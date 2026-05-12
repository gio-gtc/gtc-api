<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class PasswordResetController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // We use Laravel's password broker to send the email
        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => bcrypt($password)
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }

    /**
     * Validate the token before showing the reset form.
     */
    public function validateToken(Request $request): JsonResponse
    {
        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $request->validate([
            'email' => 'required|email',
            'token' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if the user exists AND if the token is valid
        if (!$user || !$broker()->tokenExists($user, $request->token)) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        return response()->json(['valid' => true], 200);
    }
}