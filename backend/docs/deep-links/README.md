# Deep links — pointer vers le repo Flutter

> Cette documentation a été déplacée. La source de vérité (et les fichiers
> servis sur `https://www.termini-im.com/.well-known/`) vit maintenant dans
> le repo Flutter, où ils sont auto-déployés par le workflow CI :
>
> **`hairspot_mobile/docs/landing/.well-known/`**
>
> - `assetlinks.json` — Android App Links
> - `apple-app-site-association` — iOS Universal Links
> - `README.md` — instructions complètes (placeholders à remplacer, format,
>   validation, snippet Apache `<Location>` déjà appliqué sur le VPS)

## Pourquoi ?

Le déploiement de `www.termini-im.com` pousse `hairspot_mobile/docs/landing/`
sur `/var/www/termini-im/` via `rsync` (cf. `hairspot_mobile/.github/workflows/deploy.yml`).
Mettre les fichiers `.well-known/` ici les rend versionnés, auditables, et
auto-déployés à chaque push sur `main` côté Flutter.

L'autre alternative (les laisser uniquement dans le backend Laravel ou
sur le VPS à la main) garantirait l'oubli au prochain redéploiement complet
du SPA — exactement le bug qu'on a découvert le 2026-04-25.

## Côté Laravel — rien à faire ici

Le backend ne sert pas ces fichiers : ils sont sur l'origin `www.termini-im.com`,
pas `api.termini-im.com`. Le `ShareController` (toujours dans `app/Http/Controllers/`)
n'a aucune dépendance avec `.well-known/`.
