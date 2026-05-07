<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\UserInvitedMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserOnboardingController extends Controller
{
    // 1. Admin creates the user
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            // The 'unique:users' rule automatically blocks duplicates!
            'email'        => 'required|email|unique:users,email', 
            'organisation' => 'required|string|max:255',
        ]);

        // Create the user with a secure, random dummy password and NO verification timestamp
        $user = User::create([
            'first_name'   => $request->first_name,
            'last_name'    => $request->last_name,
            'email'        => $request->email,
            'organisation' => $request->organisation,
            'password'     => Hash::make(Str::random(32)),
        ]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        // Send the email
        Mail::to($user->email)->send(new UserInvitedMail($user, $token));

        return response()->json(['message' => 'User invited successfully.']);
    }

    // 2. User sets their password
    public function setPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Use Laravel's broker to validate the token and update the user
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    // Mark them as verified the moment they set their password!
                    'email_verified_at' => now(), 
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password successfully set.']);
        }

        // If token expired or invalid, return an error
        return response()->json(['message' => __($status)], 400);
    }
}