<?php

declare(strict_types=1);

namespace App\Http\Requests\MySchedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalkInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date_format:Y-m-d'],
            'time'       => ['required', 'date_format:H:i'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }
}
