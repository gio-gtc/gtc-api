<?php

use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| These routes do not require a token. Anyone can attempt to log in.
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [RegisterController::class, 'store']);
/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
| These routes require a valid Sanctum token passed in the Authorization header.
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // A simple route to test if our token and session are working
    Route::get('/me', function (Request $request) {
        return response()->json([
            'message' => 'Token is valid!',
            'user' => $request->user(),
            'roles' => $request->user()->getRoleNames(),
            'permissions' => $request->user()->getAllPermissions()->pluck('name'),
        ]);
    });

    // In the future, your protected endpoints will go here:
    // Route::apiResource('tours', TourController::class);
    // Route::apiResource('venues', VenueController::class);
});
