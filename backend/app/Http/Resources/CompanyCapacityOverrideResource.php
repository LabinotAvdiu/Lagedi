<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCapacityOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => (string) $this->id,
            'date'     => $this->date?->format('Y-m-d'),
            'capacity' => (int) $this->capacity,
            'notes'    => $this->notes,
        ];
    }
}
