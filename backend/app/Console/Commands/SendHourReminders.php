<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\CompanyRole;
use App\Jobs\SendAppointmentReminder2h;
use App\Jobs\SendAppointmentReminderOwner;
use App\Models\Appointment;
use App\Models\CompanyUser;
use Illuminate\Console\Command;

class SendHourReminders extends Command
{
    protected $signature   = 'appointments:send-hour-reminders';
    protected $description = 'Envoie les rappels 1h et 2h avant les rendez-vous (tourne toutes les 10 min).';

    public function handle(): int
    {
        $now = now();

        // -----------------------------------------------------------------------
        // Rappels 1h — reminder_owner pour owner + employé assigné
        // Fenêtre : starts_at ∈ [now + 55min, now + 65min]
        // -----------------------------------------------------------------------
        $oneHourAppts = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Pending->value])
            ->whereNotNull('date')
            ->whereRaw(
                "TIMESTAMP(date, start_time) BETWEEN ? AND ?",
                [
                    $now->copy()->addMinutes(55)->format('Y-m-d H:i:s'),
                    $now->copy()->addMinutes(65)->format('Y-m-d H:i:s'),
                ],
            )
            ->with(['companyUser.user'])
            ->get();

        foreach ($oneHourAppts as $appt) {
            // Employé assigné (si Type 1)
            if ($appt->company_user_id && $appt->companyUser?->user_id) {
                SendAppointmentReminderOwner::dispatch($appt, $appt->companyUser->user_id);
            }

            // Owner(s) du salon
            $owners = CompanyUser::where('company_id', $appt->company_id)
                ->where('role', CompanyRole::Owner->value)
                ->where('is_active', true)
                ->pluck('user_id');

            foreach ($owners as $ownerId) {
                SendAppointmentReminderOwner::dispatch($appt, $ownerId);
            }
        }

        $this->info("[1h] Processed {$oneHourAppts->count()} appointment(s).");

        // -----------------------------------------------------------------------
        // Rappels 2h — reminder_2h pour le client
        // Fenêtre : starts_at ∈ [now + 1h55, now + 2h05]
        // -----------------------------------------------------------------------
        $twoHourAppts = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Pending->value])
            ->whereNotNull('date')
            ->whereNotNull('user_id') // Walk-ins sans user_id sont ignorés
            ->whereRaw(
                "TIMESTAMP(date, start_time) BETWEEN ? AND ?",
                [
                    $now->copy()->addMinutes(115)->format('Y-m-d H:i:s'),
                    $now->copy()->addMinutes(125)->format('Y-m-d H:i:s'),
                ],
            )
            ->get();

        foreach ($twoHourAppts as $appt) {
            SendAppointmentReminder2h::dispatch($appt);
        }

        $this->info("[2h] Processed {$twoHourAppts->count()} appointment(s).");

        return self::SUCCESS;
    }
}
