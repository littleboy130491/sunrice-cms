# Multilingual content

Configure locales in `sunrice.locales`: `main`, `available`, `names`.

## URLs

- Main-locale content lives at the unprefixed route: `/articles/hello`.
- Other locales get a prefix from `SetFrontendLocale`: `/en/articles/hello`.
- `<x-sunrice::seo>` emits `hreflang` alternates for main + every `Ready`
  translation, plus `x-default` pointing at main.

## Whole-entity fallback (never field mixing)

When the requested locale has no Ready translation, the whole main-locale
translation is served at the locale-prefixed URL, with `isFallback = true`
(canonical points at the main URL, no hreflang emitted). A missing or Draft
translation never produces a 404; the page follows the main entry's
publication status. This is not configurable.

A translation that exists but is not `Ready` is **not** served publicly,
and its slug is not routable: fallback pages use the main-language slug.

## Draft / Ready per translation

Each translation has `is_ready` (Draft/Ready). Ready translations are
publishable; publish copies the `draft` payload to the live columns and
records a `Revision`. `sunrice.revisions.keep` caps revisions per
translation.
