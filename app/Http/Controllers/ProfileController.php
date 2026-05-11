<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyPendingEmail;

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
            
            $verifyUrl = URL::temporarySignedRoute(
                'profile.verify-email',
                now()->addMinutes(60), // Expires in 1 hour
                ['user' => $user->id]
            );

            Mail::to($request->email)->send(new VerifyPendingEmail($verifyUrl));
        }

        $user->update($dataToUpdate);

        return response()->json([
            'message' => $message,
            'user'    => $user
        ]);
    }

    /**
     * Handle the secure link clicked from the user's email inbox.
     */
    public function verifyPendingEmail(Request $request, User $user)
    {
        // 1. Check if the URL was tampered with or expired
        if (! $request->hasValidSignature()) {
            // In a real app, you might redirect to a dedicated frontend error page
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        // 2. If they have a pending email, officially swap it!
        if ($user->pending_email) {
            $user->update([
                'email'         => $user->pending_email,
                'pending_email' => null,
            ]);
        }

        // 3. Redirect them back to the React Proxy so they can see success
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8000'); 
        
        return redirect($frontendUrl . '/settings?email_verified=true');
    }
}