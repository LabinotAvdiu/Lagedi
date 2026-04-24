<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EmployeeInvitationLinkMail;
use App\Models\Company;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmployeeInvitationLinkEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly Company $company,
        public readonly User $owner,
        public readonly string $plaintextToken,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->invitation->email)
            ->locale(config('app.fallback_locale', 'fr'))
            ->send(new EmployeeInvitationLinkMail(
                invitation:     $this->invitation,
                company:        $this->company,
                owner:          $this->owner,
                plaintextToken: $this->plaintextToken,
            ));
    }
}
