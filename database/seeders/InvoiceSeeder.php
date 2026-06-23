<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Grab orders that don't currently have an invoice attached
        $orders = Order::doesntHave('invoices')
            ->with(['orderItems.orderMenuItem', 'client'])
            ->get();

        if ($orders->isEmpty()) {
            $this->command->info('No invoice-less orders found to seed.');
            return;
        }

        // 2. Fetch or initialize our central document sequence tracking pointer
        $sequenceKey = 'invoice';
        $sequence = DB::table('invoice_document_sequences')
            ->where('sequence_key', $sequenceKey)
            ->first();

        $currentNumber = $sequence ? (int)$sequence->last_value : 975949;

        // 3. Process each order container atomically
        foreach ($orders as $order) {
            if ($order->orderItems->isEmpty()) {
                continue;
            }

            $currentNumber++;
            
            $invoiceSubtotalCents = 0;
            $calculatedLines = [];

            // Stage A: Map individual line prices directly from OrderItems
            foreach ($order->orderItems as $item) {
                // Read the actual item's locked price (fallback to a realistic random price if empty)
                $rawPrice = $item->locked_price ?? rand(75, 450);
                $unitPriceCents = (int) ($rawPrice * 100);
                $quantity = 1; 
                $lineTotalCents = $unitPriceCents * $quantity;

                // Queue the calculated data matrix for the second step
                $calculatedLines[] = [
                    'item'             => $item,
                    'description'      => $item->orderMenuItem?->name ?? $item->description ?? 'Creative Deliverable Asset Production Stop',
                    'unit_price_cents' => $unitPriceCents,
                    'quantity'         => $quantity,
                    'total_cents'      => $lineTotalCents,
                ];

                // Cumulatively build the true invoice total mathematical sum
                $invoiceSubtotalCents += $lineTotalCents;
            }

            // Stage B: Construct the master Invoice with the exact sum of lines
            $invoice = Invoice::create([
                'order_id'         => $order->id,
                'organisation_id'  => $order->client?->organisation_id ?? 1,
                'document_number'  => (string) $currentNumber,
                'status'           => collect(['Held', 'Paid', 'Sent'])->random(), 
                'subtotal_cents'   => $invoiceSubtotalCents,
                'tax_cents'        => 0,
                'total_cents'      => $invoiceSubtotalCents,
                'payment_due'      => Carbon::now()->addDays(30)->format('Y-m-d'),
                'created_at'       => Carbon::now()->subDays(rand(1, 30)), 
            ]);

            // Stage C: Insert the lines and tie the relational pointers back together
            foreach ($calculatedLines as $lineData) {
                $invoiceLine = $invoice->lines()->create([
                    'order_item_id'    => $lineData['item']->id,
                    'description'      => $lineData['description'],
                    'unit_price_cents' => $lineData['unit_price_cents'],
                    'quantity'         => $lineData['quantity'],
                    'total_cents'      => $lineData['total_cents'],
                ]);

                // ⚡ Mirror production: Connect the order item back to its generating invoice line
                $lineData['item']->update([
                    'invoice_line_id' => $invoiceLine->id
                ]);
            }
        }

        // 4. Update the tracking sequences ledger table to maintain systemic progression alignment
        DB::table('invoice_document_sequences')
            ->updateOrInsert(
                ['sequence_key' => $sequenceKey],
                [
                    'last_value' => $currentNumber,
                    'updated_at' => now(),
                ]
            );
    }
}