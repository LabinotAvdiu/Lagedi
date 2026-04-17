<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use App\Enums\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InviteEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'        => ['required', 'email', 'max:255'],
            'role'         => ['sometimes', new Enum(CompanyRole::class)],
            'specialties'  => ['sometimes', 'array'],
            'specialties.*'=> ['string', 'max:100'],
        ];
    }
}
