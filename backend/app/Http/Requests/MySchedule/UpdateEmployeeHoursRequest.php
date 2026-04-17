<?php

declare(strict_types=1);

namespace App\Http\Requests\MySchedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours'                   => ['required', 'array', 'size:7'],
            'hours.*.day_of_week'     => ['required', 'integer', 'between:0,6'],
            'hours.*.start_time'      => ['nullable', 'date_format:H:i'],
            'hours.*.end_time'        => ['nullable', 'date_format:H:i'],
            'hours.*.is_working'      => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hours = $this->input('hours', []);
            foreach ($hours as $i => $hour) {
                if (!empty($hour['is_working'])) {
                    if (empty($hour['start_time'])) {
                        $validator->errors()->add("hours.$i.start_time", "L'heure de début est requise.");
                    }
                    if (empty($hour['end_time'])) {
                        $validator->errors()->add("hours.$i.end_time", "L'heure de fin est requise.");
                    }
                    if (!empty($hour['start_time']) && !empty($hour['end_time']) && $hour['end_time'] <= $hour['start_time']) {
                        $validator->errors()->add("hours.$i.end_time", "L'heure de fin doit être après l'heure de début.");
                    }
                }
            }
        });
    }
}
