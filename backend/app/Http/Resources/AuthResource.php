<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    private string $token;
    private string $refreshToken;

    public function __construct($resource, string $token, string $refreshToken)
    {
        parent::__construct($resource);
        $this->token = $token;
        $this->refreshToken = $refreshToken;
    }

    public function toArray(Request $request): array
    {
        // Users with role=company but no owner pivot yet (fresh social sign-up)
        // need to finish the company setup flow client-side. The flag lets the
        // app redirect straight to the business-info screen.
        $needsCompanySetup = false;
        if ($this->resource->role?->value === 'company') {
            $needsCompanySetup = ! $this->resource->companies()
                ->wherePivot('role', 'owner')
                ->exists();
        }

        return [
            'token'              => $this->token,
            'refresh_token'      => $this->refreshToken,
            'user'               => new UserResource($this->resource),
            'needsCompanySetup'  => $needsCompanySetup,
        ];
    }
}
