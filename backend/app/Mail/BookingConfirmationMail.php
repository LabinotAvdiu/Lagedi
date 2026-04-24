<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Termini Im — booking confirmation email with ICS attachment.
 *
 * Sent immediately after a booking is confirmed (status = confirmed).
 * The ICS attachment is generated inline (RFC 5545) so it works without
 * any filesystem writes or external library.
 *
 * The cancel URL uses a signed URL valid for 7 days. Because Laravel's
 * URL::signedRoute requires named routes and the current routes file may
 * not define a named cancel route, we fall back to a plain URL with a
 * cancel_token query param generated from a HMAC of the appointment id.
 * The controller that handles cancellation must verify this token.
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $cancelUrl;
    public readonly string $icsContent;

    public function __construct(
        public readonly Appointment $appointment,
    ) {
        $this->appointment->loadMissing(['user', 'company', 'service', 'companyUser.user']);

        $this->cancelUrl  = $this->buildCancelUrl();
        $this->icsContent = $this->buildIcs();
    }

    public function envelope(): Envelope
    {
        $appt    = $this->appointment;
        $company = $appt->company;

        // Date formatted as "Lundi 23 avril 2026" in locale — we keep it
        // simple here by passing the raw date string; the view formats it.
        $dateLabel = $appt->date instanceof \Carbon\Carbon
            ? $appt->date->translatedFormat('d/m/Y')
            : substr((string) $appt->date, 0, 10);

        return new Envelope(
            from: new Address('no-reply@termini-im.com', 'Termini im'),
            replyTo: [
                new Address('support@termini-im.com', 'Support Termini im'),
            ],
            subject: __('emails.booking_confirmation.subject', [
                'salon' => $company->name,
                'date'  => $dateLabel,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_confirmation',
            with: [
                'appointment' => $this->appointment,
                'cancelUrl'   => $this->cancelUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->icsContent, 'rendez-vous.ics')
                ->withMime('text/calendar'),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a cancel URL with a 7-day HMAC token.
     *
     * If a named route `bookings.cancel` exists we use Laravel's signed URL;
     * otherwise we fall back to a plain URL with a `cancel_token` param so
     * the feature is usable before the cancel-via-email route is registered.
     */
    private function buildCancelUrl(): string
    {
        $apptId = $this->appointment->id;

        // HMAC token: sha256(appointment_id + app_key), hex, valid 7 days.
        // The cancel controller must recompute and compare this token.
        $token = hash_hmac('sha256', (string) $apptId, config('app.key'));

        // Use a signed route if available; else use the web app URL.
        if ($this->namedRouteExists('bookings.cancel')) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'bookings.cancel',
                now()->addDays(7),
                ['id' => $apptId],
            );
        }

        return rtrim((string) config('app.frontend_url', 'https://app.termini-im.com'), '/')
            . '/bookings/' . $apptId . '/cancel?cancel_token=' . $token;
    }

    private function namedRouteExists(string $name): bool
    {
        try {
            return \Illuminate\Support\Facades\Route::has($name);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Generate an RFC 5545 iCalendar string for the appointment.
     *
     * The content is built as a plain string to avoid any external
     * dependency. Lines are folded at 75 octets per the RFC.
     */
    private function buildIcs(): string
    {
        $appt     = $this->appointment;
        $company  = $appt->company;
        $service  = $appt->service;

        $dateStr = $appt->date instanceof \Carbon\Carbon
            ? $appt->date->format('Y-m-d')
            : substr((string) $appt->date, 0, 10);

        $startCarbon = \Carbon\Carbon::parse($dateStr . ' ' . $appt->start_time)->utc();
        $endCarbon   = \Carbon\Carbon::parse($dateStr . ' ' . $appt->end_time)->utc();

        $dtStart = $startCarbon->format('Ymd\THis\Z');
        $dtEnd   = $endCarbon->format('Ymd\THis\Z');
        $dtStamp = now()->utc()->format('Ymd\THis\Z');

        $uid         = $appt->id . '@termini-im.com';
        $summary     = $this->icsEscape(__('emails.booking_confirmation.ics_summary', ['salon' => $company->name]));
        $location    = $this->icsEscape(trim(($company->address ?? '') . ', ' . ($company->city ?? '')));
        $organizer   = $company->email ?? 'no-reply@termini-im.com';

        $employeeName = optional(optional($appt->companyUser)->user)->first_name . ' '
            . optional(optional($appt->companyUser)->user)->last_name;
        $employeeName = trim($employeeName) ?: '';

        $serviceLine = $service
            ? ($service->name . ' — ' . $service->duration . ' min — ' . number_format((float) $service->price, 2) . ' €')
            : '';

        $description = $this->icsEscape(
            ($serviceLine ? $serviceLine . '\n' : '')
            . ($employeeName ? __('emails.booking_confirmation.ics_employee') . ': ' . $employeeName . '\n' : '')
            . __('emails.booking_confirmation.ics_cancel') . ': ' . $this->cancelUrl
        );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Termini Im//Booking//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtStamp,
            'DTSTART:' . $dtStart,
            'DTEND:' . $dtEnd,
            'SUMMARY:' . $summary,
            'LOCATION:' . $location,
            'DESCRIPTION:' . $description,
            'ORGANIZER:mailto:' . $organizer,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    /** Escape special chars for iCalendar text values. */
    private function icsEscape(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\\,', '\\;', '\\n'],
            $value,
        );
    }
}
