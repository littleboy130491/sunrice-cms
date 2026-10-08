# Caching and performance

## Content version

Every public read path (`EntryQuery`, menus, globals, the route matcher,
the sitemap) keys its cache as `sunrice:{version}:{locale}:{key}` where
`version` is a single global counter stored as `sunrice:content_version`.

Publishing, unpublishing, deleting or restoring an entry — and every
`ContentChanged` event (term, menu, global, asset, structure and
settings saves) — bumps the version, so all public keys become
unreachable at once. Works on every cache driver; no tags needed.

Draft saves never bump the version; previews bypass the cache entirely.

Only plain values are cached (ids, arrays, strings), never models or
other objects, so the cache works with Laravel 13's default
`cache.serializable_classes = false`. Entry queries cache the matching
ids and load those entries by key.

## Config

- `sunrice.cache.enabled` — master switch.
- `sunrice.cache.store` — cache store (default: app default).
- `sunrice.cache.ttl` — TTL seconds.
- `sunrice.cache.full_page` — enable the optional full-page cache.
  Requires `spatie/laravel-responsecache` (PHP 8.4+); when enabled the
  `CacheResponse` middleware wraps frontend routes and the whole page
  cache is cleared on every bump.

## Testing with lots of content

Fill a staging site with fake articles, try the pages, then remove them:

```bash
php artisan sunrice:demo-content 1000          # or 10000
# open the printed pages, or time them:
curl -s -o /dev/null -w "%{time_total}s\n" https://staging.example.com/demo
php artisan sunrice:demo-content --remove
```

The articles live in their own `demo` collection (pages at `/demo/…`, a
listing at `/demo`, topics at `/demo_topics/…`), so real content and
menus are never touched. Measure on the server itself or with a load
tool (`ab`, `hey`, k6) against staging, not production. To see the
database queries behind a page, install
[Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) on
staging (`APP_DEBUG=true`), or [Telescope](https://laravel.com/docs/telescope).

For reference, with 10,000 articles (SQLite, PHP 8.4, no full-page cache,
default templates, median of 5 requests):

| Page | Time | Queries |
| --- | --- | --- |
| Article | ~10 ms | 16 |
| Listing (first or last page) | ~10 ms | 5 |
| Term page | ~23 ms | 13 |
| Search | ~26 ms | 9 |
| Admin: entries list / search | ~24 / ~37 ms | 11 |

Query counts don't grow with the amount of content or the number of cards
on a page (a test guards this). Settings are read once per request, and
listed entries share their collection and blueprint instead of loading
them one by one.
