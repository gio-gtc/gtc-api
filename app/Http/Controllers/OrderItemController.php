<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use App\Models\OrderItemBroadcastSpecs;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

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
            // 🔓 Cast to int since duration can now be passed as a string like "30"
            if ((int)$durationSeconds !== 30) {
                $customErrors['specifications.duration_seconds'] = ['International spots are locked to 30 seconds.'];
            }
            if (!is_string($language) || !Str::startsWith($language, 'English')) {
                $customErrors['specifications.language'] = ['International spots are locked to English.'];
            }
        } else {
            // 🔓 Since fields are now open custom strings, we simply validate that they aren't left blank
            if (blank($cut)) {
                $customErrors['specifications.cut'] = ['The cut variant is required.'];
            }
            if (blank($durationSeconds)) {
                $customErrors['specifications.duration_seconds'] = ['The duration is required.'];
            }
            if (blank($language)) {
                $customErrors['specifications.language'] = ['The language is required.'];
            }
        }

        $encoding = Arr::get($specs, 'encoding'); // This will now come in as an array

        if (!is_array($encoding) || empty($encoding)) {
            $customErrors['specifications.encoding'] = ['At least one encoding profile must be selected.'];
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
            $latestSpec = OrderItemBroadcastSpecs::orderBy('id', 'desc')->first();
            $nextSequenceNumber = 1;

            if ($latestSpec && preg_match('/GTC(\d+)/', $latestSpec->isci, $matches)) {
                $nextSequenceNumber = ((int) $matches[1]) + 1;
            }

            $paddedNumber = str_pad($nextSequenceNumber, 6, '0', STR_PAD_LEFT);
            $newIsci = "GTC{$paddedNumber}";

            $broadcastSpec = OrderItemBroadcastSpecs::create([
                'type'             => $specs['type'],
                'cut'              => $specs['cut'],
                'duration_seconds' => (int) $specs['duration_seconds'],
                'language'         => $specs['language'],
                'encoding'         => $specs['encoding'] ?? null,
                'isci'             => $newIsci,
            ]);

            return OrderItem::create([
                'order_id'             => $order->id,
                'order_menu_item_id'   => $menuItem->id,
                'locked_price'         => $menuItem->default_price ?? '0.00',
                'order_item_status_id' => 1, 
                'due_date'             => $request->input('due_date'),
                'specifiable_id'       => $broadcastSpec->id,
                'specifiable_type'     => OrderItemBroadcastSpecs::class,
            ]);
        });

        return response()->json([
            'message' => 'Video delivery item successfully authorized and appended to cart.',
            'data'    => $item->fresh(['specifiable', 'statusLookup'])
        ], 201);
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
     * Executes an in-place update on the specification entity tables while auto-incrementing the R-counter.
     * Route: PATCH /api/order-items/{orderItem}
     */
    public function update(OrderItem $orderItem, Request $request): JsonResponse
    {
        // 1. Guard against direct edits on already Cancelled history items
        if ((int)$orderItem->order_item_status_id == 5) {
            return response()->json([
                'errors' => ['status' => ['This historical line item has been cancelled and can no longer be edited.']]
            ], 422);
        }

        // 2. Loose validation matching our dirty fields strategy
        $validated = $request->validate([
            'due_date'             => 'sometimes|nullable|date_format:Y-m-d',
            'order_item_status_id' => 'sometimes|exists:order_item_statuses,id',
            'assignee_ids'         => 'sometimes|array',
            'assignee_ids.*'       => 'exists:users,id',
            'specifications'       => 'sometimes|array',
        ]);

        return DB::transaction(function () use ($orderItem, $request) {
            
            // Core Item field dirty-checks
            if ($request->has('due_date')) {
                $orderItem->due_date = $request->input('due_date');
            }
            if ($request->has('order_item_status_id')) {
                $orderItem->order_item_status_id = $request->input('order_item_status_id');
            }
            
            if ($orderItem->isDirty()) {
                $orderItem->save();
            }

            // Sync structural user assignees if present in request
            if ($request->has('assignee_ids')) {
                $orderItem->assignees()->sync($request->input('assignee_ids', []));
            }

            // In-place update to polymorphic spec tables
            $incomingSpecs = $request->input('specifications');
            if (!empty($incomingSpecs) && is_array($incomingSpecs)) {
                $orderItem->specifiable?->update($incomingSpecs);
            }

            // Force parent order status and tags engine refresh
            $orderItem->order?->syncStatusAndTags();

            return response()->json([
                'message' => 'Line item successfully updated in-place.',
                'data'    => $orderItem->fresh(['specifiable', 'statusLookup', 'assignees'])
            ], 200);
        });
    }

    /**
     * NEW: High-Speed Polymorphic Bulk Update Strategy
     * Route: POST /api/order-items/bulk-update
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
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
            $itemsMeta = OrderItem::whereIn('id', $itemIds)
                ->select(['id', 'order_id', 'specifiable_id', 'specifiable_type'])
                ->get();

            if ($itemsMeta->isEmpty()) {
                return response()->json(['errors' => ['order_item_ids' => ['No valid order items found.']]], 422);
            }

            $affectedParentOrderIds = $itemsMeta->pluck('order_id')->unique()->toArray();

            $itemTableUpdates = [];
            if ($request->has('due_date')) { $itemTableUpdates['due_date'] = $request->input('due_date'); }
            if ($request->has('order_item_status_id')) { $itemTableUpdates['order_item_status_id'] = $request->input('order_item_status_id'); }

            if (!empty($itemTableUpdates)) {
                OrderItem::whereIn('id', $itemIds)->update($itemTableUpdates);
            }

            if ($request->has('assignee_ids')) {
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
                    DB::table('order_item_assignee')->insert($bulkPivotRows);
                }
            }

            $incomingSpecs = $request->input('specifications');
            if (!empty($incomingSpecs) && is_array($incomingSpecs)) {
                $firstItem = $itemsMeta->first();
                $specModelClass = $firstItem->specifiable_type;
                $specIds = $itemsMeta->pluck('specifiable_id')->filter()->toArray();

                if ($specModelClass && !empty($specIds)) {
                    $specModelClass::whereIn('id', $specIds)->update($incomingSpecs);
                }
            }

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

    /**
     * Client/System Revision Request Loop (Cancel & Duplicate Archive Tracking)
     * Route: POST /api/order-items/{orderItem}/revise
     */
    public function revise(OrderItem $orderItem, Request $request): JsonResponse
    {
        $menuItem = $orderItem->orderMenuItem;

        // 1. Enforce validation rules
        $request->validate([
            'comment' => 'required|string|min:5'
        ]);

        // Guard against trying to revise an already Cancelled row (Status 7)
        if ((int)$orderItem->order_item_status_id == 7) {
            return response()->json(['errors' => ['specifications' => ['This line item has already been cancelled.']]], 422);
        }

        $processedItem = DB::transaction(function () use ($orderItem, $request) {
            
            // 1. FETCH & CLONE THE SPECIFICATION DYNAMICALLY (Works for all media tables!)
            $oldSpecification = $orderItem->specifiable;
            $newSpecification = $oldSpecification->replicate(); // Clones all unique schema columns automatically
            
            // Handle ISCI version generation loops if the column exists on this specific media spec table
            if (isset($newSpecification->isci) && !empty($oldSpecification->isci)) {
                $baseIsci = preg_replace('/R\d+$/', '', $oldSpecification->isci);
                $nextRevision = ((int) ($orderItem->revision_number ?? 0)) + 1;
                $newSpecification->isci = "{$baseIsci}R{$nextRevision}";
            }
            $newSpecification->save();

            // Move the old historical row directly to Status 7 (Cancelled)
            $orderItem->order_item_status_id = 7; 
            $orderItem->save();

            // 2. SPAWN THE NEW DUPLICATE REVISION RECORD
            $nextRevisionNumber = ((int) ($orderItem->revision_number ?? 0)) + 1;
            
            $newRevisionItem = OrderItem::create([
                'order_id'             => $orderItem->order_id,
                'order_menu_item_id'   => $orderItem->order_menu_item_id,
                'locked_price'         => $orderItem->locked_price,
                'order_item_status_id' => 5,
                'due_date'             => $orderItem->due_date,
                'specifiable_id'       => $newSpecification->id,
                'specifiable_type'     => $orderItem->specifiable_type,
                'revision_number'      => $nextRevisionNumber,
                'asset_path'            => null,
            ]);

            // 3. DUPLICATE PIVOT TABLE CONNECTIONS (Assignees)
            $oldAssigneeIds = $orderItem->assignees()->pluck('users.id')->toArray();
            if (!empty($oldAssigneeIds)) {
                $newRevisionItem->assignees()->sync($oldAssigneeIds);
            }

            // 4. WRITE LEDGER PAIRING ENTRY
            DB::table('order_item_revisions')->insert([
                'old_order_item_id' => $orderItem->id,
                'new_order_item_id' => $newRevisionItem->id,
                'user_id'           => Auth::id() ?? $orderItem->order->ordered_by_id,
                'comment'           => $request->input('comment'),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Force automatic recalculations on parent order badges/tags
            $newRevisionItem->order?->syncStatusAndTags();

            return $newRevisionItem;
        });

        return response()->json([
            'message' => "Line item successfully split to Revision {$processedItem->revision_number}.",
            'data'    => $processedItem->fresh(['specifiable', 'statusLookup', 'assignees', 'revisionInstructions'])
        ], 200);
    }
}