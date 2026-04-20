<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:confirmed,rejected,cancelled,no_show'],
            // Optional owner-typed motif. Used on rejected (shown to client)
            // and on owner-initiated cancellation (kept in history).
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
