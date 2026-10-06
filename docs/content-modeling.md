# Content modeling

## Collections

A **collection** is a content type (`pages`, `articles`, `products`).
Settings:

- `route` — public URL template, e.g. `/articles/{slug}`.
- `archive_route` — archive URL when `has_archive` is on.
- `has_single`, `has_archive` — toggle detail/archive pages.
- `translatable`, `sluggable`, `dated`, `archivable`.
- `per_page` — archive pagination size.
- `template` — default Blade view for singles.
- `taxonomies` — attached taxonomy ids.

## Blueprints and fieldsets

**Blueprints** define the editable fields of a collection, taxonomy,
global or template part. **Fieldsets** are reusable groups of fields
included inside blueprints via a `fieldset` field.

Changing an entry's blueprint never deletes stored data — fields absent
from the active blueprint are hidden, not erased.

## Field types

`text`, `textarea`, `rich_text`, `number`, `toggle`, `select`, `date`,
`link`, `asset`, `entries`, `terms`, `group`, `repeater`, `flexible`,
`fieldset`, `file` (forms only).

Each field: `handle`, `type`, `label`, `required`, `instructions`,
`conditions` (`{field, operator, value}`), `validation` (extra Laravel
rules), `translatable` (see [multilingual](multilingual.md#which-fields-are-translatable)),
`config` (per-type options).

Repeater rows and flexible blocks each get an optional key and a Show
switch in the editor; templates read keyed items with `->byKey('key')`.

## Entries

- Whole-entity translations: each locale has its own `EntryTranslation`
  (title, slug, data, seo, ready flag, draft/live payloads).
- Drafts edit the `draft` column; publishing copies it to the live
  payload and creates a revision.
- `Draft`/`Ready` per translation controls which locales can be
  requested publicly.
- Slugs are unique per `(collection, locale)` including trashed rows;
  rename + publish leaves a `301` redirect on the old slug.

## Taxonomies

Hierarchical (optional) terms with per-locale `name` + `slug`; terms can
have their own blueprint, and a taxonomy `route` exposes term archive
pages. `EntryQuery::whereTerm()` optionally includes child terms.

## Menus

Menus contain nested items of type `entry`, `collection`, `term` or
`url`, with per-locale `labels`. `Sunrice::menu('main')` resolves item
URLs and drops unpublished targets.

## Globals and template parts

`globals` (group `global`) hold site-wide values; `template_parts`
(group `template_part`) hold header/footer-type blocks. Both read via
`Sunrice::global('handle')` / `sunrice_global('handle')`, honoring the
whole-entity fallback.
