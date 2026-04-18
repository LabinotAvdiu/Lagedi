<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyBreakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => (string) $this->id,
            'dayOfWeek' => $this->day_of_week,
            'startTime' => $this->start_time,
            'endTime'   => $this->end_time,
            'label'     => $this->label,
        ];
    }
}
