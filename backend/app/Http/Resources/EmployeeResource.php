<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee (company_user pivot row) shape for the owner dashboard.
 *
 * $this wraps a CompanyUser model with a loaded `user` relation.
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => (string) $this->id,
            'role'        => $this->role instanceof \BackedEnum
                ? $this->role->value
                : $this->role,
            'isActive'    => (bool) $this->is_active,
            'specialties' => $this->specialties ?? [],
            'profilePhoto'=> $this->profile_photo,

            // Service IDs this employee can perform (empty array when not loaded)
            'serviceIds'  => $this->whenLoaded(
                'services',
                fn () => $this->services->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                [],
            ),

            // Flattened user fields
            'userId'      => $this->user ? (string) $this->user->id : null,
            'firstName'   => $this->user?->first_name,
            'lastName'    => $this->user?->last_name,
            'email'       => $this->user?->email,
            'phone'       => $this->user?->phone,
        ];
    }
}
