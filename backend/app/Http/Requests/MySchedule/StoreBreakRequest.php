<?php

declare(strict_types=1);

namespace App\Http\Requests\MySchedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'label'       => ['nullable', 'string', 'max:100'],
            // When true, save the break even if appointments overlap the
            // window. Used after the user explicitly confirmed via the
            // "save anyway" dialog. Default false → conflict returns 409.
            'force'       => ['nullable', 'boolean'],
        ];
    }
}
