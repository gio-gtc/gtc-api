<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItemStatus;
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
    public function index(Request $request): JsonResponse
    {
        $query = Order::with([
            'venue',
            'tour',
            'client',
            'statuses',
            'orderItems.statusLookup'
        ]);

        // High-Performance Filter Check: Intercept explicit request string status updates
        if ($request->has('status')) {
            $filterStatusName = $request->query('status');
            
            $query->whereHas('statuses', function ($q) use ($filterStatusName) {
                $q->where('name', $filterStatusName);
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

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
    // 1. Resolve target relational system lookup dictionaries immediately
    $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();
    $unassignedStatus  = OrderItemStatus::where('name', 'Unassigned')->first();

    // Defensively verify that the order has items to submit using the dictionary lookup ID
    $cartItems = $order->orderItems()
        ->where('order_item_status_id', $stillInCartStatus->id)
        ->get();
    
    if ($cartItems->isEmpty()) {
        return response()->json([
            'message' => 'Conflict: No items found in cart for this order context.'
        ], 409);
    }

    // 2. Wrap operations inside a strict database transaction to ensure atomicity
    $invoice = DB::transaction(function () use ($order, $cartItems, $unassignedStatus) {
        
        // Step A: Advance line item status pipelines out of the cart state using foreign key IDs
        foreach ($cartItems as $item) {
            $item->update([
                'order_item_status_id' => $unassignedStatus->id
            ]);
        }

        // DEMO GUARD: Showcase blueprints skip billing row compilation entirely
        if ($order->is_demo) {
            return null;
        }

        // Step B: Resolve the client contact's organizational credit terms
        $clientUser = $order->client; 
        if (!$clientUser || !$clientUser->organisation_id) {
            throw new \Exception('Precondition Failed: Order client organization details are unresolved.');
        }
        
        $organisation = $clientUser->organisation;
        
        $days = 30;
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
            'document_number' => $nextDocumentNumber,
            'status'          => 'Held',
            'payment_due'     => $paymentDue,
        ]);

        // Step E: Snapshot line item detail states into decoupled billing files
        foreach ($cartItems as $item) {
            $invoiceLine = InvoiceLine::create([
                'invoice_id'    => $invoice->id,
                'order_item_id' => $item->id,
                'description'   => $item->orderMenuItem?->name ?? 'Creative Deliverable Asset Production Stop',
                'price'         => $item->locked_price,
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

    /**
     * Display the specified parent order with complete nested relationship detail trees.
     */
    public function show(Order $order): JsonResponse
    {
        // Eager load everything needed to render a rich order details page
        $order->load([
            'venue',
            'tour',
            'client',
            'showDates',
            'orderItems.orderMenuItem.category',
            'orderItems.assignees'
        ]);

        return response()->json([
            'data' => $order
        ], 200);
    }
}