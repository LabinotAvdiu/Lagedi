<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateOpeningHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours'               => ['required', 'array', 'min:1'],
            'hours.*.day_of_week' => ['required', new Enum(DayOfWeek::class)],
            'hours.*.open_time'   => ['required_if:hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'hours.*.close_time'  => ['required_if:hours.*.is_closed,false', 'nullable', 'date_format:H:i'],
            'hours.*.is_closed'   => ['required', 'boolean'],
        ];
    }
}
