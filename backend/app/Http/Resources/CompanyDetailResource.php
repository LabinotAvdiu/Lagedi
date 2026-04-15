<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full company detail shape for the Flutter detail / booking page.
 *
 * Expected shape:
 * {
 *   "id", "name", "address", "city", "priceLevel", "rating", "reviewCount",
 *   "photos": ["url", ...],
 *   "categories": [{ "id", "name", "services": [{ "id", "name", "durationMinutes", "price" }] }],
 *   "employees": [{ "id", "name", "photoUrl", "specialties": ["Coupe", ...] }]
 * }
 */
class CompanyDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => (string) $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'address'     => $this->address,
            'city'        => $this->city,
            'postalCode'  => $this->postal_code,
            'country'     => $this->country,
            'gender'      => $this->gender?->value,
            'rating'      => (float) $this->rating,
            'reviewCount' => (int) $this->review_count,
            'priceLevel'  => (int) $this->price_level,

            // ----------------------------------------------------------------
            // photos — from gallery_images; fall back to profile_image_url
            // ----------------------------------------------------------------
            'photos' => $this->buildPhotos(),

            // ----------------------------------------------------------------
            // categories — service_categories scoped to this company,
            // each with nested active services
            // ----------------------------------------------------------------
            'categories' => $this->buildCategories(),

            // ----------------------------------------------------------------
            // openingHours — kept for completeness
            // ----------------------------------------------------------------
            'openingHours' => $this->whenLoaded('openingHours', fn () =>
                $this->openingHours->map(fn ($oh) => [
                    'dayOfWeek' => $oh->day_of_week instanceof \BackedEnum
                        ? $oh->day_of_week->value
                        : $oh->day_of_week,
                    'openTime'  => $oh->open_time,
                    'closeTime' => $oh->close_time,
                    'isClosed'  => (bool) $oh->is_closed,
                ])->values()
            ),

            // ----------------------------------------------------------------
            // employees — from company_user pivot with user info
            // ----------------------------------------------------------------
            'employees' => $this->buildEmployees(),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildPhotos(): array
    {
        if ($this->relationLoaded('galleryImages') && $this->galleryImages->isNotEmpty()) {
            return $this->galleryImages
                ->map(fn ($img) => $img->image_path)
                ->filter()
                ->values()
                ->toArray();
        }

        // Fallback: return the single profile image if no gallery exists
        return $this->profile_image_url ? [$this->profile_image_url] : [];
    }

    private function buildCategories(): array
    {
        if (! $this->relationLoaded('serviceCategories')) {
            return [];
        }

        return $this->serviceCategories
            ->map(fn ($category) => [
                'id'       => (string) $category->id,
                'name'     => $category->name,
                'services' => $category->relationLoaded('services')
                    ? $category->services
                        ->where('is_active', true)
                        ->map(fn ($service) => [
                            'id'              => (string) $service->id,
                            'name'            => $service->name,
                            'durationMinutes' => (int) $service->duration,
                            'price'           => (float) $service->price,
                        ])
                        ->values()
                        ->toArray()
                    : [],
            ])
            ->filter(fn ($cat) => ! empty($cat['services'])) // hide empty categories
            ->values()
            ->toArray();
    }

    private function buildEmployees(): array
    {
        if (! $this->relationLoaded('members')) {
            return [];
        }

        return $this->members
            ->where('is_active', true)
            ->map(fn ($member) => [
                'id'          => (string) $member->id,
                'name'        => $member->relationLoaded('user')
                    ? trim($member->user->first_name . ' ' . $member->user->last_name)
                    : null,
                'photoUrl'    => $member->profile_photo,
                'specialties' => $member->specialties ?? [],
            ])
            ->values()
            ->toArray();
    }
}
