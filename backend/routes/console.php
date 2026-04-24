<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Push notification scheduled commands
|--------------------------------------------------------------------------
*/

// Rappels 1h (reminder_owner) et 2h (reminder_2h) — toutes les 10 minutes.
Schedule::command('appointments:send-hour-reminders')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Rappels du soir pour le lendemain — tous les jours à 20h00.
Schedule::command('appointments:send-evening-reminders')
    ->dailyAt('20:00')
    ->withoutOverlapping();

// Expiration nocturne des invitations en attente — tous les jours à 03h00.
Schedule::command(\App\Console\Commands\ExpireInvitations::class)
    ->dailyAt('03:00')
    ->withoutOverlapping();

