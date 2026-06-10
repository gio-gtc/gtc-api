<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AssigneeController extends Controller
{
    /**
     * READ: Get all users assigned to a specific order item
     * GET /api/order-items/{order_item}/assignees
     */
    public function index(OrderItem $orderItem): JsonResponse
    {
        return response()->json([
            'data' => $orderItem->assignees()->get()
        ], 200);
    }

    /**
     * CREATE / UPDATE: Sync an array of user IDs to the item
     * POST /api/order-items/{order_item}/assignees
     */
    public function store(Request $request, OrderItem $orderItem): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Using sync() handles the entire CRUD lifecyle for a multi-select dropdown.
        // It automatically adds new IDs, keeps existing ones, and drops unchecked ones!
        $orderItem->assignees()->sync($request->input('user_ids'));

        return response()->json([
            'message' => 'Line item assignees synced successfully.',
            'data'    => $orderItem->fresh('assignees')->assignees
        ], 200);
    }

    /**
     * DELETE: Atomic removal of a single assigned user
     * DELETE /api/order-items/{order_item}/assignees/{userId}
     */
    public function destroy(OrderItem $orderItem, int $userId): JsonResponse
    {
        // detach() breaks the link in the pivot table without touching the actual User record
        $orderItem->assignees()->detach($userId);

        return response()->json([
            'message' => 'Assignee detached from line item successfully.',
            'data'    => $orderItem->fresh('assignees')->assignees
        ], 200);
    }
}