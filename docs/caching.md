# Caching

## Content version

Every public read path (`EntryQuery`, menus, globals, the route matcher,
the sitemap) keys its cache as `sunrice:{version}:{locale}:{key}` where
`version` is a single global counter stored as `sunrice:content_version`.

Publishing, unpublishing, deleting or restoring an entry — and every
`ContentChanged` event (term, menu, global, asset, structure and
settings saves) — bumps the version, so all public keys become
unreachable at once. Works on every cache driver; no tags needed.

Draft saves never bump the version; previews bypass the cache entirely.

## Config

- `sunrice.cache.enabled` — master switch.
- `sunrice.cache.store` — cache store (default: app default).
- `sunrice.cache.ttl` — TTL seconds.
- `sunrice.cache.full_page` — enable the optional full-page cache.
  Requires `spatie/laravel-responsecache` (PHP 8.4+); when enabled the
  `CacheResponse` middleware wraps frontend routes and the whole page
  cache is cleared on every bump.
