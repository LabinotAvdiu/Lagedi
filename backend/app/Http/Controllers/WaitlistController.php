<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientWaitlistRequest;
use App\Http\Requests\StoreOwnerWaitlistRequest;
use App\Models\ClientWaitlistEntry;
use App\Models\OwnerWaitlistEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WaitlistController extends Controller
{
    /**
     * POST /api/waitlist/client
     */
    public function storeClient(StoreClientWaitlistRequest $request): JsonResponse
    {
        $contact = $this->splitContact($request->validated('contact'));
        if ($contact === null) {
            return response()->json([
                'errors' => ['contact' => ['invalid_format']],
            ], 422);
        }

        ClientWaitlistEntry::create(array_merge(
            [
                'name'             => $request->validated('name'),
                'city'             => $request->validated('city'),
                'source'           => $request->validated('source'),
                'cgu_accepted_at'  => now(),
                'unsubscribe_token' => $this->makeUnsubscribeToken(),
            ],
            $contact,
            $this->derivedFields($request),
        ));

        return response()->json(['ok' => true], 201);
    }

    /**
     * POST /api/waitlist/owner
     */
    public function storeOwner(StoreOwnerWaitlistRequest $request): JsonResponse
    {
        $contact = $this->splitContact($request->validated('contact'));
        if ($contact === null) {
            return response()->json([
                'errors' => ['contact' => ['invalid_format']],
            ], 422);
        }

        OwnerWaitlistEntry::create(array_merge(
            [
                'owner_name'        => $request->validated('owner_name'),
                'salon_name'        => $request->validated('salon_name'),
                'city'              => $request->validated('city'),
                'source'            => $request->validated('source'),
                'when_to_start'     => $request->validated('when_to_start'),
                'cgu_accepted_at'   => now(),
                'unsubscribe_token' => $this->makeUnsubscribeToken(),
            ],
            $contact,
            $this->derivedFields($request),
        ));

        return response()->json(['ok' => true], 201);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Split a free-form contact input into ['email' => …, 'phone' => …].
     * Returns null when the input matches neither pattern.
     */
    private function splitContact(string $raw): ?array
    {
        $raw = trim($raw);

        // Email path — '@' present + valid format
        if (str_contains($raw, '@')) {
            if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                return ['email' => mb_strtolower($raw), 'phone' => null];
            }
            return null;
        }

        // Phone path — Kosovo formats. Accept +383XXXXXXXX, 00383…, 04…, 049…
        // Strip spaces, dots, dashes, parens.
        $digits = preg_replace('/[\s\.\-\(\)]/', '', $raw);
        if (! preg_match('/^(\+383|00383|0)[0-9]{7,9}$/', $digits)) {
            return null;
        }

        return ['email' => null, 'phone' => $digits];
    }

    /**
     * Generate a 64-char URL-safe token for the 1-click unsubscribe link.
     */
    private function makeUnsubscribeToken(): string
    {
        return Str::random(64);
    }

    /**
     * Server-derived fields stored alongside every submission.
     * Locale from Accept-Language, IP country from CF-IPCountry header
     * (Cloudflare) with a Kosovo default, utm_* from query string,
     * referrer from header.
     */
    private function derivedFields(Request $request): array
    {
        $accept = $request->header('Accept-Language', '');
        $primary = strtolower(substr(trim(explode(',', $accept)[0] ?? ''), 0, 2));
        $locale = in_array($primary, ['sq', 'fr', 'en'], true) ? $primary : null;

        $ipCountry = strtoupper((string) $request->header('CF-IPCountry', '')) ?: null;
        if ($ipCountry === 'XX' || $ipCountry === 'T1') {
            $ipCountry = null; // Tor / unknown
        }
        $isDiaspora = $ipCountry !== null
            && ! in_array($ipCountry, ['XK', 'AL', 'MK'], true);

        return [
            'locale'        => $locale,
            'ip_country'    => $ipCountry,
            'is_diaspora'   => $isDiaspora,
            'utm_source'    => $request->query('utm_source'),
            'utm_medium'    => $request->query('utm_medium'),
            'utm_campaign'  => $request->query('utm_campaign'),
            'referrer_url'  => substr((string) $request->header('Referer', ''), 0, 500) ?: null,
        ];
    }
}
