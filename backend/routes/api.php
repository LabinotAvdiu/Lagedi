<?php

declare(strict_types=1);

use App\Http\Controllers\AppointmentCancelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeInvitationController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MyCompanyController;
use App\Http\Controllers\MyCompanyGalleryController;
use App\Http\Controllers\MyCompanyReviewController;
use App\Http\Controllers\MyScheduleController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\NotificationsLogController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SupportTicketController;
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
    Route::post('/apple',    [AuthController::class, 'appleLogin']);

    // Password reset flow — throttled to block OTP brute-force attempts
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Email verification — throttled to block OTP brute-force attempts
    Route::post('/verify-email',         [AuthController::class, 'verifyEmail'])->middleware('throttle:5,1');
    Route::post('/resend-verification',  [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');

    // Protected auth routes (require valid Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',            [AuthController::class, 'logout']);
        Route::get('/profile',            [AuthController::class, 'profile']);
        Route::put('/profile',            [AuthController::class, 'updateProfile']);
        Route::put('/change-password',    [AuthController::class, 'changePassword']);
        // Social sign-up completion for company accounts.
        Route::post('/complete-company',  [AuthController::class, 'completeCompanySignup']);
        // Account deletion — required by Apple App Store (2022+) and Google Play (2024+).
        Route::delete('/account',         [AuthController::class, 'destroy']);
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
| Appointment routes  — /api/appointments/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Feature 1 — Annulation client
    Route::post('/appointments/{id}/cancel', AppointmentCancelController::class);

    // Feature 3 — Avis post-RDV (client)
    Route::post('/appointments/{id}/review', [ReviewController::class, 'store']);
    Route::get('/appointments/{id}/review',  [ReviewController::class, 'show']);
});

// Feature 3 — Avis publics par salon
Route::get('/companies/{id}/reviews', [ReviewController::class, 'indexByCompany']);

