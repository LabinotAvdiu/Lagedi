<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Appointment;
use App\Observers\AppointmentObserver;
use App\Services\FcmService;
use Illuminate\Support\ServiceProvider;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind FcmService en singleton.
        // Si les credentials Firebase ne sont pas configurés, on passe null
        // et FcmService bascule en mode log-only (dev-friendly).
        $this->app->singleton(FcmService::class, function () {
            try {
                $messaging = Firebase::project('app')->messaging();

                return new FcmService($messaging);
            } catch (\Throwable) {
                return new FcmService(null);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum access tokens expire after 15 minutes.
        // Refresh tokens are handled separately in the RefreshToken model (90 days).
        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);

        // Observer push notifications.
        Appointment::observe(AppointmentObserver::class);
    }
}
