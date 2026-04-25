<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Server-rendered HTML stubs with proper Open Graph / Twitter Card meta tags
 * for social link unfurling. Served to crawlers (Facebook, WhatsApp, Twitter,
 * LinkedIn, iMessage, Slack, Discord…) when they fetch a /company/{id} URL.
 *
 * Real users never hit this controller — Nginx routes them to the Flutter SPA.
 * The crawler-detection happens at the Nginx layer (User-Agent sniffing) and
 * proxies bots to /share/company/{id} on this Laravel app.
 *
 * The HTML body itself contains a JS redirect so that humans who do reach this
 * URL directly land on the actual SPA route.
 */
class ShareController extends Controller
{
    /**
     * Cache TTL for the rendered HTML — keeps the DB cool when crawlers
     * re-scrape (Facebook does so every 30 days, but a popular salon may be
     * shared dozens of times in a day across platforms).
     */
    private const CACHE_TTL_SECONDS = 3600; // 1h

    /**
     * Public web URL of the Flutter SPA. The canonical og:url and the human
     * redirect both point here. Configurable via WEB_URL env var so staging
     * environments can override.
     */
    private function webUrl(): string
    {
        return rtrim((string) env('WEB_URL', 'https://www.termini-im.com'), '/');
    }

    /**
     * GET /share/company/{id}
     *
     * Returns an HTML stub with OG tags for the given salon. If the company
     * doesn't exist, returns a generic Termini Im page rather than a 404,
     * because most crawlers will silently drop a 404 from their preview cache.
     */
    public function company(string $id, ?string $employeeId = null): Response
    {
        $cacheKey = "share.company.{$id}." . ($employeeId ?: 'none');

        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->buildPayload($id),
        );

