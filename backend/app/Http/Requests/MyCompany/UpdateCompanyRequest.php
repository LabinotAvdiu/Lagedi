<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced in controller
    }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'string', 'max:255'],
            'description'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'             => ['sometimes', 'nullable', 'email', 'max:255'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'profile_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
