<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use App\Models\OrderItemBroadcastSpecification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class OrderItemController extends Controller
{
    /**
     * Shared Validation Logic Engine
     */
    private function validateVideoSpecifications(OrderMenuItem $menuItem, ?array $specs): array
    {
        $customErrors = [];
        $blueprint = (array) $menuItem->form_blueprint;

        if (blank($blueprint) || !isset($blueprint['types'])) {
            $customErrors['specifications'] = ['The underlying menu item form blueprint config is invalid.'];
            return $customErrors;
        }

        $type = Arr::get($specs, 'type');
        if (!is_string($type) || !array_key_exists($type, $blueprint['types'])) {
            $customErrors['specifications.type'] = ['The selected type is invalid.'];
            return $customErrors;
        }

        $typeConfig = $blueprint['types'][$type];
        $cut = Arr::get($specs, 'cut');
        $durationSeconds = Arr::get($specs, 'duration_seconds');
        $language = Arr::get($specs, 'language');

        if ($cut === 'International TV Package' || $type === 'International') {
            if ($durationSeconds !== 30) {
                $customErrors['specifications.duration_seconds'] = ['International spots are locked to 30 seconds.'];
            }
            if (!is_string($language) || !Str::startsWith($language, 'English')) {
                $customErrors['specifications.language'] = ['International spots are locked to English.'];
            }
        } else {
            if (!is_string($cut) || !in_array($cut, $typeConfig['cuts'] ?? [], true)) {
                $customErrors['specifications.cut'] = ['The selected cut variant is not valid.'];
            }
            if (!is_int($durationSeconds) || !in_array($durationSeconds, $typeConfig['durations'] ?? [], true)) {
                $customErrors['specifications.duration_seconds'] = ['The selected duration is invalid.'];
            }
            if (!is_string($language) || !in_array($language, $typeConfig['languages'] ?? [], true)) {
                $customErrors['specifications.language'] = ['The selected language is invalid.'];
            }
        }

        $encoding = Arr::get($specs, 'encoding');
        $encodingCustom = trim((string) Arr::get($specs, 'encoding_custom'));
        $hasCatalog = !blank($encoding);
        $hasCustom = !blank($encodingCustom);

        if (($hasCatalog && $hasCustom) || (!$hasCatalog && !$hasCustom)) {
            $customErrors['specifications.encoding'] = ['Provide exactly one of encoding or encoding_custom.'];
        } elseif ($hasCatalog && !in_array($encoding, $blueprint['encodings'] ?? [], true)) {
            $customErrors['specifications.encoding'] = ['The selected catalog profile is invalid.'];
        }

        return $customErrors;
    }

    /**
     * Handles adding a polymorphic broadcast item row to a parent order.
     * Route: POST /api/orders/{order}/items
     */
    public function store(Order $order, Request $request): JsonResponse
    {
        $baseValidator = Validator::make($request->all(), [
            'order_menu_item_id' => 'required|integer|exists:order_menu_items,id',
            'due_date'           => 'required|date_format:Y-m-d',
            'specifications'     => 'required|array',
        ]);

        if ($baseValidator->fails()) {
            return response()->json(['errors' => $baseValidator->errors()], 422);
        }

        $menuItem = OrderMenuItem::findOrFail($request->input('order_menu_item_id'));
        if ((int)$menuItem->order_menu_category_id !== 1) {
            return response()->json([
                'errors' => ['order_menu_item_id' => ['The selected menu item does not belong to Category 1.']]
            ], 422);
        }

        $specs = $request->input('specifications');
        $customErrors = $this->validateVideoSpecifications($menuItem, $specs);
        if (count($customErrors) > 0) {
            return response()->json(['errors' => $customErrors], 422);
        }

        $item = DB::transaction(function () use ($order, $menuItem, $request, $specs) {
            $latestSpec = OrderItemBroadcastSpecification::orderBy('id', 'desc')->first();
            $nextSequenceNumber = 1;

            if ($latestSpec && preg_match('/GTC(\d+)/', $latestSpec->isci, $matches)) {
                $nextSequenceNumber = ((int) $matches[1]) + 1;
            }

            $paddedNumber = str_pad($nextSequenceNumber, 6, '0', STR_PAD_LEFT);
            $newIsci = "GTC{$paddedNumber}";

            $broadcastSpec = OrderItemBroadcastSpecification::create([
                'type'             => $specs['type'],
                'cut'              => $specs['cut'],
                'duration_seconds' => (int) $specs['duration_seconds'],
                'language'         => $specs['language'],
                'encoding'         => $specs['encoding'] ?? null,
                'encoding_custom'  => $specs['encoding_custom'] ?? null,
                'isci'             => $newIsci,
            ]);

            return OrderItem::create([
                'order_id'             => $order->id,
                'order_menu_item_id'   => $menuItem->id,
                'locked_price'         => $menuItem->default_price ?? '0.00',
                'order_item_status_id' => 1, 
                'due_date'             => $request->input('due_date'),
                'specifiable_id'       => $broadcastSpec->id,
                'specifiable_type'     => OrderItemBroadcastSpecification::class,
            ]);
        });

        return response()->json([
            'message' => 'Video delivery item successfully authorized and appended to cart.',
            'data'    => $item->fresh(['specifiable', 'statusLookup'])
        ], 201);
    }

    /**
     * Executes an in-place update on the specification entity tables while auto-incrementing the R-counter.
     * Route: PATCH /api/order-items/{orderItem}
     */
    public function update(OrderItem $orderItem, Request $request): JsonResponse
    {
        $menuItem = $orderItem->orderMenuItem;

        if ($orderItem->statusLookup?->name === 'Cancelled' || (int)$orderItem->order_item_status_id === 5) {
            return response()->json([
                'errors' => ['specifications' => ['This line item has been cancelled and can no longer be modified.']]
            ], 422);
        }

        $incomingSpecs = $request->input('specifications', []);
        if ((int)$menuItem->order_menu_category_id === 1) {
            $customErrors = $this->validateVideoSpecifications($menuItem, $incomingSpecs);
            if (count($customErrors) > 0) {
                return response()->json(['errors' => $customErrors], 422);
            }
        }

        $processedItem = DB::transaction(function () use ($orderItem, $request, $incomingSpecs) {
            $specification = $orderItem->specifiable;
            
            $baseIsci = !empty($specification->isci) 
                ? preg_replace('/R\d+$/', '', $specification->isci)
                : 'GTC' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);

            if ($orderItem->statusLookup?->name === 'Still In Cart' || (int)$orderItem->order_item_status_id === 1) {
                
                $specification?->delete();
                $orderItem->delete();

                $newSpec = OrderItemBroadcastSpecification::create([
                    'type'             => $incomingSpecs['type'] ?? ($specification->type ?? 'Generic'),
                    'cut'              => $incomingSpecs['cut'] ?? ($specification->cut ?? 'Pre Sale'),
                    'duration_seconds' => isset($incomingSpecs['duration_seconds']) ? (int)$incomingSpecs['duration_seconds'] : ($specification->duration_seconds ?? 30),
                    'language'         => $incomingSpecs['language'] ?? ($specification->language ?? 'English'),
                    'encoding'         => array_key_exists('encoding', $incomingSpecs) ? $incomingSpecs['encoding'] : ($specification->encoding ?? null),
                    'encoding_custom'  => array_key_exists('encoding_custom', $incomingSpecs) ? $incomingSpecs['encoding_custom'] : ($specification->encoding_custom ?? null),
                    'isci'             => $baseIsci, 
                ]);

                return OrderItem::create([
                    'order_id'             => $orderItem->order_id,
                    'order_menu_item_id'   => $orderItem->order_menu_item_id,
                    'locked_price'         => $orderItem->locked_price,
                    'order_item_status_id' => 1, 
                    'due_date'             => $request->input('due_date', $orderItem->due_date),
                    'specifiable_id'       => $newSpec->id,
                    'specifiable_type'     => OrderItemBroadcastSpecification::class,
                    'revision_number'      => 0,
                ]);
            }

            $orderItem->update(['order_item_status_id' => 5]); 

            $nextRevision = ((int) ($orderItem->revision_number ?? 0)) + 1;
            $newIsci = "{$baseIsci}R{$nextRevision}";

            $newSpec = OrderItemBroadcastSpecification::create([
                'type'             => $incomingSpecs['type'] ?? $specification->type,
                'cut'              => $incomingSpecs['cut'] ?? $specification->cut,
                'duration_seconds' => isset($incomingSpecs['duration_seconds']) ? (int)$incomingSpecs['duration_seconds'] : $specification->duration_seconds,
                'language'         => $incomingSpecs['language'] ?? $specification->language,
                'encoding'         => array_key_exists('encoding', $incomingSpecs) ? $incomingSpecs['encoding'] : $specification->encoding,
                'encoding_custom'  => array_key_exists('encoding_custom', $incomingSpecs) ? $incomingSpecs['encoding_custom'] : $specification->encoding_custom,
                'isci'             => $newIsci,
            ]);

            return OrderItem::create([
                'order_id'             => $orderItem->order_id,
                'order_menu_item_id'   => $orderItem->order_menu_item_id,
                'locked_price'         => $orderItem->locked_price,
                'order_item_status_id' => 2, 
                'due_date'             => $request->input('due_date', $orderItem->due_date),
                'specifiable_id'       => $newSpec->id,
                'specifiable_type'     => OrderItemBroadcastSpecification::class,
                'revision_number'      => $nextRevision,
            ]);
        });

        return response()->json([
            'message' => "Line item specifications updated successfully to Revision {$processedItem->revision_number}.",
            'data'    => $processedItem->fresh(['specifiable', 'statusLookup'])
        ], 200);
    }

    /**
     * Soft cancels an active row from a cart checkout view container node index.
     * Route: DELETE /api/order-items/{orderItem}
     */
    public function destroy(OrderItem $orderItem): JsonResponse
    {
        $orderItem->specifiable?->delete();
        $orderItem->delete();

        return response()->json(['message' => 'Item removed from order completely.'], 200);
    }

    /**
     * ⚡ NEW: High-Speed Polymorphic Bulk Update Strategy
     * Route: POST /api/order-items/bulk-update
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        // 1. Core structural array rules evaluation
        $validator = Validator::make($request->all(), [
            'order_item_ids'         => 'required|array',
            'order_item_ids.*'       => 'exists:order_items,id',
            'due_date'               => 'sometimes|nullable|date_format:Y-m-d',
            'order_item_status_id'   => 'sometimes|exists:order_item_statuses,id',
            'assignee_ids'           => 'sometimes|array',
            'assignee_ids.*'         => 'exists:users,id',
            'specifications'         => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $itemIds = $request->input('order_item_ids');

        return DB::transaction(function () use ($request, $itemIds) {
            // Gather baseline metadata to identify the unique parent order boundaries and spec mappings
            $itemsMeta = OrderItem::whereIn('id', $itemIds)
                ->select(['id', 'order_id', 'specifiable_id', 'specifiable_type'])
                ->get();

            if ($itemsMeta->isEmpty()) {
                return response()->json(['errors' => ['order_item_ids' => ['No valid order items found for update.']]], 422);
            }

            // Isolate unique parent IDs to trigger single status loops later
            $affectedParentOrderIds = $itemsMeta->pluck('order_id')->unique()->toArray();

            // --- STRATEGY A: DIRECT TRACKING UPDATES (DIRTY ARRAYS ONLY) ---
            $itemTableUpdates = [];
            
            if ($request->has('due_date')) {
                $itemTableUpdates['due_date'] = $request->input('due_date');
            }
            if ($request->has('order_item_status_id')) {
                $itemTableUpdates['order_item_status_id'] = $request->input('order_item_status_id');
            }

            if (!empty($itemTableUpdates)) {
                OrderItem::whereIn('id', $itemIds)->update($itemTableUpdates);
            }

            // --- STRATEGY B: MANY-TO-MANY TEAM PIVOT CLEAN SWAP ---
            if ($request->has('assignee_ids')) {
                // Clear old bindings only for the selected subset of item IDs
                DB::table('order_item_assignee')->whereIn('order_item_id', $itemIds)->delete();

                $assigneeIds = $request->input('assignee_ids', []);
                if (!empty($assigneeIds)) {
                    $bulkPivotRows = [];
                    $timestamp = now();

                    foreach ($itemIds as $itemId) {
                        foreach ($assigneeIds as $userId) {
                            $bulkPivotRows[] = [
                                'order_item_id' => $itemId,
                                'user_id'       => $userId,
                                'created_at'    => $timestamp,
                                'updated_at'    => $timestamp,
                            ];
                        }
                    }
                    // Raw high-performance DB chunk insertion
                    DB::table('order_item_assignee')->insert($bulkPivotRows);
                }
            }

            // --- STRATEGY C: POLYMORPHIC SYSTEM ENGINE FOR ADVANCED SPECS ---
            $incomingSpecs = $request->input('specifications');
            if (!empty($incomingSpecs) && is_array($incomingSpecs)) {
                
                // Homogeneous mapping rule: we analyze the first element to know which model is targeted
                $firstItem = $itemsMeta->first();
                $specModelClass = $firstItem->specifiable_type;
                $specIds = $itemsMeta->pluck('specifiable_id')->filter()->toArray();

                if ($specModelClass && !empty($specIds)) {
                    // Instantly sweeps across the active polymorphic table (e.g., Radio, Broadcast, Social)
                    $specModelClass::whereIn('id', $specIds)->update($incomingSpecs);
                }
            }

            // --- STRATEGY D: PARENT LEDGER AGGREGATION CALCULATOR ---
            // Trigger order status/tag calculation exactly once per affected order
            $parentOrders = Order::whereIn('id', $affectedParentOrderIds)->get();
            foreach ($parentOrders as $order) {
                $order->syncStatusAndTags();
            }

            return response()->json([
                'message' => 'Selected order line items batch-updated successfully.',
                'meta' => [
                    'updated_items_count' => count($itemIds),
                    'affected_orders'     => $affectedParentOrderIds
                ]
            ], 200);
        });
    }
}