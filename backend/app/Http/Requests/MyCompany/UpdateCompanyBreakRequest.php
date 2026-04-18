<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:6'],
            'start_time'  => ['sometimes', 'date_format:H:i'],
            'end_time'    => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'label'       => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
