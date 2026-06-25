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
        // 1. Resolve target relational system lookup dictionaries immediately
        $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();
        $unassignedStatus  = OrderItemStatus::where('name', 'Unassigned')->first();

        // Defensively verify that the order has items to submit using the dictionary lookup ID
        $cartItems = $order->orderItems()
            ->where('order_item_status_id', $stillInCartStatus->id)
            ->with(['specifiable', 'orderMenuItem'])
            ->get();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Conflict: No items found in cart for this order context.'
            ], 409);
        }

        // 2. Wrap operations inside a strict database transaction to ensure atomicity
        $invoice = DB::transaction(function () use ($order, $cartItems, $unassignedStatus) {
            
            // DEMO GUARD: Showcase blueprints skip billing row compilation entirely
            if ($order->is_demo) {
                foreach ($cartItems as $item) {
                    $item->update([
                        'order_item_status_id' => $unassignedStatus->id
                    ]);
                }
                return null;
            }

            // Recover existing Held invoice or initialize a fresh ledger container shell
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

                // Construct the master Held Invoice entity (payment_due stays null while Held)
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
            $uniqueCutsTracked = [];
            $globalEncodingPlatforms = collect();

            // Pass 1: Gather all items with a 'Video' billing_code to pool encodings order-wide
            $videoItemsInCart = $cartItems->filter(fn($item) => $item->orderMenuItem?->billing_code === 'Video');
            
            foreach ($videoItemsInCart as $item) {
                $spec = $item->specifiable;
                if ($spec && isset($spec->encoding) && is_array($spec->encoding)) {
                    foreach ($spec->encoding as $platform) {
                        $globalEncodingPlatforms->push($platform);
                    }
                }
            }
            
            $totalUniqueEncodings = $globalEncodingPlatforms->unique()->count();

            // Pass 2: Main Processing Loop (Creates primary ledger records and handles status switches)
            foreach ($cartItems as $item) {
                $menuItem = $item->orderMenuItem;
                $matrix = $menuItem?->pricing_matrix ?? [];
                $billingCode = $menuItem?->billing_code;

                $itemPrice = (float) ($item->locked_price ?? 0.00);
                $description = OrderItemBillingReference::fromOrderItem($item);
                $spec = $item->specifiable;

                // Execute specialized pricing matrix rules if item matches a Video pipeline track
                if ($billingCode === 'Video' && $spec) {
                    
                    // A. Revision Scanner: Matches ISCI suffixes ending in an R# sequence pattern
                    if (!empty($spec->isci) && preg_match('/R\d+$/i', $spec->isci)) {
                        $itemPrice = (float) ($matrix['revision_price'] ?? 275.00);
                        $description = 'Revision';
                    } 
                    // B. Cut Identifier Matrix: Evaluates structural configuration groups [Type-Duration-Language]
                    else {
                        $cutSignature = implode('-', [
                            $spec->type ?? 'default',
                            $spec->duration_seconds ?? $spec->duration ?? '0',
                            $spec->language ?? 'English'
                        ]);

                        if (!in_array($cutSignature, $uniqueCutsTracked)) {
                            $itemPrice = (float) ($matrix['first_cut_price'] ?? 575.00);
                            $description = "First Cut: {$description}";
                            $uniqueCutsTracked[] = $cutSignature;
                        } else {
                            $itemPrice = (float) ($matrix['additional_cut_price'] ?? 275.00);
                            $description = "Additional Cut: {$description}";
                        }
                    }
                }

                // Write the core line ledger snapshot directly to the active invoice instance
                $invoiceLine = $invoice->lines()->create([
                    'order_item_id' => $item->id,
                    'description'   => $description,
                    'unit_price'    => $itemPrice,
                    'quantity'      => 1,
                    'total'         => $itemPrice,
                ]);

                $newItemsTotal += $itemPrice;

                // C. Fallback Standard Processing Block for Non-Video Billing Tracks (Audio, Static, etc.)
                if ($billingCode !== 'Video' && $spec) {
                    if (isset($spec->encoding) && is_array($spec->encoding) && count($spec->encoding) > 0) {
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
                }

                // Move line items out of checkout draft and attach tracking identifier links
                $item->update([
                    'order_item_status_id' => $unassignedStatus->id,
                    'invoice_line_id'      => $invoiceLine->id
                ]);
            }

            // Pass 3: Process and append consolidated global order-wide encoding breakout entries for Video assets
            if ($videoItemsInCart->isNotEmpty() && $totalUniqueEncodings > 0) {
                $primaryVideoItem = $videoItemsInCart->first();
                $videoMatrix = $primaryVideoItem->orderMenuItem?->pricing_matrix ?? [];
                
                $baseBundlePrice = (float) ($videoMatrix['base_encoding_bundle'] ?? 250.00);
                $additionalPrice = (float) ($videoMatrix['additional_encoding'] ?? 75.00);

                if ($totalUniqueEncodings === 1) {
                    // Single distribution format chosen: Render isolated base bundle row entry
                    $invoice->lines()->create([
                        'order_item_id' => $primaryVideoItem->id,
                        'description'   => 'Encoding',
                        'unit_price'    => $baseBundlePrice,
                        'quantity'      => 1,
                        'total'         => $baseBundlePrice,
                    ]);
                    $newItemsTotal += $baseBundlePrice;
                } else {
                    // Multiple formats chosen: Split package into explicit breakout rows ($250 and $0)
                    $invoice->lines()->create([
                        'order_item_id' => $primaryVideoItem->id,
                        'description'   => 'Encoding',
                        'unit_price'    => $baseBundlePrice,
                        'quantity'      => 1,
                        'total'         => $baseBundlePrice,
                    ]);
                    
                    $invoice->lines()->create([
                        'order_item_id' => $primaryVideoItem->id,
                        'description'   => 'Encoding',
                        'unit_price'    => 0.00,
                        'quantity'      => 1,
                        'total'         => 0.00,
                    ]);
                    
                    $newItemsTotal += $baseBundlePrice;

                    // Loop and write individual $75 overhead rows for every target past the primary 2-pack limit
                    if ($totalUniqueEncodings > 2) {
                        $extraCount = $totalUniqueEncodings - 2;
                        for ($i = 0; $i < $extraCount; $i++) {
                            $invoice->lines()->create([
                                'order_item_id' => $primaryVideoItem->id,
                                'description'   => 'Encoding',
                                'unit_price'    => $additionalPrice,
                                'quantity'      => 1,
                                'total'         => $additionalPrice,
                            ]);
                            $newItemsTotal += $additionalPrice;
                        }
                    }
                }
            }

            // Mathematically record and update running parent invoice balances cleanly
            $invoice->update([
                'subtotal' => $invoice->subtotal + $newItemsTotal,
                'total'    => $invoice->total + $newItemsTotal,
            ]);

            // Synchronize production metrics and monitoring tags up to the project wrapper
            $order->syncStatusAndTags();

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
            'orderItems.specifiable',
            'invoices.lines'
        ]);

        $virtualBillingLines = [];

        // 2. Resolve the "Still In Cart" dictionary status key safely
        $stillInCartStatus = \App\Models\OrderItemStatus::where('name', 'Still In Cart')->first();

        if ($stillInCartStatus) {
            // Gather any items that are actively sitting in the checkout sandbox queue
            $cartItems = $order->orderItems()
                ->where('order_item_status_id', $stillInCartStatus->id)
                ->get();

            // If there are unsubmitted items, pass them to our service to generate the live preview rows
            if ($cartItems->isNotEmpty()) {
                $videoItems = $cartItems->filter(fn($item) => $item->orderMenuItem?->billing_code === 'Video');
                
                if ($videoItems->isNotEmpty()) {
                    $calculator = new \App\Services\Pricing\VideoPricingCalculator();
                    // Simulates the exact invoice rows ($250, $0, cuts, etc.) in memory 🧠
                    $virtualBillingLines = $calculator->calculate($order, $videoItems);
                }
            }
        }

        // 3. Return a unified envelope payload back to Thunder Client / Frontend
        return response()->json([
                'order'                 => $order,
                'virtual_billing_lines' => $virtualBillingLines,
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