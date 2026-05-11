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
            'department'      => 'nullable|string|max:255',
        ]);

        $dataToUpdate = $request->only([
            'first_name', 'last_name', 'organisation_id', 'phone_number', 'job_title', 'department'
        ]);

        $message = 'Profile updated successfully.';

        if ($request->email !== $user->email) {
            $dataToUpdate['pending_email'] = $request->email;
            $message = 'Profile updated. Please check your new inbox to verify your updated email address.';
            
            // TODO: We will trigger the email notification here in Phase 2
        }

        $user->update($dataToUpdate);

        return response()->json([
            'message' => $message,
            'user'    => $user
        ]);
    }
}