/*
|--------------------------------------------------------------------------
| Employee — "My Schedule" routes  — /api/my-schedule/*
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('my-schedule')->group(function () {
    Route::get('/',          [MyScheduleController::class, 'show']);
    Route::post('/walk-in',  [MyScheduleController::class, 'storeWalkIn']);
    Route::get('/upcoming',  [MyScheduleController::class, 'upcoming']);

    // Employee-scoped appointment mutation — cancel / no-show on the
    // employee's own bookings (mirrors the owner's /my-company/... endpoint
    // but guarded by company_user_id ownership).
    Route::put('/appointments/{id}/status',
        [MyScheduleController::class, 'updateMyAppointmentStatus']);

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
    // Legacy (owner/employee only preferences)
    Route::get('/notification-preferences',  [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences',  [NotificationPreferenceController::class, 'update']);

    // D19 — Granular notification preferences (all users, channel × type)
    Route::get('/notification-preferences/granular',   [NotificationPreferencesController::class, 'index']);
    Route::patch('/notification-preferences/granular', [NotificationPreferencesController::class, 'update']);

    // D20 — Notifications log inbox (read + mark-read)
    // NOTE: read-all must be declared before {id} to avoid GoRouter-style collision.
    Route::get('/notifications-log', [NotificationsLogController::class, 'index']);
    Route::patch('/notifications-log/read-all', [NotificationsLogController::class, 'markAllAsRead']);
    Route::patch('/notifications-log/{id}/read', [NotificationsLogController::class, 'markAsRead']);

    Route::post('/devices',                  [UserDeviceController::class, 'store']);
    Route::delete('/devices',                [UserDeviceController::class, 'destroy']);
    Route::post('/avatar',                   [UserAvatarController::class, 'store']);
    Route::delete('/avatar',                 [UserAvatarController::class, 'destroy']);

    // Employee invitations inbox — me-side
    Route::get('/invitations', [EmployeeInvitationController::class, 'mine']);
    Route::post('/invitations/{id}/accept', [EmployeeInvitationController::class, 'accept'])
        ->whereNumber('id');
    Route::post('/invitations/{id}/refuse', [EmployeeInvitationController::class, 'refuse'])
        ->whereNumber('id');
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

    // Employees — invite must come before /{id} to avoid route collision
    Route::get('/employees',               [MyCompanyController::class, 'listEmployees']);
    Route::post('/employees/invite',       [MyCompanyController::class, 'inviteEmployee']);
    Route::put('/employees/{id}',          [MyCompanyController::class, 'updateEmployee'])->whereNumber('id');
    Route::delete('/employees/{id}',       [MyCompanyController::class, 'destroyEmployee'])->whereNumber('id');

    // Invitation management (resend / revoke)
    Route::post('/employees/invitations/{id}/resend', [MyCompanyController::class, 'resendInvitation'])
        ->whereNumber('id')
        ->middleware('throttle:3,60');
    Route::delete('/employees/invitations/{id}', [MyCompanyController::class, 'revokeInvitation'])
        ->whereNumber('id');

    // Opening hours
    Route::get('/hours',  [MyCompanyController::class, 'listHours']);
    Route::put('/hours',  [MyCompanyController::class, 'updateHours']);

    // Booking settings (Type 2)
    Route::put('/booking-settings', [MyCompanyController::class, 'updateBookingSettings']);

    // Company breaks + days off (capacity mode — "Mon salon" settings)
    Route::get('/breaks',         [MyCompanyController::class, 'listBreaks']);
    Route::post('/breaks',        [MyCompanyController::class, 'storeBreak']);
    Route::put('/breaks/{id}',    [MyCompanyController::class, 'updateBreak']);
    Route::delete('/breaks/{id}', [MyCompanyController::class, 'destroyBreak']);
    Route::get('/days-off',        [MyCompanyController::class, 'listDaysOff']);
    Route::post('/days-off',       [MyCompanyController::class, 'storeDayOff']);
    Route::delete('/days-off/{id}', [MyCompanyController::class, 'destroyDayOff']);

    // Capacity overrides (Type 2) — DEPRECATED, routes kept for now for
    // back-compat but the feature is being removed from the UI.
    Route::get('/capacity-overrides',        [MyCompanyController::class, 'listCapacityOverrides']);
    Route::post('/capacity-overrides',       [MyCompanyController::class, 'storeCapacityOverride']);
    Route::put('/capacity-overrides/{id}',   [MyCompanyController::class, 'updateCapacityOverride']);
    Route::delete('/capacity-overrides/{id}', [MyCompanyController::class, 'destroyCapacityOverride']);

    // Walk-in (Type 2 only)
    Route::post('/walk-in', [MyCompanyController::class, 'storeWalkIn']);

    // Appointment routes (Type 2) — specific routes before /{id} to avoid collision
    Route::get('/appointments',                  [MyCompanyController::class, 'listAppointments']);
    Route::get('/appointments/pending',          [MyCompanyController::class, 'pendingAppointments']);
    Route::get('/planning-settings',             [MyCompanyController::class, 'planningSettings']);
    Route::get('/planning-overlays',             [MyCompanyController::class, 'planningOverlays']);
    Route::put('/appointments/{id}/status',      [MyCompanyController::class, 'updateAppointmentStatus']);

    // Gallery — reorder must come before /{id} to avoid route collision
    Route::get('/gallery',              [MyCompanyGalleryController::class, 'index']);
    Route::post('/gallery',             [MyCompanyGalleryController::class, 'store']);
    Route::post('/gallery/reorder',     [MyCompanyGalleryController::class, 'reorder']);
    Route::delete('/gallery/{id}',      [MyCompanyGalleryController::class, 'destroy']);

    // Feature 3 — Reviews (owner)
    Route::get('/reviews',              [MyCompanyReviewController::class, 'index']);
    Route::put('/reviews/{id}/hide',    [MyCompanyReviewController::class, 'hide']);
    Route::put('/reviews/{id}/unhide',  [MyCompanyReviewController::class, 'unhide']);
});

/*
|--------------------------------------------------------------------------
| Invitations — /api/invitations/{token}  (public)
|--------------------------------------------------------------------------
| Public endpoint: no auth required — the invited employee clicks the link
| in their email before they have an account.  Token is a 64-char hex string
| (sha256 of the raw random bytes) matched server-side to prevent enumeration.
*/
Route::get('/invitations/{token}', [EmployeeInvitationController::class, 'showByToken'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Support  — /api/support-tickets
|--------------------------------------------------------------------------
| Public endpoint: accessible to guests and authenticated users.
| Rate-limited to 3 tickets per minute per IP to deter abuse.
*/
Route::post('/support-tickets', [SupportTicketController::class, 'store'])
    ->middleware('throttle:3,1');

/*
|--------------------------------------------------------------------------
| E28 — Client error reporting  — /api/errors
|--------------------------------------------------------------------------
| POST : public (sans auth) — capture les crashs avant le login.
|         Rate-limité à 60/min par IP pour absorber les bursts légitimes
|         sans permettre d'inonder la table.
| GET  : auth:sanctum + gate owner — debug Labinot uniquement.
*/
Route::post('/errors', [ClientErrorController::class, 'store'])
    ->middleware('throttle:60,1');
Route::get('/errors', [ClientErrorController::class, 'index'])
    ->middleware('auth:sanctum');

