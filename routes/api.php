<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AssigneeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserOnboardingController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\OrderMenuController;
use App\Models\Department;
use App\Models\OrganisationType;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

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
    Route::get('/me', function (Request $request) {
        return response()->json([
            'message' => 'Token is valid!',
            'user' => $request->user()->load('organisation:id,name'),
            'roles' => $request->user()->getRoleNames(),
            'permissions' => $request->user()->getAllPermissions()->pluck('name'),
        ]);
    });

    // Standard User updating their own profile
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/password', [UserPasswordController::class, 'update']);
    Route::post('/users/invite', [UserOnboardingController::class, 'invite']);
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/departments', function () {
        return response()->json(['departments' => Department::all()]);
    });

    Route::apiResource('organisations', OrganisationController::class);
    Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
    Route::post('/tours', [TourController::class, 'store'])->name('tours.store');
    Route::get('tours/{tour}/orders', [OrderController::class, 'getTourOrders']);
    Route::get('/venues', [VenueController::class, 'index'])->name('venues.index');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::post('/orders/{order}/submit', [OrderController::class, 'submit'])->name('orders.submit');
    Route::post('/orders/{order}/items', [OrderItemController::class, 'store'])->name('orders.items.store');
    Route::patch('order-items/{orderItem}', [OrderItemController::class, 'update'])->name('order-items.update');
    Route::delete('order-items/{orderItem}', [OrderItemController::class, 'destroy'])->name('order-items.destroy');
    Route::apiResource('order-items.assignees', AssigneeController::class)
        ->only(['index', 'store', 'destroy']);
    Route::get('/order-catalog-menu', [OrderMenuController::class, 'index'])->name('order-catalog.index');
    Route::get('/reference-data', function () {
        return response()->json([
            'org_types' => OrganisationType::all(),
            'countries' => Country::all(),
            'currency_codes' => Country::select('currency_code')
                ->distinct()
                ->whereNotNull('currency_code')
                ->orderBy('currency_code')
                ->pluck('currency_code'),
            'roles' => Role::where('name', '!=', 'Super Admin')->pluck('name')
        ]);
    });
});
