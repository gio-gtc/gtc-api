<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class InvoiceController extends Controller
{
    /**
     * Convert "In Cart" items into a fixed invoice and move items to Unassigned status.
     */
    public function store(Order $order): JsonResponse
    {
        try {
            $invoice = DB::transaction(function () use ($order) {
                $sequenceKey = 'invoice';

                // 1. ATOMIC ROW LOCK
                // Forces concurrent invoice generation streams to queue up on this exact key
                $sequence = DB::table('invoice_document_sequences')
                    ->where('sequence_key', $sequenceKey)
                    ->lockForUpdate()
                    ->first();

                // 2. Fallback initialization if seeder hasn't populated the database
                if (!$sequence) {
                    DB::table('invoice_document_sequences')->insert([
                        'sequence_key' => $sequenceKey,
                        'last_value'   => 975949, // Base sequence start index
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $sequence = DB::table('invoice_document_sequences')
                        ->where('sequence_key', $sequenceKey)
                        ->lockForUpdate()
                        ->first();
                }

                // 3. Flat numeric calculations
                $nextValue = $sequence->last_value + 1;
                $documentNumber = (string)$nextValue; // Outputs string signature: "975950"

                // 4. Save the counter position back to the sequence table instantly
                DB::table('invoice_document_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_value' => $nextValue,
                        'updated_at' => now(),
                    ]);

                // 5. Query "Still In Cart" line items belonging to this specific order
                $cartItems = OrderItem::where('order_id', $order->id)
                    ->whereHas('statusLookup', function ($query) {
                        $query->where('name', 'Still In Cart');
                    })
                    ->with('specifiable')
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw new Exception("No 'Still In Cart' items found available for checkout assignment.");
                }

                // Calculate ledger figures in standard integer currency cents
                $subtotalCents = $cartItems->sum(fn($item) => (int)($item->locked_price * 100));
                $totalCents = $subtotalCents;

                // 6. Generate the master invoice baseline tracking record
                $invoice = Invoice::create([
                    'order_id'         => $order->id,
                    'organisation_id'  => $order->organisation_id,
                    'document_number'  => $documentNumber,
                    'status'           => 'Unpaid',
                    'subtotal_cents'   => $subtotalCents,
                    'tax_cents'        => 0,
                    'total_cents'      => $totalCents,
                    'payment_due'      => now()->addDays(30),
                ]);

                // 7. Clone data snapshot configurations over to immutable ledger sublines
                foreach ($cartItems as $item) {
                    $spec = $item->specifiable;
                    
                    // Build format-aware descriptive strings dynamically
                    $description = class_basename($item->specifiable_type);
                    if ($spec && isset($spec->type)) {
                        $description .= " - {$spec->type}";
                    }
                    if ($spec && isset($spec->cut)) {
                        $description .= " ({$spec->cut})";
                    }

                    $invoice->lines()->create([
                        'order_item_id'    => $item->id,
                        'description'      => $description,
                        'unit_price_cents' => (int)($item->locked_price * 100),
                        'quantity'         => 1,
                        'total_cents'      => (int)($item->locked_price * 100),
                    ]);

                    // 8. Push items into production stream (Status 2 = Unassigned)
                    $item->update([
                        'order_item_status_id' => 2
                    ]);
                }

                // 9. Synchronize system workflow logic flags up to the parent order container
                $order->syncStatusAndTags();

                return $invoice;
            });

            return response()->json([
                'message' => 'Order processed and items successfully pushed to production.',
                'data'    => $invoice->load('lines')
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'errors' => ['billing' => [$e->getMessage()]]
            ], 422);
        }
    }
}