<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'start_time'  => ['required', 'date_format:H:i', 'before:end_time'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'label'       => ['nullable', 'string', 'max:100'],
        ];
    }
}
