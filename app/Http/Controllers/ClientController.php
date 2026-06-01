<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a performance-optimized, searchable listing of client users.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        // 1. Initialize query excluding internal GTC staff (ID 1)
        $query = User::where('organisation_id', '!=', 1)
            ->with('organisation:id,name');

        // 2. Enforce minimum string length limits
        if (!empty($search)) {
            if (strlen($search) < 2) {
                return response()->json(['data' => []], 200);
            }

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhereHas('organisation', function ($orgQuery) use ($search) {
                        $orgQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 3. Keep the network payload lean by selecting split name columns
        $clients = $query->orderBy('first_name', 'asc')
            ->get(['id', 'first_name', 'last_name', 'email', 'organisation_id']);

        return response()->json([
            'data' => $clients
        ], 200);
    }
}