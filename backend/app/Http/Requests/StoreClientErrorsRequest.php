<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * E28 — Validation du batch d'erreurs Flutter.
 *
 * Accepte jusqu'à 50 erreurs par requête (batch). Le client Flutter
 * accumule les erreurs dans un buffer et les envoie groupées.
 */
class StoreClientErrorsRequest extends FormRequest
{
    /**
     * Pas d'auth requise — l'endpoint est public pour capturer
     * les erreurs qui surviennent avant le login.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'errors'                  => ['required', 'array', 'min:1', 'max:50'],
            'errors.*.platform'       => ['required', 'string', 'in:android,ios,web'],
            'errors.*.app_version'    => ['required', 'string', 'max:32'],
            'errors.*.error_type'     => ['required', 'string', 'max:64'],
            'errors.*.message'        => ['required', 'string', 'max:5000'],
            'errors.*.stack_trace'    => ['nullable', 'string', 'max:20000'],
            'errors.*.route'          => ['nullable', 'string', 'max:255'],
            'errors.*.http_status'    => ['nullable', 'integer', 'between:100,599'],
            'errors.*.http_url'       => ['nullable', 'string', 'max:2000'],
            'errors.*.context'        => ['nullable', 'array'],
            'errors.*.occurred_at'    => ['required', 'date'],
        ];
    }
}
