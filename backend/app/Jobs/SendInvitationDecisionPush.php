<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployeeInvitation;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvitationDecisionPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly string $decision, // 'accepted' | 'refused' | 'expired'
    ) {}

    public function handle(FcmService $fcm): void
    {
        $invitation = $this->invitation->loadMissing('invitedBy');
        $owner = $invitation->invitedBy;

        if (! $owner) {
            return;
        }

        $fcm->sendToUser(
            user: $owner,
            type: "invitation.{$this->decision}",
            data: [
                'type' => "invitation.{$this->decision}",
                'invitationId' => (string) $invitation->id,
                'companyId' => (string) $invitation->company_id,
            ],
            titleKey: "invitation_{$this->decision}_title",
            bodyKey: "invitation_{$this->decision}_body",
            bodyParams: [
                'email' => $invitation->email,
            ],
        );
    }
}
