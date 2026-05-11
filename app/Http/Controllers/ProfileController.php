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
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,' . $user->id, 
            'organisation_id' => 'nullable|integer',
            'phone_number' => 'nullable|string|max:50',
            'job_title'    => 'nullable|string|max:255',
        ]);

        $user->update($request->only([
            'first_name', 'last_name', 'email', 'organisation_id', 'phone_number', 'job_title'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user
        ]);
    }
}