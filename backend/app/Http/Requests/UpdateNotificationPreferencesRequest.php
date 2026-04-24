<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D19 — Validation du PATCH /api/me/notification-preferences
 *
 * Body attendu :
 * [
 *   { "channel": "push",  "type": "marketing",        "enabled": false },
 *   { "channel": "email", "type": "reminder_evening",  "enabled": true  },
 *   ...
 * ]
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // L'auth Sanctum est vérifiée par le middleware de route
    }

    public function rules(): array
    {
        $validChannels = ['push', 'email', 'in-app'];
        $validTypes    = NotificationType::all();

        return [
            '*'          => ['required', 'array'],
            '*.channel'  => ['required', 'string', Rule::in($validChannels)],
            '*.type'     => ['required', 'string', Rule::in($validTypes)],
            '*.enabled'  => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.channel.in' => 'Channel must be one of: push, email, in-app.',
            '*.type.in'    => 'Type must be one of the configurable notification types.',
        ];
    }
}
