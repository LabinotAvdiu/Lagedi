<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCapacityOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'notes'    => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
