this is a CMS built as a laravel package.
the stack is:

- laravel 13 (initial supported version)
- PHP 8.3 or newer
- database: sqlite, mysql, or postgresql
- inertia js
- react
- tailwind css
- shadcn

i want you to use the open source packages or plugins that is robust, reputable, well maintained. don't try to reinvent the wheel or build everything from scratch.

the philosophy:
easy to setting and extend CMS, similar like WordPress ACF and statamic.
no need to create new migration for content management or new fields.
but still easy to extend if we use new model from laravel, ex: in laravel we create new model and we can attach it easily in the CMS nav and query the record data. (similar like filament)

the important parts:

- collections: similar like custom post in WordPress
- entry: each record
- taxonomy
- blueprints: similar like Statamic, or fields in WordPress
- collection_settings: user can put the fields for the archive page, and can be queried in archive page. also general settings like slug, translatable, has archive has single, the templates it will use.
- navigation
- globals
- assets (like file manager)
- forms
- template parts: like header, footer, etc. (implemented as globals; see Globals)
- template selection hooks
- users, roles, and permissions
- fieldsets: where we can put the flexible fields (like in ACF), then just attach it to specific collections or entry

features:

- SEO friendly, meta title, description, canonical, OG
- translatable: not sure which laravel package that we need to use, i want something that easy to use, and no need to alter database when user choose to use multilanguage site from single language site.
- trash, soft delete
- sorting records
- status draft
- scheduled posting
- export data to CSV.
- table filtering, search, sort. also user can define the columns in the table.
- caching to optimize page performance.

templates:

- has default convention, user can define the template in each collection and each entry to override.

## Design principle

Prefer the simplest implementation that is good enough, especially for features that are not core. Avoid adding packages or workflow states when a column or a small helper does the job. Items listed under "Out of scope for v1" are deliberately deferred.

## Confirmed behavior

### Package installation and integration

- Install Sunrice into an existing Laravel application through Composer.
- Provide a `sunrice:install` command to publish configuration and run the initial migrations.
- The default admin path is `/cms`, configurable by the host application.
- Ship compiled admin assets so installation does not require building React locally.
- Frontend Blade templates live in the host application.
- Support a configurable user model and authentication guard.
- Developers can register custom fields, model resources, and template selection hooks through service providers.

### Admin isolation from the host application

- All admin routes live under the admin path and use Sunrice's own middleware (`HandleSunriceInertiaRequests`) with its own root view (`sunrice::app`).
- Compiled admin assets are published to `public/vendor/sunrice` with their own Vite manifest. The Inertia asset version is the manifest hash.
- The admin does not use server-side rendering.
- Because Inertia is scoped to the admin routes, a host application that also uses Inertia (with any frontend framework) is unaffected.

### Custom fields

- The admin ships a fixed set of built-in field types. Developers never write JavaScript for the admin.
- A custom field is a PHP class that wraps one built-in field type and adds preset configuration, validation rules, and an optional value cast.
- Custom fields are registered through a service provider and appear in the blueprint editor like built-in types.

### Field types (v1)

- Text, Textarea, Rich text, Number, Toggle, Select (single or multiple), Date / Datetime, Link, Asset, Entries (relationship), Terms, Group, Repeater, Flexible content, and Fieldset include.
- Group, Repeater, and Flexible content can be nested; the admin renders nested fields recursively with the same field components.

### Content configuration

- Administrators create and manage collections, blueprints, and fieldsets through the CMS admin.
- Each entry can have its own blueprint.
- Each collection has a default blueprint, which individual entries can override.
- Changing an entry's blueprint preserves existing field data. Fields absent from the active blueprint are hidden from the editing form, and switching back restores access to their saved values.
- Field data is keyed by field handle. Renaming a field handle follows the same rule: existing data is preserved but hidden, and renaming back restores it. A `sunrice:rename-field` command moves stored data when a rename is intended.
- Developer-written code primarily focuses on frontend HTML templates.

### Existing Laravel model integration

