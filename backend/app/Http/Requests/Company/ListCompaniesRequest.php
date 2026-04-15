<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class ListCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard already enforced by the route middleware
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'city'   => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'in:men,women,both'],
            'date'   => ['nullable', 'date_format:Y-m-d'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}
