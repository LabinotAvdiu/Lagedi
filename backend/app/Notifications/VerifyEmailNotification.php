<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Custom email verification notification.
 *
 * Instead of the signed URL approach (which requires a browser),
 * we send a short numeric/alphanumeric token that the mobile app
 * can display in an "Enter your code" screen.
 *
 * The token is stored (hashed) in email_verification_tokens.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $plainToken,
    ) {}

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email — Takimi IM')
            ->greeting("Hello {$notifiable->first_name}!")
            ->line('Please use the code below to verify your email address.')
            ->line("**{$this->plainToken}**")
            ->line('This code expires in 24 hours.')
            ->line('If you did not create an account, no further action is required.');
    }
}
