<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\SendReviewRequestNotification;
use App\Models\Appointment;
use App\Models\AppointmentNotificationSent;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentConfirmedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $appt   = $this->appointment->load(['service', 'user', 'company']);
        $type   = 'appointment.confirmed';
        $client = $appt->user;

        // Walk-in sans user_id associé — pas de notification client.
        if (! $client) {
            return;
        }

        if (AppointmentNotificationSent::alreadySent($appt->id, $client->id, $type)) {
            return;
        }

        $time = substr((string) $appt->start_time, 0, 5);

        $fcm->sendToUser(
            user:       $client,
            type:       $type,
            data:       [
                'type'          => $type,
                'appointmentId' => (string) $appt->id,
                'companyId'     => (string) $appt->company_id,
            ],
            titleKey:   'appointment_confirmed_title',
            bodyKey:    'appointment_confirmed_body',
            bodyParams: [
                'service_name' => $appt->service?->name ?? '',
                'time'         => $time,
            ],
        );

        AppointmentNotificationSent::markSent($appt->id, $client->id, $type);

        // C12 — Planifie la demande d'avis pour J+1 à 18h (Europe/Tirane par défaut).
        // Le job vérifie lui-même au moment de l'exécution si le RDV est toujours actif.
        //
        // On passe *un int de secondes* à ->delay() plutôt qu'un Carbon :
        // avec PHP 8.4 + Carbon 2.x, la sérialisation d'un Carbon en queue
        // sync récurse dans __debugInfo (UnknownGetterException lève une
        // exception dont le message reformate via le même get(), infinite
        // loop). Les secondes sont identiques en pratique et ne recursent pas.
        // Ref : https://github.com/briannesbitt/Carbon/issues/2923
        $timezone = 'Europe/Tirane';
        $reviewRequestAt = Carbon::now($timezone)
            ->addDay()
            ->setHour(18)
            ->setMinute(0)
            ->setSecond(0);

        // Fresh instance (no eager-loaded relations) so the serialized payload
        // stays minimal — the child job reloads what it needs in handle().
        SendReviewRequestNotification::dispatch($appt->fresh())->delay($reviewRequestAt);
    }
}
