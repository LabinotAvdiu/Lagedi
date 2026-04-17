<?php

declare(strict_types=1);

namespace App\Http\Requests\MySchedule;

use Illuminate\Foundation\Http\FormRequest;

class GetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
