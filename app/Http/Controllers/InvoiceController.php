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

                    DB::table('invoice_document_sequences')
                        ->where('id', $sequence->id)
                        ->update([
                            'last_value' => $nextValue,
                            'updated_at' => now(),
                        ]);

                    // Generate a master baseline tracking ledger shell
                    $invoice = Invoice::create([
                        'order_id'         => $order->id,
                        'organisation_id'  => $order->organisation_id ?? $order->client?->organisation_id ?? 1,
                        'document_number'  => $documentNumber,
                        'status'           => 'Held',
                        'subtotal'         => 0.00, 
                        'tax'              => 0.00,
                        'total'            => 0.00,
                        'payment_due'      => null,
                    ]);
                }

                $newItemsTotal = 0.00;

                // 3. Clone item configuration mappings over to immutable lines
                foreach ($cartItems as $item) {
                    $itemPrice = (float) $item->locked_price;

                    $invoiceLine = $invoice->lines()->create([
                        'order_item_id' => $item->id,
                        'description'   => OrderItemBillingReference::fromOrderItem($item),
                        'unit_price'    => $itemPrice,
                        'quantity'      => 1,
                        'total'         => $itemPrice,
                    ]);

                    $newItemsTotal += $itemPrice;

                    // ⚡ Path A is open to ALL items. If a Radio (Audio) or Key Art (Static) item 
                    // ever includes an encoding payload array down the line, this will catch and process it.
                    $spec = $item->specifiable;
                    if ($spec && isset($spec->encoding) && is_array($spec->encoding) && count($spec->encoding) > 0) {
                        $encodingCount = count($spec->encoding);
                        $pricePerTarget = 50.00; 
                        $totalEncoding = $pricePerTarget * $encodingCount;

                        $invoice->lines()->create([
                            'order_item_id' => $item->id,
                            'description'   => 'Encoding',
                            'unit_price'    => $pricePerTarget,
                            'quantity'      => $encodingCount,
                            'total'         => $totalEncoding,
                        ]);

                        $newItemsTotal += $totalEncoding;
                    }

                    $item->update([
                        'order_item_status_id' => 2,
                        'invoice_line_id'      => $invoiceLine->id
                    ]);
                }

                // 4. Update parent invoice totals seamlessly
                $invoice->update([
                    'subtotal' => $invoice->subtotal + $newItemsTotal,
                    'total'    => $invoice->total + $newItemsTotal,
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