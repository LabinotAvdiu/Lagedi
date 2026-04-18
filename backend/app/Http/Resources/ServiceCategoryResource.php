<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Service category with nested services for the owner dashboard.
 */
class ServiceCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => (string) $this->id,
            'name'     => $this->name,
            'services' => $this->whenLoaded('services', fn () =>
                $this->services->map(fn ($service) => [
                    'id'              => (string) $service->id,
                    'name'            => $service->name,
                    'description'     => $service->description,
                    'durationMinutes' => (int) $service->duration,
                    'price'           => (float) $service->price,
                    'isActive'        => (bool) $service->is_active,
                    'maxConcurrent'   => $service->max_concurrent !== null ? (int) $service->max_concurrent : null,
                ])->values()
            ),
        ];
    }
}
