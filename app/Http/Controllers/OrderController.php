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
use App\Services\Pricing\VideoPricingCalculator;
use App\Support\OrderItemBillingReference;
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
        $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();
        $unassignedStatus  = OrderItemStatus::where('name', 'Unassigned')->first();

        $cartItems = $order->orderItems()
            ->where('order_item_status_id', $stillInCartStatus->id)
            ->get();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Conflict: No items found in cart for this order context.'
            ], 409);
        }

        DB::transaction(function () use ($order, $cartItems, $unassignedStatus) {
            foreach ($cartItems as $item) {
                $item->update([
                    'order_item_status_id' => $unassignedStatus->id
                ]);
            }
        });

        // Trigger one final billing pass to freeze names (e.g. converting 'First Cut' statuses to history anchors)
        if (!$order->is_demo) {
            $calculator = new VideoPricingCalculator();
            $calculator->recalculateInvoice($order);
        }

        // Synchronize general status tags across your tracking indexes
        $order->syncStatusAndTags();

        $invoice = $order->invoices()->where('status', 'Held')->with('lines.orderItem.statusLookup')->first();

        return response()->json([
            'message' => 'Order submitted successfully.',
            'order'   => $order->load('orderItems.statusLookup'),
            'invoice' => $invoice
        ], 200);
    }

    /**
     * Display the specified parent order with complete nested relationship detail trees.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'venue:id,name,city,state',
            'tour:id,name',
            'client:id,first_name,last_name,email,organisation_id',
            'showDates:id,order_id,show_date',
            'orderItems' => function($query) {
                $query->select([
                    'id', 'order_id', 'order_menu_item_id', 'order_item_status_id', 
                    'due_date', 'asset_path', 'specifiable_id', 'specifiable_type'
                ]);
            },
            'orderItems.orderMenuItem:id,name,billing_code,order_menu_category_id',
            'orderItems.statusLookup:id,name',
            'orderItems.assignees:id,first_name,last_name,avatar',
            'invoices.lines' => function($query) {
                $query->select(['id', 'invoice_id', 'order_item_id', 'description', 'unit_price', 'quantity', 'total']);
            },
            'invoices.lines.orderItem.statusLookup:id,name'
        ]);

        return response()->json([
            'order' => $order,
        ], 200);
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

        // Moved all filtering logic ABOVE the query execution block
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

        // Apply tenant boundary constraints cleanly before execution
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Supervisor'])) {
            $ordersQuery->where(function ($query) use ($user) {
                $query->where('ordered_by_id', $user->id)
                    ->orWhereHas('client', function ($q) use ($user) {
                        $q->where('organisation_id', $user->organisation_id);
                    });
            });
        }

        // Only pulls rows that matched the filters
        $orders = $ordersQuery->latest()->get();
        $orderIds = $orders->pluck('id');

        if ($orderIds->isNotEmpty()) {
            // Flat lookups for Collaborators Map
            $collaboratorsMap = DB::table('users')
                ->join('order_item_assignee', 'users.id', '=', 'order_item_assignee.user_id')
                ->join('order_items', 'order_item_assignee.order_item_id', '=', 'order_items.id')
                ->whereIn('order_items.order_id', $orderIds)
                ->select(['users.id', 'users.first_name', 'users.last_name', 'users.avatar', 'order_items.order_id'])
                ->distinct()
                ->get()
                ->groupBy('order_id');

            // Flat lookups for Menu Category Tags Map
            $categoryMap = DB::table('order_items')
                ->join('order_menu_items', 'order_items.order_menu_item_id', '=', 'order_menu_items.id')
                ->whereIn('order_items.order_id', $orderIds)
                ->select(['order_items.order_id', 'order_menu_items.order_menu_category_id'])
                ->distinct()
                ->get()
                ->groupBy('order_id');

            // Single-pass hydration loop
            foreach ($orders as $order) {
                $order->collaborators = $collaboratorsMap->get($order->id, collect())->map(function ($user) {
                    return [
                        'id'         => $user->id,
                        'first_name' => $user->first_name,
                        'last_name'  => $user->last_name,
                        'avatar'     => $user->avatar,
                    ];
                })->values();

                $orderCategoryIds = $categoryMap->get($order->id, collect())->pluck('order_menu_category_id')->toArray();
                
                $tags = [];
                if (count(array_intersect($orderCategoryIds, [1, 2, 3])) > 0) { $tags[] = 'Audio'; }
                if (in_array(4, $orderCategoryIds)) { $tags[] = 'Art'; }

                $order->tags = $tags;
                $order->unsetRelation('orderItems');
                $order->makeHidden(['orderItems', 'order_items']);
            }
        }

        return response()->json(['data' => $orders], 200);
    }

    /**
     * Clear all unsubmitted "Still In Cart" items from the specified order.
     */
    public function clearCart(Order $order): JsonResponse
    {
        $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();

        if (!$stillInCartStatus) {
            return response()->json([
                'message' => 'System Error: "Still In Cart" status dictionary could not be resolved.'
            ], 500);
        }

        $deletedCount = $order->orderItems()
            ->where('order_item_status_id', $stillInCartStatus->id)
            ->delete();

        if ($deletedCount === 0) {
            return response()->json([
                'message' => 'Conflict: No items marked "Still In Cart" were found in this order context.'
            ], 409);
        }

        // Trigger a database ledger sync pass to wipe the dropped item calculations from MySQL rows
        if (!$order->is_demo) {
            $calculator = new VideoPricingCalculator();
            $calculator->recalculateInvoice($order);
        }

        // Clean up empty master containers if asset references have hit zero
        if ($order->orderItems()->count() === 0) {
            $order->delete();
            return response()->json([
                'message' => "Successfully removed {$deletedCount} items.",
                'order_deleted' => true,
                'count' => $deletedCount
            ], 200);
        }

        return response()->json([
            'message' => "Successfully cleared {$deletedCount} unsubmitted items from the cart.",
            'order_deleted' => false,
            'count' => $deletedCount,
            'order' => $order->load('orderItems.statusLookup', 'invoices.lines.orderItem.statusLookup')
        ], 200);
    }
}