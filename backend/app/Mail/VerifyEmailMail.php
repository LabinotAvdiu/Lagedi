<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Termini Im — account email verification.
 *
 * Sends a 6-character plain token that the user enters in the mobile app
 * (or web) to prove ownership of the email address. Matches the locale of
 * the recipient thanks to ->locale() set at the dispatch site.
 */
class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.verify.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify_email',
            with: [
                'user' => $this->user,
                'code' => $this->code,
            ],
        );
    }
}
