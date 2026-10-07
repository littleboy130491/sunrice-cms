# Changelog

## Unreleased

- **Two-factor login** — Settings → Security can require a 6-digit code,
  emailed after the password, for every admin login (off by default).
  Codes expire after 10 minutes; wrong guesses are limited.
  `php artisan sunrice:two-factor off` turns it off if mail stops working.
- **Test email** — Settings → Email shows the mail setup in use and sends
  a test email to any address, showing the server's error if it fails.
- **Docs in the admin** — Manage → Docs shows the developer guides
  (templates, artisan commands, languages, mail/SMTP and more) to users
  with the new `sunrice.docs.view` permission. New guides: artisan commands
  and mail (SMTP). Run `php artisan sunrice:sync-permissions` to create the
  permission.
- **Site settings** — Manage → Settings edits the site name, description,
  timezone, homepage, languages (main, available, names), site-wide
  noindex (also empties the sitemap), X/Twitter handle and default share
  image. Values override the config at boot.
- **Code snippets** — add tracking or other code to the head, body start
  or body end from Settings; layouts print them with
  `<x-sunrice::code position="head|body_start|body_end" />` (already in the
  starter layout and fallback views).
- Fixed: the asset field crashed once a single-asset field had a value.
- Menus: the item editor can now link to a collection archive (it was
  saved without its target and never showed), pick a term from a taxonomy
  (instead of typing an id), set a label per language (empty uses the linked
  title), and edit existing items. Targets are validated for their type.
- Taxonomies: attach collections from the taxonomy form, and switch term
  archive pages on there (there was no way to before). Term pages default
  to one per attached collection at `/{collection}/{taxonomy}/{slug}`,
  listing that collection's entries; a custom route gives one page across
  collections. `$term->urlIn($collection)` links to a collection's page.
- Collections: the route prefix field now takes a prefix (`blog` →
  `/blog/{slug}`), a full pattern, or `/` for the site root; left empty it
  follows the handle. Two collections can no longer share a URL pattern.
  Fixed: a plain prefix like `pages` made entries unreachable, and entry
  links used `/{slug}` while the router served `/{handle}/{slug}` when no
  route was set. Taxonomy routes accept prefixes the same way.
- Collections: choose the sidebar icon in the collection settings.
- **Granular permissions** — the `sunrice.manage-*` permissions are replaced
  by view/create/edit/delete permissions per area (collections, blueprints,
  fieldsets, taxonomies, menus, globals, users, roles), `forms.create` /
  `forms.delete` and `assets.edit`. `sunrice:sync-permissions` hands the new
  permissions to every role and user that held the old ones.
- **Translate permission** — `sunrice.entries.{id}.translate` allows editing
  an entry's other-language versions only (no main language, publishing or
  marking Ready).
- **Default roles** — `php artisan sunrice:seed-roles` (or
  `sunrice:install --roles`) creates Administrator, Editor, Author and
  Translator; rerun it after adding collections. Seeder:
  `Sunrice\Database\Seeders\RolesSeeder`.
- Fixed: users who could manage users could edit or delete super admins and
  hand out the super-admin role; role managers could rename or delete the
  super-admin role. Reordering collections had no permission check, and
  reordering entries or terms only needed view access (and could touch
  other collections' entries). The Taxonomies sidebar section never showed
  for non-super-admins.
- Fixed: `sunrice:install` failed on a fresh app because the
  spatie/laravel-permission tables were never created. It now publishes
  spatie's migration when the tables are missing, before migrating. It also
  no longer creates a super admin user that can't hold the Super Admin role
  (user model without `HasRoles`); it warns and skips instead.
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
