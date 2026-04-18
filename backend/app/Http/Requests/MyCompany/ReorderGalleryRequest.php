<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class ReorderGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via resolveOwnedCompany()
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'  => 'The ids field is required.',
            'ids.array'     => 'The ids field must be an array.',
            'ids.min'       => 'At least one image ID is required.',
            'ids.*.integer' => 'Each id must be an integer.',
        ];
    }
}
