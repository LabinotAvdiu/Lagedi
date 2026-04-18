<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentReminderEvening;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendEveningReminders extends Command
{
    protected $signature   = 'appointments:send-evening-reminders';
    protected $description = 'Envoie les rappels du soir pour tous les rendez-vous de demain (tourne à 20h00).';

    public function handle(): int
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        $appointments = Appointment::query()
            ->whereDate('date', $tomorrow)
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Pending->value])
            ->whereNotNull('user_id') // Walk-ins sans compte utilisateur ignorés
            ->get();

        foreach ($appointments as $appt) {
            SendAppointmentReminderEvening::dispatch($appt);
        }

        $this->info("Evening reminders dispatched for {$appointments->count()} appointment(s) on {$tomorrow}.");

        return self::SUCCESS;
    }
}
