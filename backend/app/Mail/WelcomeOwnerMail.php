<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Termini Im — welcome email for salon owners after email verification.
 *
 * Dispatched asynchronously at T+5 min via SendWelcomeOwnerEmail job.
 * Contains a 5-step onboarding checklist to help the owner get their
 * salon fully set up on the platform.
 */
class WelcomeOwnerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User    $user,
        public readonly Company $company,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@termini-im.com', 'Termini im'),
            replyTo: [
                new Address('support@termini-im.com', 'Support Termini im'),
            ],
            subject: __('emails.welcome_owner.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_owner',
            with: [
                'user'    => $this->user,
                'company' => $this->company,
            ],
        );
    }
}
