# Deep links — App Links (Android) + Universal Links (iOS)

When a `https://www.termini-im.com/company/{id}` link is tapped on a phone:

| OS              | App installed                              | App not installed         |
|-----------------|--------------------------------------------|---------------------------|
| **Android 12+** | App opens directly (no picker, no browser) | Browser opens normally    |
| **iOS 9+**      | App opens directly                         | Safari opens normally     |

This requires three things to be in place: the app's intent-filters /
entitlements, **and** two domain-verification files served from
`https://www.termini-im.com/.well-known/`.

---

## File 1 — `assetlinks.json` (Android)

Final URL: `https://www.termini-im.com/.well-known/assetlinks.json`
Source template: `assetlinks.json` (this folder)

Replace `REPLACE_WITH_PLAY_APP_SIGNING_SHA256` with the **App signing key
SHA-256 fingerprint** from Play Console:

> Play Console → App → Setup → App integrity → "App signing" →
> App signing key certificate → SHA-256 certificate fingerprint

Format: 64 hex chars separated by colons, uppercase. Example:
`14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5`

> ⚠️  Use the **App signing key** SHA, not the Upload key SHA. Google
> re-signs your APK with the App signing key when distributing via Play,
> so it's the SHA users actually receive.

If the app is also distributed outside Play (e.g. signed APK for direct
install), add **both** SHA-256 fingerprints to the array:
```json
"sha256_cert_fingerprints": [
  "<PLAY_APP_SIGNING_SHA256>",
  "<DIRECT_RELEASE_SHA256>"
]
```

## File 2 — `apple-app-site-association` (iOS)

Final URL: `https://www.termini-im.com/.well-known/apple-app-site-association`
Source template: `apple-app-site-association` (this folder)

> ⚠️  No `.json` extension on the served file. iOS hard-codes the path
> `/.well-known/apple-app-site-association` and does not accept any
> variation.

Replace `REPLACE_WITH_TEAM_ID` with the **Apple Developer Team ID**:

> Apple Developer Portal → Membership → Team ID (10-char alphanumeric)

Final `appIDs` value should look like: `ABC1234DEF.com.terminiim.app`.

---

## Apache config to serve them correctly

The file extensions matter:
- `assetlinks.json` → must be served as `application/json`
- `apple-app-site-association` (no extension) → must be served as
  `application/json`, MUST NOT redirect, MUST be reachable on port 443
  with a valid TLS cert (no self-signed)

Add to the `www.termini-im.com` HTTPS vhost (`*-le-ssl.conf`):

```apache
# Deep link domain verification — App Links (Android) / Universal Links (iOS).
# These two files MUST be served as application/json with no redirect.
<Location "/.well-known/assetlinks.json">
    ForceType application/json
    Header set Cache-Control "public, max-age=3600"
</Location>

<Location "/.well-known/apple-app-site-association">
    ForceType application/json
    Header set Cache-Control "public, max-age=3600"
</Location>
```

The site root currently lives at `/var/www/termini-im/`. Create the
`.well-known` directory there and drop the two files in:

```bash
sudo mkdir -p /var/www/termini-im/.well-known
sudo cp /tmp/assetlinks.json /var/www/termini-im/.well-known/
sudo cp /tmp/apple-app-site-association /var/www/termini-im/.well-known/
sudo chown -R www-data:www-data /var/www/termini-im/.well-known
sudo chmod 644 /var/www/termini-im/.well-known/*
```

If the `.well-known/` path is currently caught by a Let's Encrypt
location block, make sure the `<Location>` directives above come AFTER
the `acme-challenge` rule, so cert renewal still works.

---

## Validation

### Android

```bash
# Verify the file is reachable and well-formed
curl -i https://www.termini-im.com/.well-known/assetlinks.json
# expect: HTTP/2 200, Content-Type: application/json, valid JSON

# Google's official validator (do this AFTER deploying the app to Play
# at least to internal track — Play needs to know the package exists):
# https://developers.google.com/digital-asset-links/tools/generator
```

After installing the app on a fresh device, confirm verification status:

```bash
adb shell pm get-app-links com.terminiim.app
# expect: "verified" next to www.termini-im.com
```

If you see `legacy_failure` or `verification_failure`, re-run:

```bash
adb shell pm verify-app-links --re-verify com.terminiim.app
```

### iOS

```bash
curl -i https://www.termini-im.com/.well-known/apple-app-site-association
# expect: HTTP/2 200, Content-Type: application/json, NO redirect
```

Apple's validator: https://search.developer.apple.com/appsearch-validation-tool/

On a TestFlight install, tap a `https://www.termini-im.com/company/<id>`
link in iMessage — if the app is configured correctly, the URL row will
show a small app icon button on the right and tapping the link opens the
app directly.

---

## Why both files matter

Without them, the manifest intent-filter and entitlements are inert: the
OS sees the declarations but skips the verification step. On Android
that means the user gets a "Open with…" picker every time. On iOS the
link silently opens in Safari with zero indication anything is wrong.
