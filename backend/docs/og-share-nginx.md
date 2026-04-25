# Open Graph SSR — Nginx routing

When a salon link `https://www.termini-im.com/company/{id}` is shared on
Facebook, WhatsApp, Twitter, LinkedIn, iMessage, Slack or Discord, the
crawler **does not execute JavaScript** — it reads only the static `<head>`
tags of `index.html`. The Flutter Web SPA cannot dynamically inject Open
Graph tags fast enough to satisfy these crawlers.

## Architecture

- Real users hit `/company/{id}` → Nginx serves the Flutter SPA (`index.html`).
- Crawlers hit `/company/{id}` → Nginx detects the User-Agent and reverse-
  proxies to `https://api.termini-im.com/share/company/{id}`, which returns
  HTML with proper `og:*`, `twitter:*` and JSON-LD tags.

The Laravel endpoint lives in `app/Http/Controllers/ShareController.php` and
is registered in `routes/web.php` as `share.company`.

## Nginx snippet

Drop this inside the `server { }` block of `www.termini-im.com` (the Flutter
Web vhost). Adjust `proxy_pass` if the Laravel API runs on a different host.

```nginx
# ---------------------------------------------------------------------------
# Open Graph SSR for /company/{id}
# Detect known social-media crawlers and reverse-proxy them to the Laravel
# share endpoint, which returns HTML with proper og:* / twitter:* tags.
# ---------------------------------------------------------------------------

# 1) UA matcher — set $is_social_crawler to "1" for known bots.
map $http_user_agent $is_social_crawler {
    default                              0;
    "~*facebookexternalhit"              1;
    "~*Facebot"                          1;
    "~*WhatsApp"                         1;
    "~*Twitterbot"                       1;
    "~*LinkedInBot"                      1;
    "~*Slackbot"                         1;
    "~*Slack-ImgProxy"                   1;
    "~*TelegramBot"                      1;
    "~*Discordbot"                       1;
    "~*Pinterest"                        1;
    "~*SkypeUriPreview"                  1;
    "~*Google-PageRenderer"              1;
    "~*Googlebot"                        1;
    "~*bingbot"                          1;
    "~*Applebot"                         1;
    "~*iMessageLinkPreview"              1;
    "~*redditbot"                        1;
    "~*Embedly"                          1;
    "~*Mastodon"                         1;
    "~*viber"                            1;
    "~*okhttp"                           1;  # generic preview fetchers
}

server {
    server_name www.termini-im.com;

    # … your existing TLS / root / Flutter SPA blocks …

    # /company/{id} — bots get the Laravel SSR stub, humans get the SPA.
    # The location uses a regex so we capture the salon id and any optional
    # ?employee=<eid> query string is forwarded as-is via $request_uri.
    location ~ ^/company/(?<company_id>[A-Za-z0-9_-]+)/?$ {
        if ($is_social_crawler = 1) {
            # Strip query params from the upstream URI but pass them through
            # by using $is_args$args. Keep the trailing slash off so the
            # Laravel route matches /share/company/{id} exactly.
            proxy_pass         https://api.termini-im.com/share/company/$company_id$is_args$args;
            proxy_set_header   Host              api.termini-im.com;
            proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
            proxy_set_header   X-Forwarded-Proto $scheme;
            proxy_set_header   X-Real-IP         $remote_addr;
            proxy_ssl_server_name on;
            proxy_intercept_errors off;
            break;
        }

        # Real users — fall back to the Flutter SPA. `try_files` is a
        # convention; adapt to whatever pattern your existing config uses.
        try_files $uri $uri/ /index.html;
    }
}
```

## Testing

Verify the SSR endpoint directly:

```bash
curl -A 'facebookexternalhit/1.1' -i https://www.termini-im.com/company/<id>
# expect: 200, Content-Type text/html, og:* meta tags in body
```

Verify the SPA still serves real users:

```bash
curl -A 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15' \
     -i https://www.termini-im.com/company/<id>
# expect: 200, the Flutter index.html shell
```

Test the Laravel endpoint in isolation:

```bash
curl -i https://api.termini-im.com/share/company/<id>
# expect: 200, full meta-tag HTML
```

## Validating with crawler tools

After deploy, validate the live unfurl with the official debuggers:

- **Facebook**: https://developers.facebook.com/tools/debug/
- **Twitter (X)**: https://cards-dev.twitter.com/validator
- **LinkedIn**: https://www.linkedin.com/post-inspector/
- **WhatsApp**: just paste the link in a chat with yourself

If you change the Laravel template after a debugger has cached a preview,
hit "Scrape Again" in the Facebook debugger to bust their cache.

## OG default image

Place a 1200×630 PNG at `web/og-default.png` in the Flutter repo (and at
`public/og-default.png` in the Laravel repo if Laravel ever needs to serve
it directly). The image should be the editorial Termini Im logo on the
ivory `#F7F2EA` background — same look as the splash screen.

## Caching

`ShareController` caches each rendered page for 1 hour via Laravel's cache
layer (`Cache::remember`). When a salon updates its name / photo / services,
flush the relevant key (`share.company.{id}.none`) — or just wait for the
TTL to expire.

If you add a CDN in front (Cloudflare, Bunny), the `Cache-Control` header
already advertises `s-maxage=3600`, so the edge will cache the response too.

## Adding the WEB_URL env var

The controller reads `env('WEB_URL', 'https://www.termini-im.com')` for the
canonical link. Add it to `.env` if your production URL ever changes:

```
WEB_URL=https://www.termini-im.com
```
