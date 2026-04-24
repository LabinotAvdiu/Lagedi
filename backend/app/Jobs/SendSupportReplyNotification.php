<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * C13 — Notifie le demandeur quand un admin répond à son ticket support.
 *
 * TODO (trigger manquant) : Il n'existe pas encore d'endpoint admin pour répondre
 * à un ticket support (pas de SupportTicketController@reply ni de table
 * support_ticket_messages). Ce job est prêt à être câblé dès que le flow admin
 * sera implémenté. Dispatcher avec :
 *
 *   SendSupportReplyNotification::dispatch($ticket, $replyMessage)
 *
 * où `$replyMessage` est le texte de la réponse admin (max 255 char en pratique).
 *
 * Note : si le ticket a été soumis par un guest (user_id null), aucune push
 * ne sera envoyée (l'utilisateur n'a pas de compte).
 */
class SendSupportReplyNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        // Basé sur le ticket + timestamp pour permettre plusieurs réponses
        // successives sur le même ticket.
        return 'support_reply_' . $this->ticket->id . '_' . $this->replyTimestamp;
    }

    public int $uniqueFor = 600; // 10 min dedup

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly string $replyMessage,
        public readonly int $replyTimestamp = 0,
    ) {
        // Si replyTimestamp n'est pas fourni, on utilise le timestamp courant
        // pour garantir l'unicité entre plusieurs réponses.
        if ($this->replyTimestamp === 0) {
            // Note : on ne peut pas utiliser now() dans le constructeur car il
            // est aussi appelé lors de la désérialisation. On utilise time().
            $this->replyTimestamp = time();
        }
    }

    public function handle(FcmService $fcm): void
    {
        $ticket = $this->ticket->load(['user.devices']);
        $type   = 'support.reply';

        if (! $ticket->user_id) {
            // Ticket soumis par un guest — impossible d'envoyer une push.
            return;
        }

        /** @var User $user */
        $user = $ticket->user;

        if (! $user) {
            return;
        }

        if ($user->devices()->count() === 0) {
            return;
        }

        // Tronque la réponse à 80 caractères.
        $message = mb_strlen($this->replyMessage) > 80
            ? mb_substr($this->replyMessage, 0, 77) . '…'
            : $this->replyMessage;

        $fcm->sendToUser(
            user:       $user,
            type:       $type,
            data:       [
                'type'     => $type,
                'ticketId' => (string) $ticket->id,
            ],
            titleKey:   'support_reply_title',
            bodyKey:    'support_reply_body',
            bodyParams: ['message' => $message],
        );

        Log::info('[FCM] support.reply dispatched', [
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
        ]);
    }
}
