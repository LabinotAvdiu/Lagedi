<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Avis client — shape unifié pour listes publiques et vues owner.
 *
 * Relations requises : user (lazy-load acceptable car utilisé en contexte paginé).
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Anonymisation légère : "Ariana K."
        $firstName   = $this->user?->first_name ?? '';
        $lastInitial = $this->user?->last_name
            ? mb_strtoupper(mb_substr($this->user->last_name, 0, 1))
            : '';

        return [
            'id'        => (string) $this->id,
            'rating'    => (int) $this->rating,
            'comment'   => $this->comment,
            'status'    => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'author'    => [
                'firstName'       => $firstName,
                'lastInitial'     => $lastInitial,
                'profileImageUrl' => $this->user?->profile_image_url,
            ],
        ];
    }
}
