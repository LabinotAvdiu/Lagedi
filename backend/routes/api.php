<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes  — /api/auth/*
|--------------------------------------------------------------------------
*/

// Public routes — rate limited to 5 requests/min per IP on login
Route::prefix('auth')->group(function () {

    // Registration & login
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Token lifecycle
    Route::post('/refresh',  [AuthController::class, 'refresh']);

    // Social OAuth (stubs — implement server-side verification)
    Route::post('/google',   [AuthController::class, 'googleLogin']);
    Route::post('/facebook', [AuthController::class, 'facebookLogin']);

    // Password reset flow
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

    // Email verification
    Route::post('/verify-email',         [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification',  [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');

    // Protected auth routes (require valid Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',     [AuthController::class, 'logout']);
        Route::get('/profile',     [AuthController::class, 'profile']);
        Route::put('/profile',     [AuthController::class, 'updateProfile']);
    });
});

/*
|--------------------------------------------------------------------------
| Company routes  — /api/companies/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/companies',                    [CompanyController::class, 'index']);
    Route::get('/companies/{id}',               [CompanyController::class, 'show']);
    Route::get('/companies/{id}/employees',     [CompanyController::class, 'employees']);
    Route::get('/companies/{id}/slots',         [CompanyController::class, 'slots']);
});

/*
|--------------------------------------------------------------------------
| Booking routes  — /api/bookings/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
});
