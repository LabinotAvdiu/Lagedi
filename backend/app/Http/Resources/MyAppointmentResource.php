<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appointment shape for the "My Bookings" list in the Flutter app.
 *
 * Expected shape:
 * {
 *   "id", "companyName", "serviceName", "employeeName",
 *   "dateTime", "durationMinutes", "price", "status"
 * }
 *
 * Requires eager-loaded relations: company, service, companyUser.user
 */
class MyAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Combine separate date + start_time columns into a single ISO datetime string
        $dateTime = $this->date->format('Y-m-d') . 'T' . $this->start_time;

        // companyUser → user → first_name + last_name
        $employeeName = null;
        if ($this->companyUser && $this->companyUser->user) {
            $user = $this->companyUser->user;
            $employeeName = trim($user->first_name . ' ' . $user->last_name);
        }

        return [
            'id'              => (string) $this->id,
            'companyId'       => (string) $this->company_id,
            'companyName'     => $this->company?->name,
            'companyAddress'  => $this->company?->address,
            'companyPhotoUrl' => $this->company?->profile_image_url,
            'serviceName'     => $this->service?->name,
            'employeeName'    => $employeeName,
            'dateTime'        => $dateTime,
            'durationMinutes' => $this->service ? (int) $this->service->duration : null,
            'price'           => $this->service ? (float) $this->service->price : null,
            'status'          => $this->status instanceof AppointmentStatus
                ? $this->status->value
                : $this->status,
        ];
    }
}
