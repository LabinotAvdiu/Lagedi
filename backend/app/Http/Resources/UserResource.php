<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a User model into the JSON shape expected by the Flutter app.
 *
 * Returned shape:
 * {
 *   "id":              "string (UUID or numeric string)",
 *   "email":           "string",
 *   "firstName":       "string",
 *   "lastName":        "string",
 *   "phone":           "string|null",
 *   "profileImageUrl": "string|null"
 * }
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => (string) $this->id,
            'email'           => $this->email,
            'firstName'       => $this->first_name ?? '',
            'lastName'        => $this->last_name ?? '',
            'phone'           => $this->phone,
            'city'            => $this->city,
            'role'            => $this->role?->value ?? 'user',
            'profileImageUrl' => $this->profile_image_url,
            'emailVerified'   => $this->email_verified_at !== null,
        ];
    }
}
