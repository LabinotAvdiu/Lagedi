<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use App\Enums\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class CreateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'password'     => ['required', Password::min(8)],
            'role'         => ['sometimes', new Enum(CompanyRole::class)],
            'specialties'  => ['sometimes', 'array'],
            'specialties.*'=> ['string', 'max:100'],
        ];
    }
}
