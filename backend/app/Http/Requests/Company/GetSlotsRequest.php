<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class GetSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'date'        => ['required', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'exists:company_user,id'],
            'service_id'  => ['nullable', 'integer', 'exists:services,id'],
        ];
    }
}
