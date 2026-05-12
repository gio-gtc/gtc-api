<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserPasswordController extends Controller
{
    /**
     * Update the authenticated user's password.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'], 
            
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }
}
