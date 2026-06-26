<?php

namespace App\Services\Pricing;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OrderItemStatus;
use App\Support\OrderItemBillingReference;
use Illuminate\Support\Facades\DB;

class VideoPricingCalculator
{
    /**
     * Holistic database-backed calculation loop.
     * Rebuilds fluid cart items on the Held invoice while tracking historical context.
     */
    public function recalculateInvoice(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // 1. Resolve or initialize an ongoing 'Held' invoice using your sequence generator
            $invoice = $order->invoices()->where('status', 'Held')->first();
            
            if (!$invoice) {
                $sequenceKey = 'invoice';
                $sequence = DB::table('invoice_document_sequences')
                    ->where('sequence_key', $sequenceKey)
                    ->lockForUpdate()
                    ->first();

                if (!$sequence) {
                    DB::table('invoice_document_sequences')->insert([
                        'sequence_key' => $sequenceKey,
                        'last_value'   => 975949,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $sequence = DB::table('invoice_document_sequences')->where('sequence_key', $sequenceKey)->lockForUpdate()->first();
                }

                $nextValue = $sequence->last_value + 1;
                DB::table('invoice_document_sequences')->where('id', $sequence->id)->update(['last_value' => $nextValue, 'updated_at' => now()]);

                $invoice = Invoice::create([
                    'order_id'         => $order->id,
                    'organisation_id'  => $order->organisation_id ?? $order->client?->organisation_id ?? 1,
                    'document_number'  => (string)$nextValue,
                    'status'           => 'Held',
                    'subtotal'         => 0.00,
                    'tax'              => 0.00,
                    'total'            => 0.00,
                ]);
            }

            // 2. Wipe fluid cart lines linked to active "Still In Cart" items on this invoice
            $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();
            
            if ($stillInCartStatus) {
                // Clear direct cuts/revisions
                $invoice->lines()->whereHas('orderItem', function ($query) use ($stillInCartStatus) {
                    $query->where('order_item_status_id', $stillInCartStatus->id);
                })->delete();
                
                // Clear order-wide standalone encoding allocations owned by active cart video rows
                $invoice->lines()->where('description', 'Encoding')->whereHas('orderItem', function ($query) use ($stillInCartStatus) {
                    $query->where('order_item_status_id', $stillInCartStatus->id);
                })->delete();
            }

            // 3. Query ALL non-cancelled video assets across the entire history of this order
            $allVideoItems = $order->orderItems()
                ->whereHas('statusLookup', function ($query) {
                    $query->where('name', '!=', 'Cancelled');
                })
                ->whereHas('orderMenuItem', function ($query) {
                    $query->where('billing_code', 'Video');
                })
                ->orderBy('id', 'asc') // Chronological ordering ensures accurate first vs additional cut indexing
                ->get();

            if ($allVideoItems->isEmpty()) {
                // Recalculate invoice totals based on remaining alternative lines (e.g. Audio/Static lines)
                $invoice->update([
                    'subtotal' => $invoice->lines()->sum('total'),
                    'total'    => $invoice->lines()->sum('total'),
                ]);
                return;
            }

            $uniqueCutsTracked = [];
            $globalEncodingPool = collect();

            // Pass 1: Trace cumulative chronological sequence and store active cart rows
            foreach ($allVideoItems as $item) {
                $spec = $item->specifiable;
                if (!$spec) continue;

                $menuItem = $item->orderMenuItem;
                $matrix = $menuItem?->pricing_matrix ?? [];
                $isCartItem = ($item->order_item_status_id === $stillInCartStatus->id);

                // A. Price Evaluation (Looks back over the whole order lifecycle)
                if (!empty($spec->isci) && preg_match('/R\d+$/i', $spec->isci)) {
                    $unitPrice = (float) ($matrix['revision_price'] ?? 275.00);
                    $description = 'Revision';
                } else {
                    // Added $spec->cut directly into the uniqueness string assembly!
                    $cutSignature = implode('-', [
                        $spec->type ?? 'default',
                        $spec->cut ?? 'default',
                        $spec->duration_seconds ?? $spec->duration ?? '0',
                        $spec->language ?? 'English'
                    ]);

                    if (!in_array($cutSignature, $uniqueCutsTracked)) {
                        $unitPrice = (float) ($matrix['first_cut_price'] ?? 575.00);
                        $description = "First Cut: " . OrderItemBillingReference::fromOrderItem($item);
                        $uniqueCutsTracked[] = $cutSignature;
                    } else {
                        $unitPrice = (float) ($matrix['additional_cut_price'] ?? 275.00);
                        $description = "Additional Cut: " . OrderItemBillingReference::fromOrderItem($item);
                    }
                }

                // Write the line ONLY if the asset belongs to the active cart sandbox.
                if ($isCartItem) {
                    $invoice->lines()->create([
                        'order_item_id' => $item->id,
                        'description'   => $description,
                        'unit_price'    => $unitPrice,
                        'quantity'      => 1,
                        'total'         => $unitPrice,
                    ]);
                }

                // B. Accumulate items into the order-wide global encoding pool
                $isSocial = str_contains(strtolower($menuItem?->name ?? ''), 'social');
                if ($isSocial) {
                    $globalEncodingPool->push(['item_id' => $item->id, 'platform' => "Social-Default-{$item->id}", 'is_cart' => $isCartItem]);
                } else {
                    if (isset($spec->encoding) && is_array($spec->encoding)) {
                        foreach ($spec->encoding as $platform) {
                            $globalEncodingPool->push(['item_id' => $item->id, 'platform' => $platform, 'is_cart' => $isCartItem]);
                        }
                    }
                }
            }

            // Pass 2: Calculate historical encoding tiers and write missing cart breakouts
            $totalEncodingsCount = $globalEncodingPool->count();
            
            if ($totalEncodingsCount > 0) {
                $primaryVideoItem = $allVideoItems->first();
                $videoMatrix = $primaryVideoItem->orderMenuItem?->pricing_matrix ?? [];
                $baseBundlePrice = (float) ($videoMatrix['base_encoding_bundle'] ?? 250.00);
                $additionalPrice = (float) ($videoMatrix['additional_encoding'] ?? 75.00);

                for ($i = 0; $i < $totalEncodingsCount; $i++) {
                    $encodingData = $globalEncodingPool[$i];

                    // Determine unit price rate based strictly on cumulative history array index positioning
                    if ($i === 0) {
                        $price = $baseBundlePrice;
                    } elseif ($i === 1) {
                        $price = 0.00;
                    } else {
                        $price = $additionalPrice;
                    }

                    // Save the breakout itemized line only if it scales up from a fresh cart selection
                    if ($encodingData['is_cart']) {
                        $invoice->lines()->create([
                            'order_item_id' => $encodingData['item_id'],
                            'description'   => 'Encoding',
                            'unit_price'    => $price,
                            'quantity'      => 1,
                            'total'         => $price,
                        ]);
                    }
                }
            }

            // 4. Recalculate unified totals on the master invoice record
            $invoice->update([
                'subtotal' => $invoice->lines()->sum('total'),
                'total'    => $invoice->lines()->sum('total'),
            ]);
        });
    }
}