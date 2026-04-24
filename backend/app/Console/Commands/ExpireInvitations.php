<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvitationStatus;
use App\Jobs\SendInvitationDecisionPush;
use App\Models\EmployeeInvitation;
use Illuminate\Console\Command;

class ExpireInvitations extends Command
{
    protected $signature = 'invitations:expire';
    protected $description = 'Mark pending employee invitations as expired and notify owners.';

    public function handle(): int
    {
        $expired = EmployeeInvitation::where('status', InvitationStatus::Pending)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $invitation) {
            $invitation->update(['status' => InvitationStatus::Expired]);
            SendInvitationDecisionPush::dispatch($invitation->fresh(), 'expired');
        }

        $this->info("Expired {$expired->count()} invitations.");
        return self::SUCCESS;
    }
}
