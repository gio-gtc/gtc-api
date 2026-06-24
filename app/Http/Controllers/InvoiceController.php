<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Support\OrderItemBillingReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class InvoiceController extends Controller
{
    /**
     * Convert "In Cart" items into a fixed invoice or append them to an existing Held invoice.
     */
    public function store(Order $order): JsonResponse
    {
        try {
            $invoice = DB::transaction(function () use ($order) {
                
                // 1. Query "Still In Cart" line items belonging to this specific order
                $cartItems = OrderItem::where('order_id', $order->id)
                    ->whereHas('statusLookup', function ($query) {
                        $query->where('name', 'Still In Cart');
                    })
                    ->with(['specifiable', 'orderMenuItem'])
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw new Exception("No 'Still In Cart' items found available for checkout assignment.");
                }

                // 2. Check for an existing open "Held" invoice attached to this order shell
                $invoice = $order->invoices()->where('status', 'Held')->first();

                if (!$invoice) {
                    $sequenceKey = 'invoice';

                    // ATOMIC ROW LOCK: Queues up concurrent sequence generation streams
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

                        $sequence = DB::table('invoice_document_sequences')
                            ->where('sequence_key', $sequenceKey)
                            ->lockForUpdate()
                            ->first();
                    }

                    $nextValue = $sequence->last_value + 1;
                    $documentNumber = (string)$nextValue;

                    // Update central sequence pointer tracking index checkpoint
                    DB::table('invoice_document_sequences')
                        ->where('id', $sequence->id)
                        ->update([
                            'last_value' => $nextValue,
                            'updated_at' => now(),
                        ]);

                    // Generate master baseline tracking ledger shell
                    $invoice = Invoice::create([
                        'order_id'         => $order->id,
                        'organisation_id'  => $order->organisation_id,
                        'document_number'  => $documentNumber,
                        'status'           => 'Held',
                        'subtotal_cents'   => 0,
                        'tax_cents'        => 0,
                        'total_cents'      => 0,
                        'payment_due'      => null,
                    ]);
                }

                $newItemsTotalCents = 0;

                // 3. Clone item configuration mappings over to immutable lines
                foreach ($cartItems as $item) {
                    $itemPriceCents = (int)($item->locked_price * 100);

                    // Write row directly to the assigned invoice instance using your dedicated reference class ⚡
                    $invoiceLine = $invoice->lines()->create([
                        'order_item_id'    => $item->id,
                        'description'      => OrderItemBillingReference::fromOrderItem($item),
                        'unit_price_cents' => $itemPriceCents,
                        'quantity'         => 1,
                        'total_cents'      => $itemPriceCents,
                    ]);

                    $newItemsTotalCents += $itemPriceCents;

                    // Push items into production stream (Status 2 = Unassigned) and map line reference
                    $item->update([
                        'order_item_status_id' => 2,
                        'invoice_line_id'      => $invoiceLine->id
                    ]);
                }

                // 4. Update parent invoice totals (handles new builds vs appending merges seamlessly)
                $invoice->update([
                    'subtotal_cents' => $invoice->subtotal_cents + $newItemsTotalCents,
                    'total_cents'    => $invoice->total_cents + $newItemsTotalCents,
                ]);

                // 5. Synchronize system workflow logic flags up to the parent order container
                $order->syncStatusAndTags();

                return $invoice;
            });

            return response()->json([
                'message' => 'Order processed and items successfully assigned to ledger routing.',
                'data'    => $invoice->load('lines')
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'errors' => ['billing' => [$e->getMessage()]]
            ], 422);
        }
    }
}