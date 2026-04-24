<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WelcomeClientMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Send the welcome email to a newly verified client.
 *
 * Dispatched at T+5 min after the OTP verification succeeds so the user
 * first sees the in-app confirmation screen before the email arrives.
 *
 * Only sends to role=user (not company/owner). The welcome owner email
 * is handled by SendWelcomeOwnerEmail.
 */
class SendWelcomeClientEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {
    }

    public function handle(): void
    {
        // Guard: only send to plain client accounts that have verified their email.
        if (! $this->user->hasVerifiedEmail()) {
            return;
        }

        Mail::to($this->user->email)
            ->locale($this->user->locale ?? config('app.fallback_locale', 'fr'))
            ->send(new WelcomeClientMail($this->user));
    }
}
