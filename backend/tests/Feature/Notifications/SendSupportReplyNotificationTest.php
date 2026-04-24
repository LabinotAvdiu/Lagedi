<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Jobs\SendSupportReplyNotification;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendSupportReplyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function testJobCanBeDispatchedForTicketWithUser(): void
    {
        Queue::fake();

        $user = User::factory()->create(['locale' => 'fr']);

        $ticket = SupportTicket::create([
            'user_id'     => $user->id,
            'first_name'  => 'Jean',
            'phone'       => '+33600000000',
            'email'       => $user->email,
            'message'     => 'Bonjour, j\'ai un problème avec mon compte.',
            'source_page' => 'settings',
            'status'      => 'new',
        ]);

        SendSupportReplyNotification::dispatch($ticket, 'Bonjour Jean, votre problème a été résolu.', time());

        Queue::assertPushed(
            SendSupportReplyNotification::class,
            fn ($job) => $job->ticket->id === $ticket->id
                && str_starts_with($job->replyMessage, 'Bonjour Jean'),
        );
    }

    public function testJobSkipsGuestTicket(): void
    {
        // Un ticket sans user_id (soumis par guest) ne doit pas envoyer de push.
        Queue::fake();

        $ticket = SupportTicket::create([
            'user_id'     => null,
            'first_name'  => 'Anonymous',
            'phone'       => '+38344000000',
            'message'     => 'Problème technique.',
            'source_page' => 'login',
            'status'      => 'new',
        ]);

        $fcmMock = $this->createMock(\App\Services\FcmService::class);
        $fcmMock->expects($this->never())->method('sendToUser');

        (new SendSupportReplyNotification($ticket, 'Bonjour, merci de votre retour.', time()))->handle($fcmMock);
    }
}