- Developers register existing Laravel models through a small resource definition.
- A resource defines its navigation, editing fields, table columns, filters, and sorting.
- Resource fields map to the model's existing attributes and relationships.
- Registered resources support full CRUD: create, view, edit, and delete records through the CMS admin.
- Resources use developer-defined validation rules and honor existing model authorization policies.
- Administrators assign resource access through the role editor.
- Existing models retain their own database schemas. Migration-free custom content fields apply to CMS-managed collections.

### Content storage

- Package migrations create a fixed set of CMS tables.
- Collections, blueprints, and fieldsets are stored in the database and managed through the admin.
- Standard entry attributes, including identity, collection, ownership, and publication state, use regular columns.
- Custom field values use JSON.
- Translations have separate records linked to the original entry and share its publication control.
- Relationships use explicit record references.
- Drafts and revisions preserve versioned content.
- Adding collections, changing fields, or enabling additional languages requires no new migrations.

Core tables (indicative):

- `entries`: `id`, `collection_id`, `blueprint_id` (nullable override), `author_id`, `status` (`draft` | `published`), `published_at`, `template` (nullable override), `sort_order`, `parent_id` (reserved), timestamps, `deleted_at`.
- `entry_translations`: `id`, `entry_id`, `collection_id` (denormalized for slug uniqueness), `locale`, `title`, `slug`, `data` (JSON, live), `draft` (JSON, nullable working copy of title, slug, data, and SEO), `is_ready`, `content_published_at` (when this translation's content was last published; used for the "Outdated" badge, not for scheduling), timestamps. Unique `(entry_id, locale)` and `(collection_id, locale, slug)`. The main language is also a row in this table.
- `revisions`: `id`, `entry_translation_id`, `user_id`, `content` (JSON snapshot), `created_at`.
- `sunrice_references`: `source_type`, `source_id`, `target_type`, `target_id`, `field_path`.
- `redirects`: `old_path`, `locale`, `entry_id`, timestamps.

### References

- On every save, Sunrice rebuilds the `sunrice_references` rows for the saved record from its field definitions (asset, entries, terms, and link fields) and from `data-asset-id` attributes inside rich text.
- The references table powers asset usage tracking and reverse-relationship queries.

### Translations

- Translations are a plain `hasMany` relationship (`EntryTranslation`) owned by Sunrice. No third-party translation package is used.
- Translations can have separate slugs.
- Publishing schedules are shared with the original entry; translations do not have independent schedules.
- When a translation is not ready, display the whole entry's main-language content instead of returning a 404 because the translation is unavailable.
- Fallback applies to the entire entry, rather than individual fields, so partially completed translations do not produce mixed-language content.
- A single resolver, `$entry->resolveFor($locale)`, returns the Ready translation for the locale or the main-language translation. All public rendering and queries go through it.
- New translations start as Draft. An editor explicitly marks a translation Ready.
- Marking a translation Ready publishes its draft content to its live columns and sets `is_ready`. Returning it to Draft clears `is_ready`, which restores the main-language fallback.
- Each translation has its own draft and live content, using the same draft workflow as the main language.
- The original entry controls publication status and scheduling for every language. A Ready translation appears when the original entry is published.
- Missing or Draft translations use the whole-entry main-language fallback. Returning a translation to Draft restores that fallback.
- When the main language was published more recently than a Ready translation, the admin shows an "Outdated" badge on that translation. No further workflow is attached.

### Multilingual URLs

- Main-language URLs have no language prefix, such as `/articles/example`.
- Other languages use a language prefix, such as `/en/articles/translated-example`.
- A language-specific URL uses the translated slug when available; otherwise, it uses the original entry's slug.
- When a translation is missing or Draft, the language-specific URL displays the whole main-language entry, subject to the original entry's publication status.

### Routing

- Each collection defines a URL pattern in its settings, such as `/articles/{slug}`, or `/{slug}` for pages.
- Sunrice registers one catch-all frontend route after the host application's routes. It can be disabled in configuration.
- A site setting selects the homepage entry.
- Page URLs are flat in v1; nested page URLs are out of scope.
- Slugs that match a configured locale code or the admin path are rejected on save.
- When a request does not match an entry, the `redirects` table is checked before returning a 404.

### Fieldsets and flexible content

- A fieldset is a reusable group of fields, such as a Hero with a heading, image, and button.
- A blueprint can include a fieldset directly or contain a flexible content field.
- A flexible content field lets editors add, repeat, and reorder blocks from its allowed fieldsets.
- Each block stores its own field values. Frontend templates render the blocks in their saved order.
- For example, a page's Sections field can contain Hero, Text, Gallery, and another Text block.

### Rich text editing

- Rich text fields use a visual editor (TipTap).
- Rich text is stored as HTML and sanitized on save with `symfony/html-sanitizer`.
- Images inserted in rich text carry a `data-asset-id` attribute for usage tracking.

### Editing and publication workflow

- Saving draft changes to a published entry preserves the currently published version on the public website.
- Save draft writes only the translation's `draft` column. The public website never reads `draft`.
- Preview displays draft changes before publication, rendering from `draft` and falling back to the live content.
- Publishing makes draft changes live, subject to the user's publishing permission and the entry's publication schedule. Publishing copies `draft` into the live columns, clears `draft`, and stores a revision.
- Revision history allows editors to restore an earlier version as a draft. Restoring a revision copies it into `draft` and does not immediately change the live entry.

### Status and scheduling

- An entry has a `status` of `draft` or `published` and a `published_at` date.
- A published entry with a future `published_at` is scheduled. Public queries require `status = published` and `published_at <= now`, so content goes live without a job changing its status.
- v1 supports a publish date only. Expiry/unpublish dates and scheduled updates to already-live content are out of scope.

### Trash and soft delete

- Entries, taxonomy terms, assets, and form submissions use soft deletes.
- Blueprints, fieldsets, menus, and globals are deleted permanently, and deletion is blocked while they are in use.
- Slug uniqueness includes trashed entries, so restoring an entry never causes a slug conflict.

### Sorting

- Manually ordered records use a `sort_order` column updated by a drag-and-drop reorder endpoint.
- Hierarchies (taxonomy terms, menu items) use `parent_id` and `sort_order`, and trees are assembled in PHP.

### Caching

- Content/query caching is enabled by default. Full-page caching is optional.
- Cache public entry queries separately by language, filters, sorting, and pagination.
- Invalidation uses a single global `content_version` number included in every public cache key. Publishing content or changing navigation, globals, or assets increments it. This works on every cache driver and needs no cache tags.
- Saving draft changes keeps the published content cache intact.
- Draft previews bypass public caches.
- A `sunrice:publish-scheduled` command, run every minute by the Laravel scheduler, increments `content_version` when scheduled content goes live. If the scheduler is not running, the configured cache duration (default 1 hour) bounds the delay.
- Full-page caching uses `spatie/laravel-responsecache` and is fully cleared whenever `content_version` changes.
- Developers can configure cache storage and duration.

### Custom field querying

- Filtering and sorting on custom fields use JSON queries and are supported for scalar fields (text, number, date, toggle, select).
- A small helper applies database-specific casts for numeric and date sorting across SQLite, MySQL, and PostgreSQL.
- JSON fields are not indexed. This is acceptable for small and medium sites and is documented as a known limit.

### Taxonomies

- Administrators create taxonomies such as Categories, Tags, and Topics.
- A taxonomy can be shared across multiple collections.
- Each taxonomy can be flat or hierarchical, with parent/child terms.
- Terms have names, slugs, and a taxonomy blueprint for additional fields such as descriptions and images.
- Entries can have multiple terms.
- A taxonomy can enable public term archives and choose their Blade template.

### Navigation

- Administrators create named menus such as Main Navigation and Footer Navigation.
- Menu items support nested levels and drag-and-drop ordering.
- Items can link to entries, collection archives, taxonomy terms, or custom URLs.
- Internal links reference CMS records so URLs follow slug changes and the active language.
- Editors can customize labels, including translated labels.
- Blade templates retrieve menus by name and control their HTML.
- Automatically generated menus are outside the current scope.

### Template parts

- Template parts (header, footer, etc.) are implemented as globals and listed under their own heading in the admin.
- Template parts contain editable content only.
- Blade files render the editable content supplied by template parts, retrieved like any global, e.g. `sunrice_global('header')`.

### Globals

- Globals are named, blueprint-driven groups of site-wide content, managed through the admin and retrieved by name in Blade templates with `sunrice_global('name')`.
- Each global group can use values shared across languages or support per-language content.
- Examples include site identity, contact details, social links, and template parts such as header and footer.

### Assets

- Provide one shared asset library with folders, search, and filtering.
- Editors can upload directly from image/file fields or select existing assets.
- Store files using a configurable Laravel storage disk.
- Support metadata such as alt text, captions, and titles.
- Generate image sizes for thumbnails and frontend use. Sizes are defined in configuration and generated on upload by a queued job using `intervention/image` v3.
- Reference assets by ID so replacing a file preserves its content references. Replacing a file keeps its storage path, so URLs inside rich text remain valid.
- Track where assets are used, via the references table.
- Manual cropping and focal points are out of scope for v1; generated sizes use a center crop.

### Users, roles, and permissions

- Administrators can create users and assign roles through the CMS admin.
- Administrators can create custom roles and customize their authority.
- Permissions can be scoped to individual collections, taxonomies, forms, and other CMS resources.
- `spatie/laravel-permission` is the foundation for roles and permissions, integrated with Laravel authorization. If the host application already uses it, Sunrice reuses its configuration. Its "teams" mode is not supported.
- Permission enforcement applies to server-side actions as well as the admin interface.
- Roles can distinguish editing a user's own entries from editing all entries in an authorized collection.
- Entry ownership identifies the author used for own-entry permission checks. Editing an entry does not change its ownership.
- For example, an Author role can edit its users' own entries while an Editor role can edit everyone's entries in the permitted collections.

Permission matrix:

- All Sunrice permission names are prefixed with `sunrice.` and reference resources by ID rather than handle, e.g. `sunrice.entries.{collectionId}.edit`, so renaming a collection does not break permissions.
- Per collection: `view`, `create`, `edit`, `edit-own`, `delete`, `delete-own`, `publish`.
- Per taxonomy and per registered model resource: `view`, `create`, `edit`, `delete`.
- Per form: `view-submissions`, `export-submissions`, `delete-submissions`, `edit` (form builder).
- Global: `manage-structure` (collections, blueprints, fieldsets, taxonomies setup), `manage-users`, `manage-roles`, `manage-settings`, `manage-navigation`, `manage-globals`, `assets.view`, `assets.upload`, `assets.delete`.
- A Super Admin role bypasses all checks via `Gate::before`.

### Frontend rendering and template selection

- Inertia.js and React power the CMS admin.
- Blade templates render the public website.
- Entry template priority: entry-specific override, then the collection's configured template, then the convention-based default.
- Archive template priority: the collection's configured archive template, then the convention-based default.

Convention-based defaults:

- Entry: `sunrice/{collection}/show.blade.php`, then `sunrice/show.blade.php`.
- Collection archive: `sunrice/{collection}/index.blade.php`, then `sunrice/index.blade.php`.
- Term archive: `sunrice/taxonomies/{taxonomy}/show.blade.php`, then `sunrice/taxonomies/show.blade.php`.
- Templates receive `$entry` (or `$term`), `$collection`, and `$locale`.

### SEO

- Entries support meta title, description, canonical URL, and Open Graph fields.
- Ready translations emit hreflang alternates.
- Fallback pages (a language URL showing main-language content) set their canonical to the main-language URL and are excluded from hreflang and the sitemap.
- A sitemap is generated with `spatie/laravel-sitemap` and cached.
- When the slug of a published entry changes, a `redirects` row is created so the old URL returns a 301 redirect.

### Template selection hooks

- The initial hook scope is limited to customizing Blade template selection.
- A template selection hook receives the normally resolved template and rendering context, including the page type, relevant content records, and active language.
- The hook can return an alternative Blade view name after normal template selection has resolved entry overrides, collection settings, or convention-based defaults.
- Developers register template selection hooks through a service provider.
- Lifecycle hooks and query extension hooks are outside the initial scope.

### Blade entry-query component

- Provide a custom Blade component that queries collection entries directly from frontend templates.
- The component belongs to the Laravel package and is registered by the package's service provider under the Sunrice namespace: `<x-sunrice::entries>`.
- Developers select the collection and configure pagination, including whether to paginate and the number of entries per page.
- Support custom-field filters, taxonomy filters, ordering, and a result limit for non-paginated queries.
- Each paginated component can use an independent page parameter so multiple lists on one page do not interfere with each other.
- Developers supply their own HTML skin through the component's slot, including the loop over the query results and placement of pagination controls.
- Query results and pagination controls must be accessible to the skin.
- Public queries follow the entry publication rules and the active language's whole-entry translation fallback.
- The slot accesses the result collection or paginator through `$component->entries`.
- Translations are eager loaded by default. A `with` attribute (e.g. `with="terms,assets"`) eager loads additional relations to avoid N+1 queries.

Example frontend usage:

```blade
<x-sunrice::entries collection="articles" :paginate="true" :per-page="12">
    @foreach ($component->entries as $entry)
        <article>
            <a href="{{ $entry->url }}">{{ $entry->title }}</a>
        </article>
    @endforeach

    {{ $component->entries->links() }}
</x-sunrice::entries>
```

### Forms

- Forms are public submission forms.
- Administrators build forms using the CMS field definitions and validation system.
- Blade templates render forms, and submissions are validated on the server.
- Submissions are stored in the database and managed in an admin table with search, filtering, and CSV export.
- Each form can optionally send email notifications to configured recipients.
- Built-in spam protection includes a honeypot (`spatie/laravel-honeypot`) and Laravel rate limiting. CAPTCHA is out of scope for v1.
- File upload fields store files on a private disk, separate from the asset library.
- Submissions can be pruned automatically after a configurable number of days using Laravel's `MassPrunable`. Pruning is off by default.

### CSV export

- CSV exports are streamed with `spatie/simple-excel`, so large exports do not require a queue.

## Packages

| Need | Package |
| --- | --- |
| Roles and permissions | `spatie/laravel-permission` |
| Image sizes | `intervention/image` v3 |
| CSV export | `spatie/simple-excel` |
| Honeypot | `spatie/laravel-honeypot` |
| Full-page cache | `spatie/laravel-responsecache` |
| Sitemap | `spatie/laravel-sitemap` |
| HTML sanitizing | `symfony/html-sanitizer` |
| Admin tables | TanStack Table |
| Drag-and-drop | dnd-kit |
| Rich text editor | TipTap |
| Package testing | Orchestra Testbench |

Confirm that each package's current release declares Laravel 13 support before adding it.

Deliberately not used:

- `astrotomic/laravel-translatable` and `spatie/laravel-translatable`: Sunrice stores translations as its own `entry_translations` records with whole-entry fallback, Draft/Ready state, per-locale drafts, and localized slugs. A plain relationship is simpler than working around a package's per-attribute fallback.
- `kalnoy/nestedset` and `spatie/eloquent-sortable`: `parent_id` and `sort_order` columns are enough for the small trees and lists Sunrice manages.
- `spatie/laravel-medialibrary`: it is designed around files attached to a model, not a shared asset library with folders.

## Out of scope for v1

- Admin JavaScript plugins (custom fields are PHP-only compositions of built-in types)
- Headless/REST API
- Frontend search
- Multisite
- Nested page URLs
- Expiry/unpublish dates and scheduled updates to live content
- Manual image cropping and focal points
- Automatically generated menus
- Lifecycle and query extension hooks
- CAPTCHA for forms
