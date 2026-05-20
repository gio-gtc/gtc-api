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
}