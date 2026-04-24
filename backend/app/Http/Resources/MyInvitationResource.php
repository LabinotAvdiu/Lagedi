<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->resource->invitedBy;
        $company = $this->resource->company;

        return [
            'id'        => $this->resource->id,
            'company'   => [
                'id'      => (string) $company->id,
                'name'    => $company->name,
                'city'    => $company->city ?? null,
                'logoUrl' => $company->profile_image_url ?? null,
            ],
            'invitedBy' => [
                'firstName' => $owner->first_name,
                'lastName'  => $owner->last_name,
            ],
            'role'      => $this->resource->role,
            'expiresAt' => $this->resource->expires_at?->toIso8601String(),
        ];
    }
}
