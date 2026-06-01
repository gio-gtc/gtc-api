<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Add a creative deliverable item to an open order cart.
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'order_menu_item_id' => 'required|exists:order_menu_items,id',
            'specifications'     => 'nullable|array',
            'assignee_ids'       => 'nullable|array',
            'due_date'           => 'required|date|after_or_equal:today',
            'assignee_ids.*'     => 'exists:users,id',
        ]);

        $menuItem = OrderMenuItem::findOrFail($validated['order_menu_item_id']);

        $lockedPrice = $menuItem->default_price;

        // Create the initial line-item assignment block in cart status
        $orderItem = OrderItem::create([
            'order_id'           => $order->id,
            'order_menu_item_id' => $menuItem->id,
            'locked_price'       => $lockedPrice, // Aligned with the database column modification name
            'due_date'           => $validated['due_date'],
            'specifications'     => $validated['specifications'] ?? [],
            'status'             => 'Still In Cart',
        ]);

        if (!empty($validated['assignee_ids'])) {
            $orderItem->assignees()->sync($validated['assignee_ids']);
        }

        // Return wrapped response object structure
        return response()->json([
            'message' => 'Item added to order successfully.',
            'data'    => $orderItem->load('assignees')
        ], 201);
    }
}