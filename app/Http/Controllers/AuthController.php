<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Find the user by email
        $user = User::where('email', $request->email)->first();

        // 3. Check if user exists and password is correct
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401); // 401 Unauthorized
        }

        // 4. Generate the Sanctum Token
        $token = $user->createToken('frontend-api-token')->plainTextToken;

        // 5. Return the Token, User Data, Roles, and Permissions
        return response()->json([
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'organisation' => $user->organisation ? [
                    'id' => $user->organisation->id,
                    'name' => $user->organisation->name,
                ] : null,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'phone_number' => $user->phone_number,
                'notes' => $user->notes,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}