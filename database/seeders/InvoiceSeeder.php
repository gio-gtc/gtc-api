<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\OrderItemBillingReference;
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
        $orders = Order::doesntHave('invoices')
            ->with(['orderItems.orderMenuItem', 'orderItems.specifiable', 'client'])
            ->get();

        if ($orders->isEmpty()) {
            $this->command->info('No invoice-less orders found to seed.');
            return;
        }

        $sequenceKey = 'invoice';
        $sequence = DB::table('invoice_document_sequences')
            ->where('sequence_key', $sequenceKey)
            ->first();

        $currentNumber = $sequence ? (int)$sequence->last_value : 975949;

        foreach ($orders as $order) {
            if ($order->orderItems->isEmpty()) {
                continue;
            }

            $currentNumber++;
            
            // 35% chance to remain 'Held' with a null due date, otherwise finalized and due
            $isHeld = rand(1, 100) <= 35;
            $status = $isHeld ? 'Held' : collect(['Unpaid', 'Sent', 'Paid'])->random();
            $dueDate = $isHeld ? null : Carbon::now()->addDays(30)->format('Y-m-d');

            $invoiceSubtotalCents = 0;
            $calculatedLines = [];

            foreach ($order->orderItems as $item) {
                $rawPrice = $item->locked_price ?? rand(75, 450);
                $unitPriceCents = (int) ($rawPrice * 100);
                $quantity = 1; 
                $lineTotalCents = $unitPriceCents * $quantity;

                $calculatedLines[] = [
                    'item'             => $item,
                    'description'      => OrderItemBillingReference::fromOrderItem($item),
                    'unit_price_cents' => $unitPriceCents,
                    'quantity'         => $quantity,
                    'total_cents'      => $lineTotalCents,
                ];

                $invoiceSubtotalCents += $lineTotalCents;
            }

            // Construct the master Invoice
            $invoice = Invoice::create([
                'order_id'         => $order->id,
                'organisation_id'  => $order->organisation_id ?? $order->client?->organisation_id ?? 1,
                'document_number'  => (string) $currentNumber,
                'status'           => $status,
                'subtotal_cents'   => $invoiceSubtotalCents,
                'tax_cents'        => 0,
                'total_cents'      => $invoiceSubtotalCents,
                'payment_due'      => $dueDate, 
                'created_at'       => Carbon::now()->subDays(rand(1, 30)), 
            ]);

            foreach ($calculatedLines as $lineData) {
                $invoiceLine = $invoice->lines()->create([
                    'order_item_id'    => $lineData['item']->id,
                    'description'      => $lineData['description'],
                    'unit_price_cents' => $lineData['unit_price_cents'],
                    'quantity'         => $lineData['quantity'],
                    'total_cents'      => $lineData['total_cents'],
                ]);

                $lineData['item']->update([
                    'invoice_line_id' => $invoiceLine->id
                ]);
            }
        }

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