<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_mode'          => ['sometimes', 'string', 'in:employee_based,capacity_based'],
            'capacity_auto_approve' => ['sometimes', 'boolean'],
        ];
    }
}
