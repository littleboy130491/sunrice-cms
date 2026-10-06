# Changelog

## Unreleased

- **Shared layout, translated text** — secondary languages now store only
  their translated text and render inside the main language's layout, so
  adding, reordering or hiding blocks and swapping images happens once for
  every language. Fields have a **Translatable** switch in the blueprint
  builder (text types on by default); shared fields are read-only when
  editing a translation. Run `php artisan sunrice:upgrade-translations` once
  to convert existing translations (old ones still render meanwhile).
- Repeater rows and flexible blocks get an optional **key** and a **Show**
  switch: hidden items are skipped on the site, and templates can fetch an
  item with `$entry->get('sections')->byKey('hero')`. Rows now carry a
  stable `_id`.
- Fixed: the blueprint builder stored group/repeater children and flexible
  block types where the server didn't read them, type settings (max, min,
  multiple…) didn't save, select fields had no options setting, and the
  entry editor never received the fieldsets of flexible fields.
- Fixed: preview rendered the draft's top-level keys as field data and
  showed the main language for translations that weren't Ready yet.

- **Starter templates** — `php artisan vendor:publish --tag=sunrice-templates`
  publishes a layout, header/footer, single, archive, term archive, an
  articles override and flexible-content block templates that show how to
  read entries, fields, assets, globals, menus, terms and forms.
- `Term::get()` reads taxonomy-blueprint fields, hydrated like entry fields.
- Menus: the active item is worked out per request instead of being cached
  with the menu, and home links (`/`, `/en`) only match their own page.
- SEO: per-entry "Hide from search engines" (robots `noindex`, also removed
  from the sitemap), a site-wide `SUNRICE_NOINDEX` switch, Twitter card tags,
  `og:type`/`og:site_name`, and absolute canonical, `og:url`, hreflang and
  sitemap URLs.
- `sunrice:translate` machine-translates entries, terms, globals, menu labels
  and Laravel language files with Gemini (default) or OpenRouter. Only
  missing translations are filled in unless `--force`; entry translations
  are saved as drafts for review.
- `sunrice:optimize-images` resizes and recompresses image assets in place
  (defaults in `sunrice.assets.optimize`, overridable per run), backs up the
  originals to the private `local` disk by default and can `--restore` them.
- Admin password reset emails now link to the CMS reset page instead of the
  host app's `password.reset` route.
- The package's fallback `show` view no longer prints plain-text fields
  unescaped.
- **Admin UI refresh** — components regenerated with the official shadcn/ui
  CLI (new-york, Tailwind 4) and the shell rebuilt on the layout patterns of
  Laravel's React starter kit: collapsible inset sidebar with icons and a
  mobile drawer, user menu with light/dark/system appearance, breadcrumbs,
  Instrument Sans, and a redesigned login, dashboard, data table and entry
  editor.

## 1.0.0

Initial release of Sunrice CMS — a Laravel package CMS with:

- **Content domain** — collections, blueprints, fieldsets, entries with
  whole-entity translations, Draft/Ready per translation, draft/publish
  with revisions, scheduled publishing, slugs + automatic redirects,
  trash/restore, taxonomies (hierarchical terms), menus, globals and
  template parts, asset library with image sizes and usage tracking,
  public forms with honeypot/rate-limit/upload handling and pruning.
- **Admin** — Inertia + React + Tailwind panel at `/cms`: structure
  manager (collections/blueprints/fieldsets), entry editor with
  conditional fields, taxonomy/menu/global editors, form builder,
  submissions browser + CSV export, asset library with drag-drop
  upload, users/roles/permissions, settings, model resources for
  host-app models, localized UI, table preferences.
- **Frontend** — Blade rendering with template hierarchy +
  `resolveTemplateUsing` hooks, `<x-sunrice::entries>`,
  `<x-sunrice::seo>` (canonical/hreflang/OG), `<x-sunrice::form>`,
  signed draft previews, sitemap.xml, per-locale URLs with whole-entity
  fallback.
- **Platform** — permission system with entity scopes, content-version
  caching with automatic invalidation, optional full-page cache,
  `sunrice:install` / `sync-permissions` / `regenerate-images` /
  `publish-assets` / `rename-field` / `publish-scheduled` commands.

## Unreleased
