<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\MyCompanyController;
use App\Http\Controllers\MyScheduleController;
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
        Route::post('/logout',           [AuthController::class, 'logout']);
        Route::get('/profile',           [AuthController::class, 'profile']);
        Route::put('/profile',           [AuthController::class, 'updateProfile']);
        Route::put('/change-password',   [AuthController::class, 'changePassword']);
    });
});

/*
|--------------------------------------------------------------------------
| Company routes  — /api/companies/*
|--------------------------------------------------------------------------
*/
// Public — accessible without authentication (guest mode)
Route::get('/companies',                       [CompanyController::class, 'index']);
Route::get('/companies/{id}',                  [CompanyController::class, 'show']);
Route::get('/companies/{id}/employees',        [CompanyController::class, 'employees']);
Route::get('/companies/{id}/availability',     [CompanyController::class, 'availability']);
Route::get('/companies/{id}/slots',            [CompanyController::class, 'slots']);

/*
|--------------------------------------------------------------------------
| Booking routes  — /api/bookings/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings',  [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Employee — "My Schedule" routes  — /api/my-schedule/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('my-schedule')->group(function () {
    Route::get('/',          [MyScheduleController::class, 'show']);
    Route::post('/walk-in',  [MyScheduleController::class, 'storeWalkIn']);
    Route::get('/upcoming',  [MyScheduleController::class, 'upcoming']);

    // Work schedule settings
    Route::get('/settings',              [MyScheduleController::class, 'settings']);
    Route::put('/hours',                 [MyScheduleController::class, 'updateHours']);

    // Breaks
    Route::post('/breaks',               [MyScheduleController::class, 'storeBreak']);
    Route::delete('/breaks/{id}',        [MyScheduleController::class, 'destroyBreak']);

    // Days off
    Route::post('/days-off',             [MyScheduleController::class, 'storeDayOff']);
    Route::delete('/days-off/{id}',      [MyScheduleController::class, 'destroyDayOff']);
});

/*
|--------------------------------------------------------------------------
| Owner — "Mon Salon" routes  — /api/my-company/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('my-company')->group(function () {

    // Company profile
    Route::get('/',    [MyCompanyController::class, 'show']);
    Route::put('/',    [MyCompanyController::class, 'update']);

    // Service categories
    Route::get('/categories',       [MyCompanyController::class, 'listCategories']);
    Route::post('/categories',      [MyCompanyController::class, 'storeCategory']);
    Route::put('/categories/{id}',  [MyCompanyController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [MyCompanyController::class, 'destroyCategory']);

    // Services
    Route::post('/services',      [MyCompanyController::class, 'storeService']);
    Route::put('/services/{id}',  [MyCompanyController::class, 'updateService']);
    Route::delete('/services/{id}', [MyCompanyController::class, 'destroyService']);

    // Employees — invite and create must come before /{id} to avoid route collision
    Route::get('/employees',               [MyCompanyController::class, 'listEmployees']);
    Route::post('/employees/invite',       [MyCompanyController::class, 'inviteEmployee']);
    Route::post('/employees/create',       [MyCompanyController::class, 'createEmployee']);
    Route::put('/employees/{id}',          [MyCompanyController::class, 'updateEmployee']);
    Route::delete('/employees/{id}',       [MyCompanyController::class, 'destroyEmployee']);

    // Opening hours
    Route::get('/hours',  [MyCompanyController::class, 'listHours']);
    Route::put('/hours',  [MyCompanyController::class, 'updateHours']);
});
