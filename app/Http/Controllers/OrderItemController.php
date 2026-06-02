<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItemStatus;
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
        // 1. Validate incoming menu selection and parameters
        $validated = $request->validate([
            'order_menu_item_id' => 'required|exists:order_menu_items,id',
            'due_date'           => 'nullable|date',
            'specifications'     => 'nullable|array',
        ]);

        $menuItem = OrderMenuItem::findOrFail($validated['order_menu_item_id']);

        // Dynamically find the default relational "Still In Cart" record row
        $stillInCartStatus = OrderItemStatus::where('name', 'Still In Cart')->first();

        // 2. Instantiate the item utilizing the strict foreign lookup ID mapping key
        $item = $order->orderItems()->create([
            'order_menu_item_id'   => $menuItem->id,
            'locked_price'         => $menuItem->default_price, // Lock current catalog price
            'order_item_status_id' => $stillInCartStatus->id,   // 🚀 Applies the required NOT NULL ID
            'due_date'             => $validated['due_date'] ?? null,
            'specifications'       => $validated['specifications'] ?? [],
        ]);

        // 3. Return payload wrapped natively inside your standard network envelope
        return response()->json([
            'message' => 'Line item successfully appended to cart.',
            'data'    => $item
        ], 201);
    }
}