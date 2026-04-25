<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'min:2', 'max:80'],
            'contact' => ['required', 'string', 'max:150'],
            'city'    => ['required', Rule::in(self::CITIES)],
            'source'  => ['required', Rule::in(self::SOURCES)],
            'cgu_accepted' => ['required', 'accepted'],
        ];
    }

    public const CITIES = [
        'Ferizaj', 'Prishtinë', 'Prizren', 'Pejë', 'Gjakovë',
        'Mitrovicë', 'Gjilan', 'Tjetër',
    ];

    public const SOURCES = ['facebook', 'instagram', 'tiktok', 'other'];
}
