<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WelcomeOwnerMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Send the welcome email to a newly verified salon owner.
 *
 * Dispatched at T+5 min after the OTP verification succeeds.
 * Only fires when the user holds the owner role on at least one company.
 */
class SendWelcomeOwnerEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {
    }

    public function handle(): void
    {
        if (! $this->user->hasVerifiedEmail()) {
            return;
        }

        // Resolve the company this owner just created.
        $pivot = CompanyUser::where('user_id', $this->user->id)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->first();

        if (! $pivot) {
            return;
        }

        $company = Company::find($pivot->company_id);

        if (! $company) {
            return;
        }

        Mail::to($this->user->email)
            ->locale($this->user->locale ?? config('app.fallback_locale', 'fr'))
            ->send(new WelcomeOwnerMail($this->user, $company));
    }
}
