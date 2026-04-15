<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appointment shape for the Flutter booking flow.
 *
 * Expected shape:
 * {
 *   "id", "companyId", "serviceId", "employeeId",
 *   "dateTime", "status"
 * }
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Reconstruct the ISO dateTime from separate date + start_time columns
        $dateTime = $this->date->format('Y-m-d') . 'T' . $this->start_time;

        return [
            'id'         => (string) $this->id,
            'companyId'  => (string) $this->company_id,
            'serviceId'  => (string) $this->service_id,
            // employee_id in the API corresponds to company_user_id in the DB
            'employeeId' => $this->company_user_id !== null
                ? (string) $this->company_user_id
                : null,
            'dateTime'   => $dateTime,
            'status'     => $this->status instanceof AppointmentStatus
                ? $this->status->value
                : $this->status,
        ];
    }
}
