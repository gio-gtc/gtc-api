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
     * Add a creative deliverable item to an open order.
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'order_menu_item_id' => 'required|exists:order_menu_items,id',
            'specifications'  => 'nullable|array',
            'assignee_ids'    => 'nullable|array',
            'assignee_ids.*'  => 'exists:users,id',
        ]);

        $menuItem = OrderMenuItem::find($validated['order_menu_item_id']);
        $tour = $order->tour;

        $priceLocked = match ($menuItem->name) {
            'TV First Cut'             => $tour->tv_first_cut ?? $menuItem->default_price,
            'TV Second Cut'             => $tour->tv_second_cut ?? $menuItem->default_price,
            'Radio Single Commercial'       => $tour->radio_single_duration ?? $menuItem->default_price,
            'Radio Dual Commercial'         => $tour->radio_dual_duration ?? $menuItem->default_price,
            'Billboard Master Layout', 
            'Digital Poster Kit'            => $tour->key_art ?? $menuItem->default_price,
            default                         => $menuItem->default_price,
        };

        $orderItem = OrderItem::create([
            'order_id'              => $order->id,
            'order_menu_item_id'    => $menuItem->id,
            'price_locked'          => $priceLocked,
            'due_date'              => $validated['due_date'],
            'specifications'        => $validated['specifications'] ?? [],
            'status'                => 'New',
        ]);

        if (!empty($validated['assignee_ids'])) {
            $orderItem->assignees()->sync($validated['assignee_ids']);
        }

        return response()->json([
            'message' => 'Item added to order successfully.',
            'item'    => $orderItem->load('assignees')
        ], 201);
    }
}