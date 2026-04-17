<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'        => ['sometimes', 'string', 'max:100'],
            'last_name'         => ['sometimes', 'string', 'max:100'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'city'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'profile_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'locale'            => ['sometimes', 'nullable', 'string', 'in:fr,en'],
        ];
    }
}
