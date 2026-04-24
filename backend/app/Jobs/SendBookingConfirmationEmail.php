<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Send the booking confirmation email (with ICS attachment) to the client.
 *
 * Dispatched immediately after an appointment reaches the `confirmed` status,
 * both from BookingController::store (employee_based mode) and from
 * MyCompanyController::updateAppointmentStatus (capacity mode, pending→confirmed).
 *
 * Walk-in appointments (user_id = null) are skipped — there is no email to send.
 */
class SendBookingConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
    ) {
    }

    public function handle(): void
    {
        $appt = $this->appointment->load(['user', 'company', 'service', 'companyUser.user']);

        // Walk-in or appointment without a registered user — no email.
        if (! $appt->user || ! $appt->user->email) {
            return;
        }

        Mail::to($appt->user->email)
            ->locale($appt->user->locale ?? config('app.fallback_locale', 'fr'))
            ->send(new BookingConfirmationMail($appt));
    }
}
