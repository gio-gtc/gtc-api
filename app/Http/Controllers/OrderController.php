<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Display a listing of all parent orders with their core relationships.
     */
    public function index(): JsonResponse
    {
        $orders = Order::with([
            'venue',
            'tour',
            'client',
            'orderItems.orderMenuItem.category',
            'orderItems.assignees'
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        // 🚀 UPDATED: Enforce the standard unified JSON response contract wrapping
        return response()->json([
            'data' => $orders
        ], 200);
    }

    /**
     * Create a brand-new master order shell with synchronized show dates.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validate the core project requirements alongside nested date inputs
        $validated = $request->validate([
            'tour_id'                 => 'required|exists:tours,id',
            'is_demo'                 => 'boolean',
            'venue_id'                => 'required_unless:is_demo,true|nullable|exists:venues,id',
            'ordered_by_id'           => 'nullable|exists:users,id',
            'local_deliverable_email' => 'nullable|email',
            'due_date'                => 'nullable|date',
            'status'                  => 'string',
            'show_dates'              => 'nullable|array',
            'show_dates.*.show_date'  => 'required|date',
        ]);

        // 2. Privilege boundary evaluation for order target creation assignment
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $orderedById = $user->id;
        if ($request->has('ordered_by_id') && $user->hasAnyRole(['Admin', 'Super Admin'])) {
            $orderedById = $validated['ordered_by_id'];
        }

        // 3. Create the parent record container initializing tracking variables immediately
        $order = Order::create([
            'tour_id'                 => $validated['tour_id'],
            'venue_id'                => $validated['venue_id'] ?? null,
            'due_date'                => $validated['due_date'] ?? null,
            'local_deliverable_email' => $validated['local_deliverable_email'] ?? null,
            'ordered_by_id'           => $orderedById,
            'status'                  => 'New Order', // Handled securely via model Title Case mutators
            'submitted_at'            => now(),
            'is_demo'                 => $validated['is_demo'] ?? false,
        ]);

        // 4. Unpack Show dates: Decouple incoming parameters into dedicated table relationships
        if (!empty($validated['show_dates'])) {
            foreach ($validated['show_dates'] as $dateBlock) {
                $order->showDates()->create([
                    'show_date' => $dateBlock['show_date']
                ]);
            }
        }

        // 5. Return payload wrapped in the specified network envelope structure
        return response()->json([
            'message' => 'Order created successfully.',
            'data'    => $order->load('showDates')
        ], 201);
    }

    public function submit(Order $order): JsonResponse
    {
        // 1. Defensively verify that the order has items to submit
        $cartItems = $order->orderItems()->where('status', 'Still In Cart')->get();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Conflict: No items found in cart for this order context.'
            ], 409);
        }

        // 2. Wrap operations inside a strict database transaction to ensure atomicity
        $invoice = DB::transaction(function () use ($order, $cartItems) {
            
            // Step A: Advance line item status pipelines out of the cart state
            foreach ($cartItems as $item) {
                $item->update([
                    'status' => 'Unassigned' // Handled via model Title Case mutators
                ]);
            }

            // 🛡️ DEMO GUARD: Showcase blueprints skip billing row compilation entirely
            if ($order->is_demo) {
                return null;
            }

            // Step B: Resolve the client contact's organizational credit terms
            $clientUser = $order->client; 
            if (!$clientUser || !$clientUser->organisation_id) {
                throw new \Exception('Precondition Failed: Order client organization details are unresolved.');
            }
            
            $organisation = $clientUser->organisation;
            
            // Parse "Net 30", "Net 45", etc. from text strings to compute real deadline dates
            $days = 30; // Standard baseline fallback default
            if (!empty($organisation->credit_terms) && preg_match('/\d+/', $organisation->credit_terms, $matches)) {
                $days = (int) $matches[0];
            }
            $paymentDue = Carbon::now()->addDays($days)->format('Y-m-d');

            // Step C: Isolated Document Sequencing (Preventing duplicate number selection)
            $sequence = DB::table('invoice_document_sequences')
                ->where('company_id', 1)
                ->lockForUpdate() // 🚀 ATOMIC ROW-LEVEL LOCK
                ->first();

            if (!$sequence) {
                // Initialize global configuration sequence tracking container if completely missing
                DB::table('invoice_document_sequences')->insert([
                    'company_id'            => 1,
                    'last_document_number'  => 0,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
                $nextDocumentNumber = 1;
            } else {
                $nextDocumentNumber = $sequence->last_document_number + 1;
            }

            // Update the sequence marker safely under locked execution limits
            DB::table('invoice_document_sequences')
                ->where('company_id', 1)
                ->update([
                    'last_document_number' => $nextDocumentNumber,
                    'updated_at'           => now()
                ]);

            // Step D: Construct the master Held Invoice entity
            $invoice = Invoice::create([
                'organisation_id' => $organisation->id,
                'company_id'      => 1,
                'document_number' => $nextDocumentNumber, // Assigned instantly at checkout creation
                'status'          => 'Held',
                'payment_due'     => $paymentDue,
            ]);

            // Step E: Snapshot line item detail states into decoupled billing files
            foreach ($cartItems as $item) {
                $invoiceLine = InvoiceLine::create([
                    'invoice_id'    => $invoice->id,
                    'order_item_id' => $item->id,
                    'description'   => $item->orderMenuItem?->name ?? 'Creative Deliverable Asset Production Stop',
                    'price'         => $item->locked_price, // Decoupled snapshot copy allows independent updates
                ]);

                // Track relation reference links directly within item lineage audit structures
                $item->update([
                    'invoice_line_id' => $invoiceLine->id
                ]);
            }

            return $invoice;
        });

        // 3. Return payload enveloped matching unified response rules
        return response()->json([
            'message' => 'Order submitted successfully.',
            'data'    => [
                'order'   => $order->load('orderItems'),
                'invoice' => $invoice ? $invoice->load('lines') : null
            ]
        ], 200);
    }
}