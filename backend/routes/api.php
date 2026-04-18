<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MyCompanyController;
use App\Http\Controllers\MyCompanyGalleryController;
use App\Http\Controllers\MyScheduleController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\UserAvatarController;
use App\Http\Controllers\UserDeviceController;
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

    // Email availability check (used during signup form on-blur)
    Route::get('/check-email', [AuthController::class, 'checkEmail'])->middleware('throttle:30,1');

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
| Favorites routes  — /api/companies/{company}/favorite
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/companies/{company}/favorite',   [FavoriteController::class, 'store']);
    Route::delete('/companies/{company}/favorite', [FavoriteController::class, 'destroy']);
});

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
/*
|--------------------------------------------------------------------------
| Me — notifications & devices  — /api/me/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('me')->group(function () {
    Route::get('/notification-preferences',  [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences',  [NotificationPreferenceController::class, 'update']);
    Route::post('/devices',                  [UserDeviceController::class, 'store']);
    Route::delete('/devices',                [UserDeviceController::class, 'destroy']);
    Route::post('/avatar',                   [UserAvatarController::class, 'store']);
    Route::delete('/avatar',                 [UserAvatarController::class, 'destroy']);
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

    // Booking settings (Type 2)
    Route::put('/booking-settings', [MyCompanyController::class, 'updateBookingSettings']);

    // Company breaks (Type 2)
    Route::get('/breaks',        [MyCompanyController::class, 'listBreaks']);
    Route::post('/breaks',       [MyCompanyController::class, 'storeBreak']);
    Route::put('/breaks/{id}',   [MyCompanyController::class, 'updateBreak']);
    Route::delete('/breaks/{id}', [MyCompanyController::class, 'destroyBreak']);

    // Capacity overrides (Type 2)
    Route::get('/capacity-overrides',        [MyCompanyController::class, 'listCapacityOverrides']);
    Route::post('/capacity-overrides',       [MyCompanyController::class, 'storeCapacityOverride']);
    Route::put('/capacity-overrides/{id}',   [MyCompanyController::class, 'updateCapacityOverride']);
    Route::delete('/capacity-overrides/{id}', [MyCompanyController::class, 'destroyCapacityOverride']);

    // Walk-in (Type 2 only)
    Route::post('/walk-in', [MyCompanyController::class, 'storeWalkIn']);

    // Appointment routes (Type 2) — specific routes before /{id} to avoid collision
    Route::get('/appointments',                  [MyCompanyController::class, 'listAppointments']);
    Route::get('/appointments/pending',          [MyCompanyController::class, 'pendingAppointments']);
    Route::put('/appointments/{id}/status',      [MyCompanyController::class, 'updateAppointmentStatus']);

    // Gallery — reorder must come before /{id} to avoid route collision
    Route::get('/gallery',              [MyCompanyGalleryController::class, 'index']);
    Route::post('/gallery',             [MyCompanyGalleryController::class, 'store']);
    Route::post('/gallery/reorder',     [MyCompanyGalleryController::class, 'reorder']);
    Route::delete('/gallery/{id}',      [MyCompanyGalleryController::class, 'destroy']);
});
