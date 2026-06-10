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
     * Fetch all external clients for selection pickers and dashboard views
     */
    public function clientIndex(Request $request): JsonResponse
    {
        // 1. Initialize the base query for external organizations (non-GTC staff)
        $query = User::where('organisation_id', '!=', 1)
            ->select('id', 'first_name', 'last_name', 'email', 'avatar', 'organisation_id');

        // 2. Check if a search query is actively provided
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');

            // Enforce the boundary constraint ONLY when a search is executing
            if (mb_strlen($searchTerm) < 2) {
                return response()->json([
                    'message' => 'The search term must be at least 2 characters.',
                    'errors'  => ['q' => ['The search term must be at least 2 characters.']]
                ], 422);
            }

            // Apply search filters cleanly across names and emails
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('first_name', 'like', "%{$searchTerm}%")
                    ->orWhere('last_name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        // 3. Execute the payload compilation
        $clients = $query->orderBy('first_name', 'asc')->get();

        return response()->json([
            'data' => $clients
        ], 200);
    }
}