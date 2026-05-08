<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        // 1. Get the currently logged-in user safely from the session/token
        /** @var \App\Models\User $user */
        $user = $request->user();

        // 2. Validate data, making sure to ignore their own ID for the email check
        $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,' . $user->id, 
            // 'organisation' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'job_title'    => 'nullable|string|max:255',
        ]);

        // 3. Update the user (only pulling the exact fields we allow)
        $user->update($request->only([
            'first_name', 'last_name', 'email', 'organization', 'phone_number', 'job_title'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user
        ]);
    }
}