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
        return [
            'token'         => $this->token,
            'refresh_token' => $this->refreshToken,
            'user'          => new UserResource($this->resource),
        ];
    }
}
