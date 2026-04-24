<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmployeeInvitationPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly User $invitedUser,
    ) {
    }

    public function handle(): void
    {
        // FCM dispatch — implemented in Phase 5.3.
    }
}
