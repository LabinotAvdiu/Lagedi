<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CompanyRole;
use App\Enums\NotificationType;
use App\Models\CompanyUser;
use App\Models\Review;
use App\Services\FcmService;
use App\Services\NotificationGate;
use App\Services\NotificationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * C8 — Notifie l'owner du salon quand un nouvel avis est publié.
 *
 * Deux variantes de titre selon la note :
 *   - 4-5 étoiles → ton positif ("Vlerësim i ri — 5 yje")
 *   - 1-3 étoiles → ton factuel ("Koment i ri nga {clientName}")
 */
class SendNewReviewNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [0, 30, 120];
    }

    public function uniqueId(): string
    {
        return 'review_' . $this->review->id;
    }

    public int $uniqueFor = 600; // 10 min dedup

    public function __construct(
        public readonly Review $review,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $review = $this->review->load(['user', 'company']);
        $type   = 'review.new';

        // Récupère l'owner du salon.
        $ownerPivot = CompanyUser::where('company_id', $review->company_id)
            ->where('role', CompanyRole::Owner->value)
            ->where('is_active', true)
            ->with(['user.devices'])
            ->first();

        if (! $ownerPivot || ! $ownerPivot->user) {
            return;
        }

        $owner = $ownerPivot->user;

        if ($owner->devices()->count() === 0) {
            return;
        }

        // D19 — Opt-out preference
        if (! $owner->isNotificationEnabled('push', NotificationType::NEW_REVIEW)) {
            Log::info('[FCM] review.new skipped — user opted out', ['owner_id' => $owner->id]);
            return;
        }

        // D21 — Quiet hours
        if (! NotificationGate::respectsQuietHours($owner, NotificationType::NEW_REVIEW)) {
            $delay = NotificationGate::nextAllowedAt($owner);
            Log::info('[FCM] review.new deferred — quiet hours', ['owner_id' => $owner->id, 'retry_at' => $delay]);
            self::dispatch($review)->delay($delay);
            return;
        }

        // D22 — Dedup 10 min
        $refKey = 'review_' . $review->id;
        if (NotificationGate::isDuplicate($owner, NotificationType::NEW_REVIEW, $refKey)) {
            Log::warning('[FCM] review.new blocked — duplicate', ['owner_id' => $owner->id, 'review_id' => $review->id]);
            return;
        }

        // D23 — Frequency cap
        if (NotificationGate::exceedsFrequencyCap($owner)) {
            Log::info('[FCM] review.new blocked — frequency cap', ['owner_id' => $owner->id]);
            return;
        }

        $clientName = $review->user
            ? trim($review->user->first_name . ' ' . $review->user->last_name)
            : 'Client';

        $rating = (int) $review->rating;

        // Tronque le commentaire à 80 caractères.
        $comment = $review->comment ?? '';
        $body80  = mb_strlen($comment) > 80
            ? mb_substr($comment, 0, 77) . '…'
            : $comment;

        // Choix du titre selon la note.
        $titleKey   = $rating >= 4 ? 'review_new_positive_title' : 'review_new_neutral_title';
        $titleParams = $rating >= 4
            ? ['rating' => $rating]
            : ['client_name' => $clientName];

        $fcm->sendToUser(
            user:       $owner,
            type:       $type,
            data:       [
                'type'      => $type,
                'reviewId'  => (string) $review->id,
                'companyId' => (string) $review->company_id,
                'rating'    => (string) $rating,
            ],
            titleKey:   $titleKey,
            bodyKey:    'review_new_body',
            bodyParams: array_merge($titleParams, ['comment' => $body80]),
        );

        Log::info('[FCM] review.new dispatched', [
            'review_id'  => $review->id,
            'company_id' => $review->company_id,
            'owner_id'   => $owner->id,
            'rating'     => $rating,
        ]);

        // D20 — Log
        NotificationLogger::log(
            user: $owner,
            channel: 'push',
            type: NotificationType::NEW_REVIEW,
            payload: ['rating' => $rating, 'clientName' => $clientName],
            refType: 'review',
            refId: $review->id,
        );
    }
}
