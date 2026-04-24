<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        $isCompany = $this->input('role') === 'company';

        return [
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()],
            'phone'        => ['nullable', 'string', 'max:20'],
            'city'         => ['nullable', 'string', 'max:100'],
            // Personal gender — drives the default home gender filter for
            // clients. Binary at user level; null = no preference.
            'gender'       => ['nullable', 'string', 'in:men,women'],
            'role'         => ['nullable', 'string', 'in:user,company'],
            'locale'       => ['nullable', 'string', 'in:fr,en,sq'],

            // Company-specific fields — required only when role=company
            'company_name'   => [$isCompany ? 'required' : 'nullable', 'string', 'max:255'],
            'address'        => [$isCompany ? 'required' : 'nullable', 'string', 'max:255'],
            'booking_mode'   => ['nullable', 'string', 'in:employee_based,capacity_based'],
            // Salon clientele filter — men-only, women-only or both. Displayed
            // on the company card so clients can filter home by gender target.
            'company_gender' => [$isCompany ? 'required' : 'nullable', 'string', 'in:men,women,both'],

            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'invitation_token' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
