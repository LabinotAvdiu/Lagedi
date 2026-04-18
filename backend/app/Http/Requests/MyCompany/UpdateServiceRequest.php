<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:service_categories,id'],
            'name'        => ['sometimes', 'string', 'max:255'],
            'duration'    => ['sometimes', 'integer', 'min:5', 'max:480'],
            'price'       => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'      => ['sometimes', 'boolean'],
            'max_concurrent' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
