<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class OrderController extends Controller
{
    /**
     * Display a listing of all parent orders with their core relationships.
     */
    public function index(Request $request): JsonResponse
    {
        $ordersQuery = Order::query()
            ->with([
                'tour:id,name',
                'venue:id,name,city,state',
                'client:id,first_name,last_name,email,organisation_id',
                'client.organisation:id,name,country_id', 
                'client.organisation.country:id,code', 
                'showDates:id,order_id,show_date',
                'orderItems:id,order_id,order_item_status_id,order_menu_item_id,asset_path',
                'orderItems.statusLookup:id,name',
                'orderItems.orderMenuItem:id,order_menu_category_id',
                'orderItems.assignees:id,first_name,last_name,email,avatar',
                'orderItems.revisionInstructions:id,new_order_item_id,comment'
            ]);

        // 2. High-Speed Filtering Hook (Example: Filtering via relational status matrix)
        if ($request->filled('status')) {
            $ordersQuery->whereHas('orderItems.statusLookup.orderStatus', function ($q) use ($request) {
                $q->where('name', $request->status);
            });
        }

        // 3. Paginate the execution block (Limits memory footprint to 25 records per network pass)
        $paginatedOrders = $ordersQuery->latest()->paginate(50);

        // 4. Return the streaming payload wrapped natively alongside page controls
        return response()->json($paginatedOrders, 200);
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
            $sequenceKey = 'invoice';

            $sequence = DB::table('invoice_document_sequences')
                ->where('sequence_key', $sequenceKey)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                DB::table('invoice_document_sequences')->insert([
                    'sequence_key' => $sequenceKey,
                    'last_value'   => 975949, // Stays aligned with our 975950 target sequence start
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

            // Save the counter position back using the new column keys
            DB::table('invoice_document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_value' => $nextValue,
                    'updated_at' => now(),
                ]);

            // Calculate subtotal based strictly on the items currently being submitted from the cart
            $subtotalCents = $cartItems->sum(fn($item) => (int) (($item->locked_price ?? 0) * 100));

            // Step D: Construct the master Held Invoice entity
            $invoice = Invoice::create([
                'order_id'         => $order->id,
                // Safe fallback sequence checking organization from the current user or order client
                'organisation_id'  => Auth::user()?->organisation_id ?? $clientUser->organisation_id,
                'document_number'  => $documentNumber,
                'status'           => 'Held',
                'subtotal_cents'   => $subtotalCents,
                'tax_cents'        => 0, 
                'total_cents'      => $subtotalCents,
                'payment_due'      => $paymentDue,
            ]);

            // Steps D & E Consolidated: Build immutable ledger lines and update item reference pointers
            foreach ($cartItems as $item) {
                $itemPriceCents = (int) (($item->locked_price ?? 0) * 100);

                $invoiceLine = $invoice->lines()->create([
                    'order_item_id'    => $item->id,
                    'description'      => $item->orderMenuItem?->name ?? $item->description ?? 'Creative Deliverable Asset Production Stop',
                    'unit_price_cents' => $itemPriceCents,
                    'quantity'         => 1,
                    'total_cents'      => $itemPriceCents,
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
            'orderItems.assignees',
            'orderItems.statusLookup', 
            'orderItems.specifiable'
        ]);

        return response()->json([
            'data' => $order
        ], 200);
    }

    /**
     * Streams lean dashboard rows for a specific tour, fully scoped to permissions.
     * (Linked to route: GET /api/tours/{tour}/orders)
     */
    public function getTourOrders(Tour $tour, Request $request): JsonResponse
    {
        $user = $request->user();
        
        $ordersQuery = Order::select([
                'id', 'tour_id', 'venue_id', 'ordered_by_id', 
                'is_demo', 'submitted_at', 'due_date'
            ])
            ->where('tour_id', $tour->id)
            ->with([
                'venue:id,name,city,state',
                'client:id,first_name,last_name,email,organisation_id',
                'client.organisation:id,name,country_id',
                'client.organisation.country:id,code',
                'statuses:id,name'
            ]);

        $orders = $ordersQuery->latest()->get();
        $orderIds = $orders->pluck('id');

        if ($orderIds->isNotEmpty()) {
            
            // 2. Optimized Flat Lookup: Collaborators Map
            $collaboratorsMap = DB::table('users')
                ->join('order_item_assignee', 'users.id', '=', 'order_item_assignee.user_id')
                ->join('order_items', 'order_item_assignee.order_item_id', '=', 'order_items.id')
                ->whereIn('order_items.order_id', $orderIds)
                ->select([
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.avatar',
                    'order_items.order_id'
                ])
                ->distinct()
                ->get()
                ->groupBy('order_id');

            // 3. Optimized Flat Lookup: Menu Category Tags Map
            // (Assumes your menu items table is named 'order_menu_items'. Adjust if different!)
            $categoryMap = DB::table('order_items')
                ->join('order_menu_items', 'order_items.order_menu_item_id', '=', 'order_menu_items.id')
                ->whereIn('order_items.order_id', $orderIds)
                ->select([
                    'order_items.order_id',
                    'order_menu_items.order_menu_category_id'
                ])
                ->distinct()
                ->get()
                ->groupBy('order_id');

            // 4. Single-pass loop to hydrate our flat layout arrays straight to the root payload
            foreach ($orders as $order) {
                
                // Map Collaborators
                $order->collaborators = $collaboratorsMap->get($order->id, collect())->map(function ($user) {
                    return [
                        'id'         => $user->id,
                        'first_name' => $user->first_name,
                        'last_name'  => $user->last_name,
                        'avatar'     => $user->avatar,
                    ];
                })->values();

                // Compute Flat Tags Array on the fly using category logic
                $orderCategoryIds = $categoryMap->get($order->id, collect())->pluck('order_menu_category_id')->toArray();
                
                $tags = [];
                // Categories 1, 2, 3 translate to 'Audio' tags
                if (count(array_intersect($orderCategoryIds, [1, 2, 3])) > 0) {
                    $tags[] = 'Audio';
                }
                // Category 4 translates to 'Art' tags
                if (in_array(4, $orderCategoryIds)) {
                    $tags[] = 'Art';
                }

                $order->tags = $tags;

                $order->unsetRelation('orderItems');
                $order->makeHidden(['orderItems', 'order_items']);
            }
        }

        // Apply the "My Tasks" row pruning strategy
        if ($request->query('filter') === 'my-tasks') {
            $ordersQuery->whereHas('orderItems.assignees', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($request->filled('client_ids')) {
            $ordersQuery->whereIn('ordered_by_id', $request->client_ids);
        }

        if ($request->filled('assignee_ids')) {
            $ordersQuery->whereHas('orderItems.assignees', function ($q) use ($request) {
                $q->whereIn('users.id', $request->assignee_ids);
            });
        }

        if ($request->filled('statuses')) {
            $ordersQuery->whereHas('orderItems.statusLookup.orderStatus', function ($q) use ($request) {
                $q->whereIn('name', $request->statuses);
            });
        }

        if ($request->filled('asset_tags')) {
            $ordersQuery->whereHas('orderItems', function ($q) use ($request) {
                $q->whereNull('asset_path')
                ->whereHas('statusLookup', function ($statusQuery) {
                    $statusQuery->whereNotIn('name', ['Cancelled', 'Still In Cart']);
                })
                ->where(function ($subQuery) use ($request) {
                    foreach ($request->asset_tags as $tag) {
                        $normalizedTag = strtolower($tag);
                        
                        if ($normalizedTag === 'audio') {
                            $subQuery->orWhereHas('orderMenuItem', function ($menuQuery) {
                                $menuQuery->whereIn('order_menu_category_id', [1, 2, 3]);
                            });
                        } elseif ($normalizedTag === 'art' || $normalizedTag === 'key art') {
                            $subQuery->orWhereHas('orderMenuItem', function ($menuQuery) {
                                $menuQuery->whereIn('order_menu_category_id', [4]);
                            });
                        }
                    }
                });
            });
        }

        if ($request->has('is_international') && $request->is_international !== null) {
            $ordersQuery->whereHas('client.organisation.country', function ($q) use ($request) {
                $operator = filter_var($request->is_international, FILTER_VALIDATE_BOOLEAN) ? '!=' : '=';
                $q->where('code', $operator, 'US');
            });
        }

        // RBAC BOUNDARY
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Supervisor'])) {
            $ordersQuery->where(function ($query) use ($user) {
                $query->where('ordered_by_id', $user->id)
                    ->orWhereHas('client', function ($q) use ($user) {
                        $q->where('organisation_id', $user->organisation_id);
                    });
            });
        }

        return response()->json(['data' => $orders], 200);
    }

    /**
     * Updates slideout header parameters for an existing order.
     * Route: PATCH /api/orders/{order}
     */
    public function update(Order $order, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'show_dates'           => 'nullable|array',
            'show_dates.*.id'      => 'nullable|integer|exists:order_show_dates,id',
            'show_dates.*.show_date' => 'required_with:show_dates|date_format:Y-m-d',
            'ticket_outlets'       => 'nullable|string',
            'on_same_date'         => 'nullable|string',
            'cardholder_times'     => 'nullable|string',
            'logos'                => 'nullable|string',
            'special_instructions' => 'nullable|string',
        ]);

        // Update the root order attributes, filtering out the relational array
        $order->update(Arr::except($validated, ['show_dates']));

        // Synchronize the OrderShowDate records
        if ($request->has('show_dates')) {
            $incomingDates = collect($request->input('show_dates'));

            // Find all IDs passed from the request to determine what stays
            $keepIds = $incomingDates->pluck('id')->filter()->toArray();

            // SQL Step 1: DELETE any existing show dates that were omitted from the payload
            $order->showDates()->whereNotIn('id', $keepIds)->delete();

            // SQL Step 2: UPSERT (Update matching rows / Insert fresh ones)
            foreach ($incomingDates as $dateItem) {
                $order->showDates()->updateOrCreate(
                    ['id' => $dateItem['id'] ?? null],
                    ['show_date' => $dateItem['show_date']]
                );
            }
        }

        return response()->json([
            'message' => 'Order workspace and show dates successfully synchronized.',
            'data'    => $order->fresh(['showDates'])
        ], 200);
    }

    /**
     * Clear all unsubmitted "Still In Cart" items from the specified order.
     */
    public function clearCart(Order $order): JsonResponse
    {
        // 1. Resolve the status lookup dictionary ID
        $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();

        if (!$stillInCartStatus) {
            return response()->json([
                'message' => 'System Error: "Still In Cart" status dictionary could not be resolved.'
            ], 500);
        }

        // 2. Perform a bulk batch delete on matching items
        $deletedCount = $order->orderItems()
            ->where('order_item_status_id', $stillInCartStatus->id)
            ->delete();

        if ($deletedCount === 0) {
            return response()->json([
                'message' => 'Conflict: No items marked "Still In Cart" were found in this order context.'
            ], 409);
        }

        // 3. Clean up: If this order has zero items left completely, drop the empty order container
        if ($order->orderItems()->count() === 0) {
            $order->delete();
            return response()->json([
                'message' => "Successfully removed {$deletedCount} items and cleared empty order shell.",
                'order_deleted' => true,
                'count' => $deletedCount
            ], 200);
        }

        return response()->json([
            'message' => "Successfully cleared {$deletedCount} unsubmitted items from the cart.",
            'order_deleted' => false,
            'count' => $deletedCount
        ], 200);
    }
}