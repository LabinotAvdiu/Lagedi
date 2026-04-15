<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'company_id'  => ['required', 'integer', 'exists:companies,id'],
            'service_id'  => ['required', 'integer', 'exists:services,id'],
            'employee_id' => ['nullable', 'integer', 'exists:company_user,id'],
            'date_time'   => ['required', 'date_format:Y-m-d\TH:i:s', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_time.after' => 'The booking date must be in the future.',
        ];
    }
}