        return response($payload, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            // Edge / CDN caching — crawlers re-fetch periodically; stay light.
            ->header('Cache-Control', 'public, max-age=300, s-maxage=3600')
            // Anti-clickjacking + classic security headers since the page is
            // ultimately user-facing.
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Build the rendered HTML for one salon.
     *
     * Template (per product spec):
     *   og:title       → "{salon_name} — {service_principal} në {qyteti}"
     *   og:description → "{salon_name}: {services_list}. Rezervo online · Termini Im"
     *   og:image       → profile_image_url, fallback /og-default.png
     *   <title>        → "{salon_name} | {qyteti} · Termini Im"
     *   meta desc      → same as og:description, max 155 chars
     */
    private function buildPayload(string $id): string
    {
        $company = Company::with([
            'serviceCategories' => fn ($q) => $q->orderBy('name'),
            'serviceCategories.services' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
        ])->find($id);

        if (! $company) {
            return $this->renderFallback();
        }

        // ---------- Pieces -------------------------------------------------
        $salonName = trim((string) $company->name) ?: 'Termini Im';
        $city      = trim((string) $company->city) ?: 'Kosova';

        // Service principal = first category that actually has active services
        // (categories are pre-filtered alphabetically; "first non-empty" is a
        // stable, deterministic choice without needing a new column).
        $primaryCategory = $company->serviceCategories
            ->first(fn ($cat) => $cat->services->isNotEmpty());

        $servicePrincipal = $primaryCategory
            ? trim((string) $primaryCategory->name)
            : 'Sallon bukurie';

        // Top 4 service names across all categories — the description list.
        $serviceNames = $company->serviceCategories
            ->flatMap(fn ($cat) => $cat->services)
            ->take(4)
            ->map(fn ($s) => trim((string) $s->name))
            ->filter()
            ->values()
            ->all();

        $servicesList = empty($serviceNames)
            ? 'Sherbime profesionale'
            : implode(', ', $serviceNames);

        // ---------- Final strings ------------------------------------------
        $title       = "{$salonName} | {$city} · Termini Im";
        $ogTitle     = "{$salonName} — {$servicePrincipal} në {$city}";
        $description = Str::limit(
            "{$salonName}: {$servicesList}. Rezervo online · Termini Im",
            150,
            '…',
        );

        $ogImage   = $this->resolveOgImage($company->profile_image_url);
        $canonical = $this->webUrl() . '/company/' . $company->id;

        return $this->renderTemplate([
            'title'       => $title,
            'ogTitle'     => $ogTitle,
            'description' => $description,
            'ogImage'     => $ogImage,
            'canonical'   => $canonical,
            'salonName'   => $salonName,
            'city'        => $city,
            'rating'      => (float) $company->rating,
            'reviewCount' => (int) $company->review_count,
            'address'     => trim((string) ($company->address ?? '')),
        ]);
    }

    /**
     * Resolve the salon photo to an absolute URL. Storage URLs may be relative
     * paths (e.g. "companies/abc/profile.jpg") or already absolute URLs; we
     * normalise to absolute, falling back to the editorial logo.
     */
    private function resolveOgImage(?string $rawUrl): string
    {
        $fallback = $this->webUrl() . '/og-default.png';

        if (empty($rawUrl)) {
            return $fallback;
        }

        if (Str::startsWith($rawUrl, ['http://', 'https://'])) {
            return $rawUrl;
        }

        try {
            return Storage::disk('public')->url($rawUrl);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * Render the meta-tag HTML page. Plain string concatenation (rather than
     * a Blade view) keeps the output tight and lets us cache the final HTML
     * directly as a string in the cache layer.
     */
    private function renderTemplate(array $d): string
    {
        $title       = e($d['title']);
        $ogTitle     = e($d['ogTitle']);
        $description = e($d['description']);
        $ogImage     = e($d['ogImage']);
        $canonical   = e($d['canonical']);
        $salonName   = e($d['salonName']);
        $city        = e($d['city']);
        $address     = e($d['address']);
        $rating      = number_format($d['rating'], 1);
        $reviewCount = $d['reviewCount'];

        // JSON-LD LocalBusiness — boosts Google rich-result eligibility.
        // JSON_HEX_TAG escapes "<" and ">" so a malicious salon name can't
        // close the <script> tag and inject markup.
        $ldData = [
            '@context' => 'https://schema.org',
            '@type'    => 'HealthAndBeautyBusiness',
            'name'     => $d['salonName'],
            'url'      => $d['canonical'],
            'image'    => $d['ogImage'],
            'address'  => array_filter([
                '@type'           => 'PostalAddress',
                'addressLocality' => $d['city'],
                'streetAddress'   => $d['address'],
                'addressCountry'  => 'XK',
            ]),
        ];
        if ($d['reviewCount'] > 0) {
            $ldData['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $reviewCount,
            ];
        }
        $jsonLd = json_encode(
            $ldData,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="sq" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <link rel="canonical" href="{$canonical}">

    <!-- Open Graph (Facebook, WhatsApp, LinkedIn, iMessage, Slack, Discord) -->
    <meta property="og:type" content="business.business">
    <meta property="og:site_name" content="Termini Im">
    <meta property="og:locale" content="sq_AL">
    <meta property="og:url" content="{$canonical}">
    <meta property="og:title" content="{$ogTitle}">
    <meta property="og:description" content="{$description}">
    <meta property="og:image" content="{$ogImage}">
    <meta property="og:image:secure_url" content="{$ogImage}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{$ogTitle}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@terminiim">
    <meta name="twitter:title" content="{$ogTitle}">
    <meta name="twitter:description" content="{$description}">
    <meta name="twitter:image" content="{$ogImage}">

    <!-- Theme + favicon (graceful when humans hit the page directly) -->
    <meta name="theme-color" content="#7A2232">
    <link rel="icon" type="image/png" href="/favicon.png">

    <!-- Schema.org -->
    <script type="application/ld+json">{$jsonLd}</script>

    <style>
        body { margin: 0; padding: 48px 24px; font-family: -apple-system, BlinkMacSystemFont, "Instrument Sans", sans-serif;
               background: #F7F2EA; color: #171311; text-align: center; }
        h1 { font-family: "Fraunces", Georgia, serif; font-weight: 400; letter-spacing: -0.02em; margin: 16px 0; }
        a  { color: #7A2232; text-decoration: none; font-weight: 600; }
        .hint { color: #716059; font-size: 14px; margin-top: 16px; }
    </style>

    <script>
        // Real visitors who somehow land here → SPA. Crawlers ignore JS.
        (function () {
            try { window.location.replace({$this->jsString($canonical)}); } catch (e) {}
        })();
    </script>
</head>
<body>
    <h1>{$salonName}</h1>
    <p>Po hapni faqen e këtij sallone në Termini Im…</p>
    <p class="hint">
        Nëse nuk ridrejtoheni automatikisht,
        <a href="{$canonical}">klikoni këtu</a>.
    </p>
</body>
</html>
HTML;
    }

    /**
     * Generic fallback when the company id is unknown. Same shape as the real
     * page so crawlers still get a usable card instead of a 404 stub.
     */
    private function renderFallback(): string
    {
        $canonical = $this->webUrl();
        $ogImage   = $canonical . '/og-default.png';

        return $this->renderTemplate([
            'title'       => 'Termini Im — Beauté & Style në Kosovë',
            'ogTitle'     => 'Termini Im — Rezervo online sallone bukurie në Kosovë',
            'description' => 'Zbulo dhe rezervo në sallonet më të mira të bukurisë në Kosovë. Termini Im — bukuria fillon me një takim.',
            'ogImage'     => $ogImage,
            'canonical'   => $canonical,
            'salonName'   => 'Termini Im',
            'city'        => 'Kosova',
            'address'     => '',
            'rating'      => 0.0,
            'reviewCount' => 0,
        ]);
    }

    /**
     * JSON-encode a string for safe embedding in inline JS.
     */
    private function jsString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
