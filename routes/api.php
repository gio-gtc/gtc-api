<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\AccessRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| These routes do not require a token.
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::get('/validate-reset-token', [PasswordResetController::class, 'validateToken']);
Route::post('/request-access', [AccessRequestController::class, 'store']);

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

    Route::put('/user/password', [UserPasswordController::class, 'update']);

    // In the future, your protected endpoints will go here:
    // Route::apiResource('tours', TourController::class);
    // Route::apiResource('venues', VenueController::class);
});
