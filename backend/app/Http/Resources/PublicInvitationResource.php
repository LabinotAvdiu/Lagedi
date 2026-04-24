<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->resource->invitedBy;
        $company = $this->resource->company;

        return [
            'companyName' => $company->name,
            'ownerName'   => trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) ?: 'Termini im',
            'email'       => $this->resource->email,
            'firstName'   => $this->resource->first_name,
            'lastName'    => $this->resource->last_name,
            'expiresAt'   => $this->resource->expires_at?->toIso8601String(),
        ];
    }
}
