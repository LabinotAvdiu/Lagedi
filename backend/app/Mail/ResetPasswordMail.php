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
 * Termini Im — password reset.
 *
 * Sends a 6-character plain token. Matches verify-email flow so the
 * mobile app has a single "enter the code" UX for all security-sensitive
 * flows (no clickable links).
 */
class ResetPasswordMail extends Mailable
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
            subject: __('emails.reset.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset_password',
            with: [
                'user' => $this->user,
                'code' => $this->code,
            ],
        );
    }
}
