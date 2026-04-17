<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use App\Enums\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role'          => ['sometimes', new Enum(CompanyRole::class)],
            'is_active'     => ['sometimes', 'boolean'],
            'specialties'   => ['sometimes', 'array'],
            'specialties.*' => ['string', 'max:100'],
            // service_ids: optional full replacement of the employee's service list
            'service_ids'   => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ];
    }
}
