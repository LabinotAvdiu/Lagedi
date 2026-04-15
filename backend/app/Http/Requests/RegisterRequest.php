<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:8',
            'phone'      => 'required|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'is_required',
            'email.required'    => 'is_required',
            'email.email'       => 'is_invalid',
            'email.unique'      => 'already_taken',
            'password.required' => 'is_required',
            'password.min'      => 'min_8_characters',
            'phone.required'    => 'is_required',
        ];
    }
}
