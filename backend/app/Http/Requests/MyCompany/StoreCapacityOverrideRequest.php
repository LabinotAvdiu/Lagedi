<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class StoreCapacityOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'     => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'notes'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
