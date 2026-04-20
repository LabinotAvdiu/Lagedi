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
 *   "profileImageUrl": "string|null",
 *   "thumbnailUrl":    "string|null"
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
            'gender'          => $this->gender, // 'men' | 'women' | null
            'role'            => $this->role?->value ?? 'user',
            'companyRole'     => \DB::table('company_user')
                ->where('user_id', $this->id)
                ->value('role'),
            'profileImageUrl' => $this->profile_image_url,
            'thumbnailUrl'    => $this->resolveThumbnailUrl(),
            'emailVerified'   => $this->email_verified_at !== null,
            'locale'          => $this->locale ?? 'fr',
        ];
    }

    /**
     * Derives the thumbnail URL from profileImageUrl by replacing /medium/ with /thumb/.
     * Returns null when no avatar has been uploaded yet.
     */
    private function resolveThumbnailUrl(): ?string
    {
        if (! $this->profile_image_url) {
            return null;
        }

        $thumb = str_replace('/medium/', '/thumb/', $this->profile_image_url);

        // Guard: if the URL does not contain /medium/ the replacement is a no-op
        // (e.g. legacy absolute URL stored directly). Return null in that case to
        // avoid returning the wrong URL to the client.
        if ($thumb === $this->profile_image_url) {
            return null;
        }

        return $thumb;
    }
}
