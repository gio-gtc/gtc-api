<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Invoice;
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
            
            // 65% chance to remain 'Held' with a null due date, otherwise finalized and due
            $isHeld = rand(1, 100) <= 65;
            $status = $isHeld ? 'Held' : collect(['Unpaid', 'Paid'])->random();
            $dueDate = $isHeld ? null : Carbon::now()->addDays(30)->format('Y-m-d');

            $invoiceSubtotal = 0.00;
            $calculatedLines = [];

            foreach ($order->orderItems as $item) {
                $unitPrice = (float) ($item->locked_price ?? rand(75, 450));
                $quantity = 1; 
                $lineTotal = $unitPrice * $quantity;

                $calculatedLines[] = [
                    'item'        => $item,
                    'description' => OrderItemBillingReference::fromOrderItem($item),
                    'unit_price'  => $unitPrice,
                    'quantity'    => $quantity,
                    'total'       => $lineTotal,
                ];

                $invoiceSubtotal += $lineTotal;
            }

            // Construct the master Invoice
            $invoice = Invoice::create([
                'order_id'        => $order->id,
                'organisation_id' => $order->organisation_id ?? $order->client?->organisation_id ?? 1,
                'document_number' => (string) $currentNumber,
                'status'          => $status,
                'subtotal'        => $invoiceSubtotal,
                'tax'             => 0.00,
                'total'           => $invoiceSubtotal,
                'payment_due'     => $dueDate, 
                'created_at'      => Carbon::now()->subDays(rand(1, 30)), 
            ]);

            foreach ($calculatedLines as $lineData) {
                $invoiceLine = $invoice->lines()->create([
                    'order_item_id' => $lineData['item']->id,
                    'description'   => $lineData['description'],
                    'unit_price'    => $lineData['unit_price'],
                    'quantity'      => $lineData['quantity'],
                    'total'         => $lineData['total'],
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