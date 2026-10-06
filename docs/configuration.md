# Configuration

All options live in `config/sunrice.php` (publish with
`php artisan vendor:publish --tag=sunrice-config`).

| Key | Default | Description |
| --- | --- | --- |
| `sunrice.admin.path` | `cms` | URL prefix of the admin panel (`SUNRICE_ADMIN_PATH`). |
| `sunrice.admin.domain` | `null` | Optional domain restriction for admin routes. |
| `sunrice.admin.middleware` | `['web']` | Middleware stack for admin routes. |
| `sunrice.auth.guard` | `web` | Guard used for admin login and permissions. |
| `sunrice.auth.user_model` | `App\Models\User` | Eloquent user model (must use `HasRoles`). |
| `sunrice.locales.main` | `id` | Main locale; unprefixed URLs resolve in it. |
| `sunrice.locales.available` | `['id']` | All locales. |
| `sunrice.locales.names` | `[]` | Display names keyed by locale. |
| `sunrice.frontend.enabled` | `true` | Mount the public catch-all + preview routes. |
| `sunrice.frontend.middleware` | `['web']` | Middleware stack for frontend routes. |
| `sunrice.cache.enabled` | `true` | Enable the content-version query cache. |
| `sunrice.cache.store` | `null` | Cache store name (`null` = default store). |
| `sunrice.cache.ttl` | `3600` | Seconds entries stay cached. |
| `sunrice.cache.full_page` | `false` | Full-page HTTP cache (requires `spatie/laravel-responsecache`, PHP 8.4+). |
| `sunrice.assets.optimize.*` | see [assets](assets.md#optimizing-images) | Defaults for `sunrice:optimize-images`. |
| `sunrice.seo.noindex` | `env('SUNRICE_NOINDEX', false)` | Add `noindex, follow` to every page (e.g. staging). |
| `sunrice.seo.twitter_site` | `env('SUNRICE_TWITTER_SITE')` | X/Twitter handle for `twitter:site`. |
| `sunrice.assets.disk` | `public` | Filesystem disk for the asset library. |
| `sunrice.assets.directory` | `sunrice` | Root directory inside the disk. |
| `sunrice.assets.max_upload_kb` | `20480` | Upload size limit in KB. |
| `sunrice.assets.image_sizes` | thumbnail/medium/large | Named derived sizes `{name: [width, height, mode]}` with mode `crop` or `fit`. |
| `sunrice.forms.upload_disk` | `local` | Private disk for form uploads. |
| `sunrice.forms.prune_after_days` | `null` | Days to keep submissions (`null` = keep forever). |
| `sunrice.forms.rate_limit` | `{attempts: 5, per_minutes: 1}` | Per-IP+form submission limit. |
| `sunrice.revisions.keep` | `50` | Revisions kept per entry translation. |
| `sunrice.super_admin_role` | `Super Admin` | Role that bypasses all permissions. |

## Honeypot

Form spam protection uses `spatie/laravel-honeypot`; its `honeypot.*`
config keys apply. Render `<x-honeypot />` inside your own forms.
