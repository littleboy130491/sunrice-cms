# Changelog

## Unreleased

- **Starter templates** — `php artisan vendor:publish --tag=sunrice-templates`
  publishes a layout, header/footer, single, archive, term archive, an
  articles override and flexible-content block templates that show how to
  read entries, fields, assets, globals, menus, terms and forms.
- `Term::get()` reads taxonomy-blueprint fields, hydrated like entry fields.
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
