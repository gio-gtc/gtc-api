<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Display a listing of all parent orders with their core relationships.
     */
    public function index(): JsonResponse
    {
        $orders = Order::with(['venue', 'tour', 'orderItems.orderMenuItem.category'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders
        ], 200);
    }

    /**
     * Create a brand-new master order shell.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validate the core project requirements
        $validated = $request->validate([
            'tour_id'                 => 'required|exists:tours,id',
            'venue_id'                => 'required|exists:venues,id',
            'due_date'                => 'required|date|after_or_equal:today',
            'local_deliverable_email' => 'required|email',
        ]);

        // 2. Create the parent record container
        $order = Order::create([
            'tour_id'                 => $validated['tour_id'],
            'venue_id'                => $validated['venue_id'],
            'due_date'                => $validated['due_date'],
            'local_deliverable_email' => $validated['local_deliverable_email'],
            'ordered_by_id'           => Auth::id(), // Captured securely from their Sanctum login token!
            'status'                  => 'New Order',  // Default initial tracking state
        ]);

        return response()->json([
            'message' => 'Order created successfully.',
            'order'   => $order
        ], 201);
    }
}