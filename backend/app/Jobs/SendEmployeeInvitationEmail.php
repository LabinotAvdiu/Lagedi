<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WelcomeEmployeeInvitationMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Send the invitation email to a user invited to join a salon as an employee.
 *
 * Dispatched immediately from MyCompanyController::inviteEmployee after
 * the company_user pivot row is created.
 *
 * The email is sent in the employee's own locale (fallback: fr).
 */
class SendEmployeeInvitationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User    $employee,
        public readonly User    $owner,
        public readonly Company $company,
    ) {
    }

    public function handle(): void
    {
        if (! $this->employee->email) {
            return;
        }

        Mail::to($this->employee->email)
            ->locale($this->employee->locale ?? config('app.fallback_locale', 'fr'))
            ->send(new WelcomeEmployeeInvitationMail(
                employee: $this->employee,
                owner:    $this->owner,
                company:  $this->company,
            ));
    }
}
