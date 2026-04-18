<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appointment shape for the owner's planning view (Type 2 — capacity_based).
 *
 * Requires eager-loaded relations: service, companyUser.user
 * For walk-in appointments, client info comes from walk_in_* columns.
 * For registered clients, client info comes from the user relation.
 */
class OwnerAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve client identity: walk-in fields take priority over user relation
        if ($this->is_walk_in) {
            $clientFirstName = $this->walk_in_first_name;
            $clientLastName  = $this->walk_in_last_name;
            $clientPhone     = $this->walk_in_phone;
        } else {
            $clientFirstName = $this->user?->first_name;
            $clientLastName  = $this->user?->last_name;
            $clientPhone     = $this->user?->phone;
        }

        // Resolve employee name from companyUser → user (null for Type 2)
        $employeeName = null;
        if ($this->companyUser && $this->companyUser->user) {
            $u = $this->companyUser->user;
            $employeeName = trim($u->first_name . ' ' . $u->last_name) ?: null;
        }

        return [
            'id'              => (string) $this->id,
            'date'            => $this->date->format('Y-m-d'),
            'startTime'       => substr((string) $this->start_time, 0, 5), // "HH:MM"
            'endTime'         => substr((string) $this->end_time, 0, 5),
            'status'          => $this->status instanceof AppointmentStatus
                ? $this->status->value
                : $this->status,
            'clientFirstName' => $clientFirstName,
            'clientLastName'  => $clientLastName,
            'clientPhone'     => $clientPhone,
            'service'         => $this->service ? [
                'id'              => (string) $this->service->id,
                'name'            => $this->service->name,
                'durationMinutes' => (int) $this->service->duration,
                'price'           => (float) $this->service->price,
            ] : null,
            'employeeName'    => $employeeName,
            'isWalkIn'        => (bool) $this->is_walk_in,
        ];
    }
}
