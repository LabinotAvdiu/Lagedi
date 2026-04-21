<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Display-side normalisation for person names.
 *
 * Walk-in forms and legacy data store names as typed ("jean", "TEST USER") —
 * we title-case on the way out rather than mutating stored values so
 * special-case names ("van der", "de la") can keep their intended form.
 *
 * Handles accents via `mb_convert_case` and compound separators (`-`, `'`)
 * via a regex pass since MB_CASE_TITLE only capitalises after whitespace.
 */
final class NameFormatter
{
    public static function titleCase(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $lowered = mb_strtolower($trimmed, 'UTF-8');
        $titled  = mb_convert_case($lowered, MB_CASE_TITLE, 'UTF-8');
        return preg_replace_callback(
            "/([\\s\\-'])(.)/u",
            static fn ($m) => $m[1] . mb_strtoupper($m[2], 'UTF-8'),
            $titled,
        );
    }
}
