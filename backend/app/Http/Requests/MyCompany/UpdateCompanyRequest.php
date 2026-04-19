<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced in controller
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasLat = $this->filled('latitude');
            $hasLng = $this->filled('longitude');

            if ($hasLat && ! $hasLng) {
                $v->errors()->add('longitude', 'The longitude field is required when latitude is present.');
            }

            if ($hasLng && ! $hasLat) {
                $v->errors()->add('latitude', 'The latitude field is required when longitude is present.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'string', 'max:255'],
            'description'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'phone_secondary'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'email'             => ['sometimes', 'nullable', 'email', 'max:255'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'profile_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'latitude'          => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'min_cancel_hours'  => ['sometimes', 'integer', 'between:0,168'],
        ];
    }
}
