<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyWalkInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:20'],
        ];
    }
}
