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
- template parts: like header, footer, etc.
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

## Confirmed behavior

### Package installation and integration

- Install Sunrice into an existing Laravel application through Composer.
- Provide a `sunrice:install` command to publish configuration and run the initial migrations.
- The default admin path is `/cms`, configurable by the host application.
- Ship compiled admin assets so installation does not require building React locally.
- Frontend Blade templates live in the host application.
- Support a configurable user model and authentication guard.
- Developers can register custom fields, model resources, and template selection hooks through service providers.

### Content configuration

- Administrators create and manage collections, blueprints, and fieldsets through the CMS admin.
- Each entry can have its own blueprint.
- Each collection has a default blueprint, which individual entries can override.
- Changing an entry's blueprint preserves existing field data. Fields absent from the active blueprint are hidden from the editing form, and switching back restores access to their saved values.
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

### Translations

- Translations can have separate slugs.
- Publishing schedules are shared with the original entry; translations do not have independent schedules.
- When a translation is not ready, display the whole entry's main-language content instead of returning a 404 because the translation is unavailable.
- Fallback applies to the entire entry, rather than individual fields, so partially completed translations do not produce mixed-language content.
- New translations start as Draft. An editor explicitly marks a translation Ready.
- The original entry controls publication status and scheduling for every language. A Ready translation appears when the original entry is published.
- Missing or Draft translations use the whole-entry main-language fallback. Returning a translation to Draft restores that fallback.

### Multilingual URLs

- Main-language URLs have no language prefix, such as `/articles/example`.
- Other languages use a language prefix, such as `/en/articles/translated-example`.
- A language-specific URL uses the translated slug when available; otherwise, it uses the original entry's slug.
- When a translation is missing or Draft, the language-specific URL displays the whole main-language entry, subject to the original entry's publication status.

### Fieldsets and flexible content

- A fieldset is a reusable group of fields, such as a Hero with a heading, image, and button.
- A blueprint can include a fieldset directly or contain a flexible content field.
- A flexible content field lets editors add, repeat, and reorder blocks from its allowed fieldsets.
- Each block stores its own field values. Frontend templates render the blocks in their saved order.
- For example, a page's Sections field can contain Hero, Text, Gallery, and another Text block.

### Rich text editing

- Rich text fields use a visual editor.

### Editing and publication workflow

- Saving draft changes to a published entry preserves the currently published version on the public website.
- Preview displays draft changes before publication.
- Publishing makes draft changes live, subject to the user's publishing permission and the entry's publication schedule.
- Revision history allows editors to restore an earlier version as a draft. Restoring a revision does not immediately change the live entry.

### Caching

- Content/query caching is enabled by default. Full-page caching is optional.
- Cache public entry queries separately by language, filters, sorting, and pagination.
- Refresh affected caches when published content, navigation, globals, or assets change.
- Saving draft changes keeps the published content cache intact.
- Draft previews bypass public caches.
- Scheduled publication refreshes affected caches when content goes live.
- Developers can configure cache storage and duration.

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

- Template parts contain editable content only.
- Blade files render the editable content supplied by template parts.

### Globals

- Globals are named, blueprint-driven groups of site-wide content, managed through the admin and retrieved by name in Blade templates.
- Each global group can use values shared across languages or support per-language content.
- Examples include site identity, contact details, and social links.

### Assets

- Provide one shared asset library with folders, search, and filtering.
- Editors can upload directly from image/file fields or select existing assets.
- Store files using a configurable Laravel storage disk.
- Support metadata such as alt text, captions, and titles.
- Generate image sizes for thumbnails and frontend use.
- Reference assets by ID so replacing a file preserves its content references.
- Track where assets are used.
- Manual cropping and focal point support remain undecided.

### Users, roles, and permissions

- Administrators can create users and assign roles through the CMS admin.
- Administrators can create custom roles and customize their authority.
- Permissions can be scoped to individual collections, taxonomies, forms, and other CMS resources.
- Spatie's `spatie/laravel-permission` is the proposed foundation for roles and permissions, integrated with Laravel authorization.
- Permission enforcement applies to server-side actions as well as the admin interface.
- Roles can distinguish editing a user's own entries from editing all entries in an authorized collection.
- Entry ownership identifies the author used for own-entry permission checks. Editing an entry does not change its ownership.
- For example, an Author role can edit its users' own entries while an Editor role can edit everyone's entries in the permitted collections.
- The complete action-level permission matrix and which additional actions support ownership restrictions still need to be defined.

### Frontend rendering and template selection

- Inertia.js and React power the CMS admin.
- Blade templates render the public website.
- Entry template priority: entry-specific override, then the collection's configured template, then the convention-based default.
- Archive template priority: the collection's configured archive template, then the convention-based default.

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
- Built-in spam protection includes a honeypot and rate limiting.

## Translation package recommendation

- Recommended candidate, pending final selection: `astrotomic/laravel-translatable` with a compatible stable 11.17.x release. Released version 11.17.1 explicitly allows Laravel 13 dependencies and is MIT-licensed. See its [released Composer manifest](https://github.com/Astrotomic/laravel-translatable/blob/v11.17.1/composer.json).
- Astrotomic uses separate translation models and tables, matching the agreed storage architecture. See the [installation documentation](https://docs.astrotomic.info/laravel-translatable/installation).
- Proposed Sunrice integration: create one shared entry translation table during initial installation, including main-language records from the start. Store custom field values in a JSON payload on each translation record. Adding collections, fields, or languages then requires no new migrations.
- Sunrice must implement its Draft/Ready workflow, shared publication control, localized URL resolution, and revision handling around the package.
- Public rendering selects the active language's published Ready translation, or the entire published main-language record. Per-field fallback must be disabled for CMS rendering to preserve whole-entry fallback. See the [fallback documentation](https://docs.astrotomic.info/laravel-translatable/package/fallback-locale).
- Alternative evaluated: `spatie/laravel-translatable` stores locale values inside JSON attributes. It supports Laravel 13, but Astrotomic is the closer fit for Sunrice's separate translation records and per-translation workflow. See [Spatie's storage setup](https://github.com/spatie/laravel-translatable/blob/main/docs/installation-setup.md) and its [released Composer manifest](https://github.com/spatie/laravel-translatable/blob/6.14.1/composer.json).
