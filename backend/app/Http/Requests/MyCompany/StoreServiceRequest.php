<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'duration'    => ['required', 'integer', 'min:5', 'max:480'],
            'price'       => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'      => ['sometimes', 'boolean'],
            'max_concurrent' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
