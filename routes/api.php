<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserOnboardingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\OrganisationController;
use App\Models\OrganisationType;
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
Route::post('/users/set-password', [UserOnboardingController::class, 'setPassword']);
Route::get('/profile/verify-email/{user}', [\App\Http\Controllers\ProfileController::class, 'verifyPendingEmail'])
    ->name('profile.verify-email');

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

    // Standard User updating their own profile
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/password', [UserPasswordController::class, 'update']);
    Route::post('/users/invite', [UserOnboardingController::class, 'invite']);
    Route::get('/roles', [RoleController::class, 'index']);

    Route::apiResource('organisations', OrganisationController::class);
    Route::get('/organisation-types', function () {
        return response()->json(['types' => OrganisationType::all()]);
    });

    // In the future, your protected endpoints will go here:
    // Route::apiResource('tours', TourController::class);
    // Route::apiResource('venues', VenueController::class);
});
