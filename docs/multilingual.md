# Multilingual content

Configure locales in `sunrice.locales`: `main`, `available`, `names`.

## URLs

- Main-locale content lives at the unprefixed route: `/articles/hello`.
- Other locales get a prefix from `SetFrontendLocale`: `/en/articles/hello`.
- `<x-sunrice::seo>` emits `hreflang` alternates for main + every `Ready`
  translation, plus `x-default` pointing at main.

## Whole-entity fallback (never field mixing)

When the requested locale has no published translation:

- `collection.settings.fallback = main` — the main-locale translation is
  served, with `isFallback = true` (canonical points at the main URL, no
  hreflang emitted).
- `fallback = 404` — the page 404s (redirects are still checked first).

A translation that exists but is not `Ready` is **not** served publicly.

## Draft / Ready per translation

Each translation has `is_ready` (Draft/Ready). Ready translations are
publishable; publish copies the `draft` payload to the live columns and
records a `Revision`. `sunrice.revisions.keep` caps revisions per
translation.
