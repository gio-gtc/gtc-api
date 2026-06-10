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
    public function clientIndex(): JsonResponse
    {
        // Fetches users who do NOT belong to the internal GTC staff organization
        $clients = User::where('organisation_id', '!=', 1) 
            ->select('id', 'first_name', 'last_name', 'email', 'avatar', 'organisation_id')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'data' => $clients
        ], 200);
    }
}