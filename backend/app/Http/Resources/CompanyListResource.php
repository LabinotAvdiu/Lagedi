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
            'photoUrl'     => $this->profile_image_url,
            'rating'       => (float) $this->rating,
            'reviewCount'  => (int) $this->review_count,
            'priceLevel'   => (int) $this->price_level,
            'availability' => $this->availability ?? [],
        ];
    }
}
