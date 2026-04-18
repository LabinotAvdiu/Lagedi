<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'phone'          => $this->phone,
            'phoneSecondary' => $this->phone_secondary,
            'email'       => $this->email,
            'address'     => $this->address,
            'city'        => $this->city,
            'postalCode'  => $this->postal_code,
            'country'     => $this->country,
            'gender'      => $this->gender?->value,
            'bookingMode' => $this->booking_mode instanceof \BackedEnum
                ? $this->booking_mode->value
                : $this->booking_mode,
            'rating'      => (float) $this->rating,
            'reviewCount' => (int) $this->review_count,
            'priceLevel'  => (int) $this->price_level,
            'latitude'    => $this->when(isset($this->latitude), fn () => (float) $this->latitude),
            'longitude'   => $this->when(isset($this->longitude), fn () => (float) $this->longitude),
            // isFavorite is injected post-cache by CompanyController::show()
            // when served via the cached array path.  When this Resource is
            // instantiated directly (e.g. in tests), the value comes from the
            // model if it has been set as a dynamic property.
            'isFavorite'  => (bool) ($this->isFavorite ?? false),

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
            // Gallery is already ordered by sort_order via the relation definition.
            // Return medium-quality URLs for the detail page; fall back to original.
            return $this->galleryImages
                ->map(function ($img) {
                    $path = $img->medium_path ?? $img->image_path;
                    if (! $path) return null;
                    // Legacy fixtures may store absolute URLs in image_path;
                    // don't double-prefix with APP_URL/storage/.
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        return $path;
                    }
                    return Storage::disk('public')->url($path);
                })
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
                            'maxConcurrent'   => $service->max_concurrent !== null ? (int) $service->max_concurrent : null,
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
