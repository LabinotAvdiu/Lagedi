<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight company shape for the Flutter listing cards.
 *
 * Shape:
 * {
 *   "id":           "1",
 *   "name":         "Salon Élégance",
 *   "address":      "39 Rue de la Bourse, Paris",
 *   "city":         "Paris",
 *   "photoUrl":     "https://...",
 *   "rating":       4.8,
 *   "reviewCount":  123,
 *   "priceLevel":   3,
 *   "availability": [
 *     { "date": "2026-04-17", "morning": true,  "afternoon": true  },
 *     { "date": "2026-04-18", "morning": true,  "afternoon": false },
 *     { "date": "2026-04-19", "morning": false, "afternoon": false },
 *     ...
 *   ]
 * }
 */
class CompanyListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => (string) $this->id,
            'name'         => $this->name,
            'address'      => $this->address,
            'city'         => $this->city,
            // Controller resolves the gallery thumbnail (or profile fallback)
            // and stores it as `photo_url` in the cached array.
            'photoUrl'     => $this->photo_url ?? $this->profile_image_url,
            'rating'       => (float) $this->rating,
            'reviewCount'  => (int) $this->review_count,
            'priceLevel'   => (int) $this->price_level,
            'bookingMode'  => $this->booking_mode instanceof \BackedEnum
                ? $this->booking_mode->value
                : ($this->booking_mode ?? 'employee_based'),
            'availability' => $this->availability ?? [],
            // Injected post-cache by CompanyController::index() — never stored
            // in the cache itself so it can never leak between users.
            'isFavorite'   => (bool) ($this->is_favorite ?? false),
            // Preferred employee for this favorite (null when no preference,
            // or when company isn't employee_based, or when the user simply
            // hasn't favorited the salon). The Flutter app uses this to show
            // a "favorite-with-employee" card alongside a plain version
            // when both `isFavorite` and `preferredEmployeeId` are set.
            'preferredEmployeeId'   => $this->preferred_employee_id !== null
                ? (string) $this->preferred_employee_id
                : null,
            'preferredEmployeeName' => $this->preferred_employee_name ?? null,
        ];
    }
}
