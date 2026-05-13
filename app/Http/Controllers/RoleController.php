<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Fetch a list of roles available for assignment.
     */
    public function index(Request $request): JsonResponse
    {
        // Fetch all roles EXCEPT 'Super Admin'
        // pluck('name') returns a simple array: ['admin', 'Designer', 'Client']
        $roles = Role::where('name', '!=', 'Super Admin')->pluck('name');

        return response()->json([
            'roles' => $roles
        ]);
    }
}