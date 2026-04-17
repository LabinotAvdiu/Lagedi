<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => (string) $this->id,
            'dayOfWeek'  => $this->day_of_week instanceof \BackedEnum
                ? $this->day_of_week->value
                : $this->day_of_week,
            'openTime'   => $this->open_time,
            'closeTime'  => $this->close_time,
            'isClosed'   => (bool) $this->is_closed,
        ];
    }
}
