<?php

declare(strict_types=1);

namespace App\Http\Requests\MySchedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreDayOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date_format:Y-m-d'],
            // Optional end-date : the request creates one day off per date
            // between [date, until_date] inclusive. Defaults to `date` when
            // omitted (a single-day close).
            'until_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'reason'     => ['nullable', 'string', 'max:255'],
        ];
    }
}
