<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name'    => ['required', 'string', 'min:2', 'max:80'],
            'salon_name'    => ['required', 'string', 'min:2', 'max:120'],
            'contact'       => ['required', 'string', 'max:150'],
            'city'          => ['required', Rule::in(StoreClientWaitlistRequest::CITIES)],
            'source'        => ['required', Rule::in(StoreClientWaitlistRequest::SOURCES)],
            'when_to_start' => ['required', Rule::in(['now', 'at_launch'])],
            'cgu_accepted'  => ['required', 'accepted'],
        ];
    }
}
