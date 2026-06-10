<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of all users with their nested organizational types.
     */
    public function index(): JsonResponse
    {
        $users = User::with(['organisation.types'])->get();

        return response()->json([
            'users' => $users
        ], 200);
    }

    /**
     * Fetch all internal GTC staff members for assignment pickers
     */
    public function staffIndex(): JsonResponse
    {
        $staff = User::where('organisation_id', 1)
            ->select('id', 'first_name', 'last_name', 'email', 'avatar')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'data' => $staff
        ], 200);
    }

    /**
     * Fetch external clients (Supports full list, search, and order existence filtering)
     * GET /api/clients
     */
    public function clientIndex(Request $request): JsonResponse
    {
        // 1. Initialize the base query for external clients
        $query = User::where('organisation_id', '!=', 1)
            ->select('id', 'first_name', 'last_name', 'email', 'avatar', 'organisation_id');

        // 2. NEW: Filter out clients who don't have any orders
        // This assumes your User model has an 'orders' relationship defined
        if ($request->boolean('has_orders')) {
            $query->whereHas('orders');
        }

        // 3. Keep your existing type-ahead search logic intact
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');

            if (mb_strlen($searchTerm) < 2) {
                return response()->json([
                    'message' => 'The search term must be at least 2 characters.',
                    'errors'  => ['q' => ['The search term must be at least 2 characters.']]
                ], 422);
            }

            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        $clients = $query->orderBy('first_name', 'asc')->get();

        return response()->json([
            'data' => $clients
        ], 200);
    }
}