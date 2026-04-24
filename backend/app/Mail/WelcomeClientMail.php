<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Termini Im — welcome email sent to a client after email verification.
 *
 * Dispatched asynchronously at T+5 min via SendWelcomeClientEmail job
 * so the user first sees the OTP confirmation screen, then receives
 * the welcome email when they go back to their inbox.
 */
class WelcomeClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@termini-im.com', 'Termini im'),
            replyTo: [
                new Address('support@termini-im.com', 'Support Termini im'),
            ],
            subject: __('emails.welcome_client.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_client',
            with: [
                'user' => $this->user,
            ],
        );
    }
}
