<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full company profile shape for the owner's "Mon Salon" tab.
 */
class MyCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => (string) $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'phone'           => $this->phone,
            'phoneSecondary'  => $this->phone_secondary,
            'email'           => $this->email,
            'address'         => $this->address,
            'city'            => $this->city,
            'postalCode'      => $this->postal_code,
            'country'         => $this->country,
            'gender'          => $this->gender?->value,
            'bookingMode'          => $this->booking_mode instanceof \BackedEnum
                ? $this->booking_mode->value
                : $this->booking_mode,
            'capacityAutoApprove'  => (bool) ($this->capacity_auto_approve ?? false),
            'profileImageUrl' => $this->profile_image_url,
            'rating'          => (float) $this->rating,
            'reviewCount'     => (int) $this->review_count,
            'priceLevel'      => (int) $this->price_level,
            'minCancelHours'  => (int) ($this->min_cancel_hours ?? 2),
            'latitude'        => $this->when(isset($this->latitude), fn () => (float) $this->latitude),
            'longitude'       => $this->when(isset($this->longitude), fn () => (float) $this->longitude),

            'openingHours' => $this->whenLoaded('openingHours', fn () =>
                $this->openingHours
                    ->sortBy('day_of_week')
                    ->map(fn ($oh) => [
                        'dayOfWeek' => $oh->day_of_week instanceof \BackedEnum
                            ? $oh->day_of_week->value
                            : $oh->day_of_week,
                        'openTime'  => $oh->open_time,
                        'closeTime' => $oh->close_time,
                        'isClosed'  => (bool) $oh->is_closed,
                    ])->values()
            ),
        ];
    }
}
