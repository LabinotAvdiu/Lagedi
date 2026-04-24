<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasAccount = User::where('email', $this->resource->email)->exists();

        return [
            'id'          => $this->resource->id,
            'kind'        => 'invitation',
            'email'       => $this->resource->email,
            'firstName'   => $this->resource->first_name,
            'lastName'    => $this->resource->last_name,
            'specialties' => $this->resource->specialties ?? [],
            'role'        => $this->resource->role,
            'status'      => $this->resource->status->value,
            'expiresAt'   => $this->resource->expires_at?->toIso8601String(),
            'createdAt'   => $this->resource->created_at?->toIso8601String(),
            'hasAccount'  => $hasAccount,
        ];
    }
}
