<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate the incoming data from the React frontend
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // 2. Create the user in the AWS database
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 4. Generate the Sanctum API token
        $token = $user->createToken('api-token')->plainTextToken;

        // 5. Return the exact same JSON payload we use for Login
        return response()->json([
            'access_token' => $token,
            'user' => $user,
            'roles' => $user->getRoleNames(), // Spatie package method
            'permissions' => $user->getAllPermissions()->pluck('name'), // Spatie package method
        ], 201); // 201 means "Created"
    }
}