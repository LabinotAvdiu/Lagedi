<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Enums\NotificationType;
use App\Http\Controllers\UnsubscribeController;
use App\Models\User;
use Illuminate\Mail\Message;

/**
 * D24 — Ajoute les headers RFC 8058 List-Unsubscribe aux emails marketing.
 *
 * Usage dans un Mailable :
 *
 *   use HasUnsubscribeHeader;
 *
 *   public function build(): static
 *   {
 *       return $this->view('...')
 *           ->withUnsubscribeHeader($this->user, NotificationType::MARKETING);
 *   }
 *
 * Headers ajoutés :
 *   List-Unsubscribe: <{signed_url}>, <mailto:unsub@termini.im?subject=unsub>
 *   List-Unsubscribe-Post: List-Unsubscribe=One-Click
 */
trait HasUnsubscribeHeader
{
    /**
     * Ajoute les headers List-Unsubscribe + List-Unsubscribe-Post au message.
     *
     * @param User   $user    Destinataire
     * @param string $type    NotificationType::* (ex: NotificationType::MARKETING)
     */
    protected function withUnsubscribeHeader(User $user, string $type): static
    {
        $url = UnsubscribeController::signedUrl($user, $type, 'email');

        return $this->withSymfonyMessage(function (Message $message) use ($url) {
            $message->getHeaders()
                ->addTextHeader(
                    'List-Unsubscribe',
                    "<{$url}>, <mailto:unsub@termini.im?subject=unsubscribe>"
                )
                ->addTextHeader(
                    'List-Unsubscribe-Post',
                    'List-Unsubscribe=One-Click'
                );
        });
    }

    /**
     * Génère l'URL de désabonnement pour l'inclure dans le footer du template blade.
     *
     * @param User   $user
     * @param string $type
     */
    protected function unsubscribeUrl(User $user, string $type): string
    {
        return UnsubscribeController::signedUrl($user, $type, 'email');
    }
}
