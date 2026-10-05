# Sunrice CMS — Execution Plan

This plan turns `SPEC.md` into a working Laravel package. It is written for AI coding agents. Each task lists its dependencies, the files to create or change, what those files must contain, and when the task is done.

`SPEC.md` is the source of truth for behaviour. This plan is the source of truth for structure, naming, and order. If they disagree, follow `SPEC.md` and record the conflict in the task's pull request.

---

## 0. Rules for agents

1. **Read `SPEC.md` and this file before starting any task.** Read the "Conventions" section below every time.
2. **One task per branch and pull request.** Branch name: `task/<task-id>-<short-name>`, e.g. `task/T2.3-entry-models`. Title the PR `[T2.3] Entry models`.
3. **Only start a task when all its dependencies are merged.** Tasks in the same phase without a dependency between them can run in parallel.
4. **Do not add Composer or npm packages that are not listed in §2.** If you believe one is needed, stop and explain why in the PR instead of adding it.
5. **Every task ships tests.** Use Pest with Orchestra Testbench. Before opening a PR, run:
   - `composer test` (Pest)
   - `composer lint` (Pint, test mode)
   - `composer analyse` (Larastan)
   - for frontend tasks: `npm run typecheck && npm run build`
6. **Do not modify migrations from a merged task.** Add a new migration instead (only allowed before v1.0.0 is tagged; after that, migrations must stay fixed per `SPEC.md`).
7. **Keep it simple.** `SPEC.md` § "Design principle" applies: the simplest implementation that is good enough. Do not implement anything listed under "Out of scope for v1".
8. **Update `docs/` when you add developer-facing API** (config keys, Blade helpers, service provider registration methods).
9. **Do not commit `node_modules/`, `vendor/`, or `.env` files.** Do commit the compiled admin assets in `dist/` (see T7.1).

---

## 1. Conventions

| Item | Value |
| --- | --- |
| Composer package | `sunrice/cms` |
| PHP namespace | `Sunrice\` → `src/` |
| Test namespace | `Sunrice\Tests\` → `tests/` |
| Service provider | `Sunrice\SunriceServiceProvider` (auto-discovered) |
| Facade | `Sunrice\Facades\Sunrice` → `Sunrice\Sunrice` (singleton, the registration API) |
| Config file | `config/sunrice.php`, key `sunrice` |
| View namespace | `sunrice::` (package views in `resources/views`) |
| Blade component namespace | `<x-sunrice::...>` |
| Table prefix | Every package table starts with `sunrice_`. The table names in `SPEC.md` are indicative; `entries` becomes `sunrice_entries`, `redirects` becomes `sunrice_redirects`, and so on. |
| Primary keys | Auto-increment `id` (`$table->id()`) |
| JSON columns | `$table->json(...)`, cast to `array` in models |
| Translation | Main locale = `config('sunrice.locales.main')`. Available = `config('sunrice.locales.available')` (array, includes main). |
| Permission names | `sunrice.<area>.<id>.<action>` or `sunrice.<action>` for global ones (see T5.1) |
| Admin route names | `sunrice.admin.<area>.<action>`, e.g. `sunrice.admin.entries.edit` |
| Frontend route names | `sunrice.frontend.*` |
| Admin JS/TS | `resources/js`, TypeScript, React 19, path alias `@/` → `resources/js` |
| Strict types | Every PHP file starts with `declare(strict_types=1);` |
| Actions | Write operations live in single-purpose action classes under `src/Actions/<Area>/` with a public `handle(...)` method. Controllers stay thin and call actions. |

---

## 2. Approved dependencies

Verify each package's current release supports Laravel 13 when you add it, and pin to that major version.

**Composer — require**

| Package | Used for | Added in |
| --- | --- | --- |
| `php` `^8.3` | | T1.1 |
| `illuminate/contracts` `^13.0` | | T1.1 |
| `spatie/laravel-package-tools` | Service provider boilerplate | T1.1 |
| `inertiajs/inertia-laravel` | Admin | T6.1 |
| `spatie/laravel-permission` | Roles and permissions | T5.1 |
| `intervention/image` (v3) | Image sizes | T9.2 |
| `spatie/simple-excel` | CSV export | T6.4 |
| `spatie/laravel-honeypot` | Forms spam protection | T13.2 |
| `spatie/laravel-responsecache` | Optional full-page cache | T12.3 |
| `spatie/laravel-sitemap` | Sitemap | T11.7 |
| `symfony/html-sanitizer` | Rich text sanitizing | T3.4 |

**Composer — require-dev**: `orchestra/testbench`, `pestphp/pest`, `pestphp/pest-plugin-laravel`, `laravel/pint`, `larastan/larastan`.

**npm (admin only)**: `react`, `react-dom`, `@inertiajs/react`, `typescript`, `vite`, `@vitejs/plugin-react`, `laravel-vite-plugin`, `tailwindcss` (v4) + `@tailwindcss/vite`, shadcn/ui components (copied into `resources/js/components/ui` via the shadcn CLI, with their Radix dependencies), `lucide-react`, `@tanstack/react-table`, `@dnd-kit/core`, `@dnd-kit/sortable`, `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-link`, `@tiptap/extension-image`, `sonner` (toasts), `date-fns`.

---

## 3. Target repository layout

```
sunrice-cms/
├── composer.json
├── package.json, package-lock.json, tsconfig.json, vite.config.ts, components.json
├── config/sunrice.php
├── database/migrations/          # all package migrations
├── database/factories/           # model factories for tests
├── dist/                         # committed compiled admin assets
├── docs/                         # developer docs
├── resources/
│   ├── js/                       # admin React app
│   ├── css/admin.css
│   └── views/
│       ├── app.blade.php         # Inertia root view
│       ├── components/           # <x-sunrice::*> Blade views
│       └── defaults/             # fallback frontend templates
├── routes/admin.php, routes/frontend.php
├── src/
│   ├── Actions/<Area>/
│   ├── Cache/
│   ├── Console/
│   ├── Facades/
│   ├── Fields/
│   ├── Forms/
│   ├── Frontend/
│   ├── Http/Controllers/Admin/, Http/Controllers/Frontend/, Http/Middleware/, Http/Requests/
│   ├── Jobs/
│   ├── Models/
│   ├── Permissions/
│   ├── Policies/
│   ├── Query/
│   ├── References/
│   ├── Resources/
│   ├── Support/
│   ├── View/Components/
│   ├── helpers.php
│   ├── Sunrice.php
│   └── SunriceServiceProvider.php
├── tests/
│   ├── Pest.php, TestCase.php
│   ├── Feature/, Unit/
├── workbench/                    # Testbench workbench app for manual runs
├── AGENTS.md, CLAUDE.md, README.md, CHANGELOG.md, SPEC.md, EXECUTION_PLAN.md
└── .github/workflows/tests.yml, .github/workflows/assets.yml
```

---

## 4. Phase overview and dependency graph

| Phase | Tasks | Depends on |
| --- | --- | --- |
| 1. Package skeleton | T1.1–T1.5 | — |
| 2. Database and models | T2.1–T2.6 | Phase 1 |
| 3. Field system | T3.1–T3.6 | T2.2 |
| 4. Content domain | T4.1–T4.8 | Phase 3 |
| 5. Permissions | T5.1–T5.3 | T2.1, T2.2 |
| 6. Admin backend foundation | T6.1–T6.4 | T5.1 |
| 7. Admin frontend foundation | T7.1–T7.4 | T6.1 |
| 8. Admin features | T8.1–T8.8 | Phases 4, 6, 7 |
| 9. Assets | T9.1–T9.4 | T2.5, T6.1 (T9.4 needs T7) |
| 10. Taxonomies, navigation, globals (domain) | T10.1–T10.3 | Phase 3, T4.1 |
| 11. Frontend rendering | T11.1–T11.8 | Phases 4, 10 |
| 12. Caching | T12.1–T12.3 | T11.2 |
| 13. Forms | T13.1–T13.4 | Phase 3, T6.1 |
| 14. Model resources | T14.1–T14.3 | T6.2, T7.3 |
| 15. Install, docs, release | T15.1–T15.4 | All |

Phases 5, 9 (backend part), 10 and 13 (backend part) can run in parallel with Phase 4.

---

## Phase 1 — Package skeleton

### T1.1 Composer manifest and service provider
**Depends on:** —
**Files:**
- `composer.json`
  - `name: sunrice/cms`, `type: library`, `license: MIT`.
  - `require` and `require-dev` from §2 (only the T1.1 rows plus dev tools).
  - PSR-4: `Sunrice\\` → `src/`, `Sunrice\\Database\\Factories\\` → `database/factories/`; dev: `Sunrice\\Tests\\` → `tests/`, `Workbench\\App\\` → `workbench/app/`.
  - `autoload.files`: `src/helpers.php`.
  - `extra.laravel.providers`: `Sunrice\\SunriceServiceProvider`; `extra.laravel.aliases.Sunrice`: `Sunrice\\Facades\\Sunrice`.
  - Scripts: `test` → `pest`, `lint` → `pint --test`, `format` → `pint`, `analyse` → `phpstan analyse`.
- `src/SunriceServiceProvider.php` — extends `Spatie\LaravelPackageTools\PackageServiceProvider`. `configurePackage()` registers: name `sunrice`, config file, views, migrations (discovered from `database/migrations`, run via `runsMigrations()`), commands (added by later tasks). `packageRegistered()` binds `Sunrice\Sunrice` as a singleton.
- `src/Sunrice.php` — the registration API. Empty methods are added by later tasks; start with `version(): string`.
- `src/Facades/Sunrice.php` — facade for `Sunrice\Sunrice`.
- `src/helpers.php` — empty file with `declare(strict_types=1);` and a comment; helpers are added by later tasks, each wrapped in `if (! function_exists(...))`.
- `.gitignore` — `vendor/`, `node_modules/`, `.phpunit.cache/`, `composer.lock`, `workbench/database/*.sqlite`, `.env`.
- `.gitattributes` — `export-ignore` for `tests/`, `workbench/`, `.github/`, `resources/js/`, `*.md` except `README.md`, `package*.json`, `vite.config.ts`, `tsconfig.json`.
**Done when:** `composer install` works, and a Testbench test asserts the provider is loaded and `Sunrice::version()` returns a string.

### T1.2 Configuration file
**Depends on:** T1.1
**Files:**
- `config/sunrice.php` — every key documented with a comment. Keys:
  ```php
  'admin' => ['path' => env('SUNRICE_ADMIN_PATH', 'cms'), 'domain' => null, 'middleware' => ['web']],
  'auth' => ['guard' => 'web', 'user_model' => App\Models\User::class],
  'locales' => ['main' => 'id', 'available' => ['id'], 'names' => ['id' => 'Bahasa Indonesia']],
  'frontend' => ['enabled' => true, 'middleware' => ['web']],
  'cache' => ['enabled' => true, 'store' => null, 'ttl' => 3600, 'full_page' => false],
  'assets' => ['disk' => 'public', 'directory' => 'sunrice', 'max_upload_kb' => 20480,
               'image_sizes' => ['thumbnail' => [300, 300, 'crop'], 'medium' => [800, null, 'fit'], 'large' => [1600, null, 'fit']]],
  'forms' => ['upload_disk' => 'local', 'prune_after_days' => null, 'rate_limit' => ['attempts' => 5, 'per_minutes' => 1]],
  'revisions' => ['keep' => 50],
  'super_admin_role' => 'Super Admin',
  ```
- `tests/Unit/ConfigTest.php`.
**Done when:** config is publishable with `php artisan vendor:publish --tag=sunrice-config` and defaults load in tests.

### T1.3 Test harness and workbench
**Depends on:** T1.1
**Files:**
- `phpunit.xml` — Pest, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `APP_KEY` set.
- `tests/TestCase.php` — extends `Orchestra\Testbench\TestCase`; loads `SunriceServiceProvider` (and later providers from §2 as they are added); uses `RefreshDatabase`; `defineEnvironment()` sets `sunrice.locales` to `main: id, available: [id, en]` for multilingual tests.
- `tests/Pest.php` — `uses(TestCase::class)->in('Feature', 'Unit');` plus helpers `actingAsSuperAdmin()` (filled in T5.1) and `createCollection()` (filled in T2.2).
- `testbench.yaml` + `workbench/` — a minimal app with a `User` model, a `DatabaseSeeder` creating a super admin (`admin@example.com` / `password`) and sample content (filled in as features land), so `vendor/bin/testbench serve` gives a running CMS.
**Done when:** `composer test` runs green with at least one test.

### T1.4 Code quality and CI
**Depends on:** T1.1
**Files:**
- `pint.json` — preset `laravel`.
- `phpstan.neon` — Larastan, level 6, paths `src/`, `config/`, `database/`.
- `.github/workflows/tests.yml` — on push and PR; matrix PHP 8.3 and 8.4 × databases sqlite, mysql 8, postgres 16 (service containers; set `DB_CONNECTION` etc.). Steps: checkout, setup-php, `composer install`, `composer lint`, `composer analyse`, `composer test`.
- `.github/workflows/assets.yml` — on PR: `npm ci && npm run typecheck && npm run build`, then fail if `git diff --exit-code dist/` shows uncommitted build changes (added once T7.1 exists; create the file now with a guard that skips when `package.json` is absent).
**Done when:** CI passes on a PR.

### T1.5 Agent and contributor guides
**Depends on:** T1.1
**Files:**
- `AGENTS.md` — short version of §0 and §1 of this plan, plus the commands to run.
- `CLAUDE.md` — a single line: `See AGENTS.md.`
- `README.md` — what Sunrice is, requirements, placeholder install section (completed in T15.2).
- `CHANGELOG.md` — `## Unreleased`.
**Done when:** files exist and match this plan.

---

## Phase 2 — Database and models

All migrations live in `database/migrations/` with timestamps in the order listed so they run correctly. All models live in `src/Models/`, use `HasFactory` with a factory in `database/factories/`, and cast JSON columns to `array`.

### T2.1 Settings
**Depends on:** Phase 1
**Files:**
- Migration `create_sunrice_settings_table` — `key` (string, unique), `value` (json, nullable), timestamps.
- `src/Models/Setting.php` with static `get(string $key, mixed $default = null)` and `set(string $key, mixed $value)`.
**Done when:** a test for `Setting::get/set` passes.

### T2.2 Collections, blueprints, fieldsets, entries, translations, revisions
**Depends on:** Phase 1
**Files (migrations):**
- `create_sunrice_blueprints_table` — `handle` (unique), `title`, `fields` (json, default `[]`), timestamps.
- `create_sunrice_fieldsets_table` — `handle` (unique), `title`, `fields` (json), timestamps.
- `create_sunrice_collections_table` — `handle` (unique), `title`, `blueprint_id` (FK, nullable, restrict delete), `settings` (json), `archive_data` (json, nullable — values of the archive fields), `sort_order` (int, default 0), timestamps.
  - `settings` keys (documented in the model): `route` (e.g. `/articles/{slug}`), `has_single` (bool), `has_archive` (bool), `archive_route` (e.g. `/articles`), `translatable` (bool), `template` (nullable), `archive_template` (nullable), `archive_blueprint_id` (nullable), `default_sort` (`manual` | `published_at_desc` | `title_asc`), `per_page` (int), `icon` (string), `table_columns` (array of field handles shown in the admin table).
- `create_sunrice_entries_table` — `collection_id` (FK, cascade), `blueprint_id` (FK nullable), `author_id` (nullable, indexed — not a FK because the user table is the host's), `status` (string, default `draft`), `published_at` (timestamp nullable, indexed), `template` (nullable), `sort_order` (int default 0), `parent_id` (nullable, reserved), timestamps, softDeletes. Index `(collection_id, status, published_at)`.
- `create_sunrice_entry_translations_table` — `entry_id` (FK cascade), `collection_id` (FK cascade), `locale` (string 10), `title`, `slug`, `data` (json, default `{}`), `seo` (json, default `{}` — `meta_title`, `meta_description`, `canonical`, `og_title`, `og_description`, `og_image` asset id), `draft` (json nullable), `is_ready` (bool default false), `content_published_at` (timestamp nullable), timestamps. Unique `(entry_id, locale)` and `(collection_id, locale, slug)`.
- `create_sunrice_revisions_table` — `entry_translation_id` (FK cascade), `user_id` (nullable), `content` (json), `created_at`.
- `create_sunrice_redirects_table` — `old_path` (string), `locale` (string 10), `entry_id` (FK → `sunrice_entries`, cascade), timestamps; unique `(old_path, locale)`.
**Files (models):**
- `Blueprint.php`, `Fieldset.php`, `Collection.php` (call it `Collection` in the `Sunrice\Models` namespace; alias as `SunriceCollection` where it clashes with `Illuminate\Support\Collection`), `Entry.php` (SoftDeletes; relations `collection`, `blueprint`, `translations`, `terms` (added T10.1)), `EntryTranslation.php` (relation `entry`, `revisions`), `Revision.php`, `Redirect.php`.
- `Entry::activeBlueprint()` returns the entry's blueprint or the collection default.
- `Entry::translation(string $locale): ?EntryTranslation` and `Entry::mainTranslation(): EntryTranslation`.
- Scopes on `Entry`: `scopePublished()` (`status = published` and `published_at <= now()`), `scopeInCollection($handle)`.
**Done when:** factories create an entry with a main translation; tests cover unique slug per collection+locale and the `published` scope (past, future, draft).

### T2.3 Taxonomies and terms
**Depends on:** T2.2
**Files:**
- Migration `create_sunrice_taxonomies_table` — `handle` (unique), `title`, `blueprint_id` (nullable FK), `hierarchical` (bool), `settings` (json: `has_archive`, `route` e.g. `/category/{slug}`, `template`), timestamps.
- Migration `create_sunrice_collection_taxonomy_table` — `collection_id`, `taxonomy_id`, primary key on both.
- Migration `create_sunrice_terms_table` — `taxonomy_id` (FK cascade), `parent_id` (nullable), `sort_order`, timestamps, softDeletes.
- Migration `create_sunrice_term_translations_table` — `term_id` (FK cascade), `taxonomy_id`, `locale`, `name`, `slug`, `data` (json), timestamps. Unique `(term_id, locale)`, `(taxonomy_id, locale, slug)`.
- Migration `create_sunrice_entry_term_table` — `entry_id`, `term_id`, primary key on both.
- Models `Taxonomy.php`, `Term.php`, `TermTranslation.php`. `Entry::terms()` belongsToMany.
**Done when:** tests cover attaching terms to entries and building a term tree from `parent_id`/`sort_order` (`Term::tree($taxonomyId)` returns nested children).

### T2.4 Navigation and globals
**Depends on:** T2.2
**Files:**
- Migration `create_sunrice_menus_table` — `handle` (unique), `title`, timestamps.
- Migration `create_sunrice_menu_items_table` — `menu_id` (FK cascade), `parent_id` (nullable), `sort_order`, `type` (`entry` | `collection` | `term` | `url`), `target_id` (nullable), `url` (nullable), `labels` (json, `{locale: label}`), `new_tab` (bool), timestamps.
- Migration `create_sunrice_globals_table` — `handle` (unique), `title`, `blueprint_id` (FK), `group` (`global` | `template_part`), `translatable` (bool), timestamps.
- Migration `create_sunrice_global_values_table` — `global_id` (FK cascade), `locale` (nullable; null when not translatable), `data` (json), timestamps; unique `(global_id, locale)`.
- Models `Menu.php`, `MenuItem.php`, `GlobalSet.php` (table `sunrice_globals`; `Global` is reserved in PHP), `GlobalValue.php`.
**Done when:** factories and relationship tests pass.

### T2.5 Assets
**Depends on:** T2.2
**Files:**
- Migration `create_sunrice_asset_folders_table` — `parent_id` (nullable), `name`, timestamps.
- Migration `create_sunrice_assets_table` — `folder_id` (nullable FK, null on delete), `disk`, `path` (unique per disk), `filename`, `mime_type`, `size` (bigint), `width`, `height` (nullable ints), `title`, `alt`, `caption` (nullable), `sizes` (json: `{name: path}`), `version` (int default 1, used for cache-busting query string), `uploaded_by` (nullable), timestamps, softDeletes.
- Models `AssetFolder.php`, `Asset.php` with `url(?string $size = null): string` (returns the original or a generated size URL with `?v={version}`), `isImage(): bool`.
**Done when:** tests cover `url()` with and without sizes using `Storage::fake`.

### T2.6 Forms and references
**Depends on:** T2.2
**Files:**
- Migration `create_sunrice_forms_table` — `handle` (unique), `title`, `fields` (json), `settings` (json: `notify_emails` array, `success_message`, `redirect_url`), timestamps.
- Migration `create_sunrice_form_submissions_table` — `form_id` (FK cascade), `data` (json), `locale`, `ip_address`, `user_agent`, timestamps, softDeletes.
- Migration `create_sunrice_references_table` — `source_type`, `source_id`, `target_type`, `target_id`, `field_path`; indexes on `(source_type, source_id)` and `(target_type, target_id)`.
- Models `Form.php`, `FormSubmission.php` (uses `MassPrunable`; `prunable()` returns nothing when `sunrice.forms.prune_after_days` is null), `Reference.php`.
**Done when:** a test proves pruning deletes only old submissions when the config is set and nothing when it is null.

---

## Phase 3 — Field system

Field definitions are stored as JSON arrays in blueprints, fieldsets and forms. One field definition:

```json
{ "handle": "hero_title", "type": "text", "label": "Hero title",
  "instructions": null, "required": true, "validation": ["max:120"],
  "translatable": true, "config": { } }
```

Container types hold children in `config`: `group` → `config.fields`, `repeater` → `config.fields` + `min`/`max`, `flexible` → `config.fieldsets` (allowed fieldset handles), `fieldset` → `config.fieldset` (handle, expanded inline).

### T3.1 Field type contract and registry
**Depends on:** T2.2
**Files:**
- `src/Fields/FieldType.php` — abstract class:
  - `abstract public static function type(): string` (e.g. `text`)
  - `public function rules(array $field): array` — Laravel validation rules for this value (merged with `required`/`validation` from the definition).
  - `public function defaultValue(array $field): mixed`
  - `public function normalize(mixed $value, array $field): mixed` — clean the posted value before storage.
  - `public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed` — turn stored value into the frontend value.
  - `public function references(mixed $value, array $field): array` — list of `[target_type, target_id]` (default `[]`).
  - `public function toAdminSchema(array $field): array` — JSON passed to React (default: the definition itself).
  - `public function filterable(): bool`, `public function sortCast(): ?string` (`string` | `number` | `date` | null).
  - `public function settingsSchema(): array` — the type's own settings (e.g. Select → options, multiple) as a list of field definitions, used by the blueprint builder UI (T8.1). Default `[]`.
- `src/Fields/FieldRegistry.php` — singleton holding `type => FieldType` instances; `register(string $class)`, `get(string $type)`, `all()`.
- `src/Fields/HydrationContext.php` — holds `locale`, a per-request memo for assets/entries, and `preview` flag.
- `Sunrice::fields()` accessor and `Sunrice::registerField(string $class)`.
**Done when:** unit tests register a dummy type and resolve it.

### T3.2 Built-in field types
**Depends on:** T3.1
**Files:** one class per type in `src/Fields/Types/`, registered in the service provider:
- `Text`, `Textarea`, `RichText` (normalize via sanitizer from T3.4), `Number` (sortCast number), `Toggle` (bool), `Select` (`config.options`, `config.multiple`), `Date` (`config.time` bool; sortCast date; stores ISO 8601), `Link` (stores `{type: url|entry, url, entry_id, label, new_tab}`; hydrates to `{url, label, new_tab}`), `Asset` (`config.multiple`, `config.image_only`; stores IDs; hydrates to `Asset` models via the context memo; references `asset`), `Entries` (`config.collections`, `config.max`; stores IDs; hydrates to resolved entries in the active locale; references `entry`), `Terms` (`config.taxonomy`, stores IDs; references `term`), `Group`, `Repeater`, `Flexible`, `FieldsetInclude` (type `fieldset`).
- Container types recurse into their children for `rules`, `normalize`, `hydrate`, `references` using the registry.
- `Flexible` stores blocks as `[{ "id": "<ulid>", "type": "<fieldset handle>", "values": {...} }]` and hydrates to a collection of `Sunrice\Fields\Block` objects with `type`, `id`, and property/array access to values.
**Done when:** each type has unit tests for rules, normalize, hydrate and references, including nested repeater-in-flexible.

### T3.3 Blueprint resolution and validation
**Depends on:** T3.2
**Files:**
- `src/Fields/BlueprintSchema.php` — built from a field array; expands `fieldset` includes (with cycle detection, max depth 5); methods `fields()`, `field(string $handle)`, `rules(string $prefix = 'data')` (dot-notation rules for a whole form, including nested `*` rules), `normalize(array $data)`, `hydrate(array $data, HydrationContext $ctx)`, `references(array $data)`, `toAdminSchema()`.
- `normalize()` must **keep keys that are not in the blueprint** untouched (SPEC: switching blueprints preserves data).
- `src/Fields/CustomField.php` — base class for developer custom fields: declares `type()`, `baseType()` (a built-in type), `config()` preset, `rules()` extra. The registry wraps it so it behaves like its base type with preset config.
**Done when:** tests prove hidden keys survive a save, fieldset includes expand, cycles throw a clear exception, and a custom field registered through the service provider validates with its extra rules.

### T3.4 Rich text sanitizer
**Depends on:** T3.1
**Files:**
- `src/Support/HtmlSanitizer.php` — wraps `symfony/html-sanitizer` with an allow-list matching TipTap's StarterKit + link + image output; allows `data-asset-id` on `img`; forces `rel="noopener"` on `target=_blank` links; drops scripts, event handlers and `javascript:` URLs.
- `RichText::references()` extracts `data-asset-id` values.
**Done when:** tests cover XSS payloads and asset id extraction.

### T3.5 Reference sync
**Depends on:** T3.3, T2.6
**Files:**
- `src/References/ReferenceSync.php` — `sync(Model $source, array $references)` replaces all rows for the source in one transaction.
- `src/References/HasReferences.php` — trait adding `referencedBy()` / `references()` query helpers.
**Done when:** tests cover add/remove of references and `Asset::usages()` listing entries that use it.

### T3.6 JSON query helper
**Depends on:** T3.1
**Files:**
- `src/Query/JsonField.php` — `where(Builder $q, string $column, string $path, string $operator, mixed $value, ?string $cast)`, `orderBy(Builder $q, string $column, string $path, ?string $cast, string $dir)`. Uses `->where("data->path")` for strings and driver-specific `CAST` expressions (`sqlite`, `mysql`, `pgsql`) for number and date sorting/comparison. Supports `in`, `contains` (for multi-select JSON arrays via `whereJsonContains`).
**Done when:** tests pass on all three databases in CI (number sort `2 < 10`, date sort, `contains`).

---

## Phase 4 — Content domain

### T4.1 Entry resolution and URLs
**Depends on:** T3.3
**Files:**
- `src/Support/Locales.php` — `main()`, `available()`, `isMain($locale)`, `prefix($locale)` (`''` for main, `/{locale}` otherwise), `current()` (from `app()->getLocale()` if available, else main).
- `Entry::resolveFor(string $locale): EntryTranslation` — returns the Ready translation for `$locale` or the main translation (whole-entry fallback; never mixes fields). Sets `$entry->resolved` and `$entry->resolvedLocale` and `$entry->isFallback`.
- Entry accessors used by templates: `title`, `slug`, `url`, `seo`, `get(string $handle)` (hydrated field value via `BlueprintSchema`), `data` (raw), `locale`, `isFallback`.
- `src/Frontend/UrlGenerator.php` — `entry(Entry $e, ?string $locale)`: locale prefix + collection `route` with `{slug}` replaced by the translated slug if Ready else main slug; `archive(Collection $c, ?string $locale)`; `term(Term $t, ?string $locale)`; homepage entry returns `/` or `/{locale}`.
**Done when:** tests cover: main URL without prefix; `/en/...` with translated slug when Ready; main slug when Draft/missing; homepage; whole-entry fallback content.

### T4.2 Draft, publish and revisions actions
**Depends on:** T4.1
**Files (`src/Actions/Entries/`):**
- `CreateEntry` — creates entry (status draft, author = current user) + main translation with `draft` populated.
- `SaveDraft` — validates with `BlueprintSchema::rules()`, normalizes, writes `{title, slug, data, seo}` into the translation's `draft`. Never touches live columns.
- `PublishTranslation` — copies `draft` into `title/slug/data/seo`, clears `draft`, sets `content_published_at = now()`, writes a `Revision`, prunes revisions beyond `sunrice.revisions.keep`, calls `ReferenceSync`, records a redirect if the live slug changed (T4.5), and for the main locale sets entry `status = published` and `published_at` (keep existing if set, else now, or the scheduled date supplied). For non-main locales it also sets `is_ready = true` (this is "Mark Ready").
- `UnpublishEntry` — entry `status = draft`.
- `ReturnTranslationToDraft` — `is_ready = false` (non-main locales only).
- `RestoreRevision` — copies a revision's content into `draft`.
- `ChangeBlueprint` — sets `blueprint_id`; no data changes.
- `DuplicateEntry` — copies entry and translations as a new draft with `-copy` slugs.
- Every action dispatches an event in `src/Events/` (`EntryPublished`, `EntryUnpublished`, `EntryDeleted`, `EntryRestored`) used by caching (T12).
**Done when:** tests cover: draft save leaves live content unchanged; publish makes it live and creates a revision; restore puts revision in draft only; translation Ready/Draft toggles fallback; Outdated detection (`EntryTranslation::isOutdated()` = main `content_published_at` > this one's).

### T4.3 Slug rules
**Depends on:** T4.1
**Files:**
- `src/Support/SlugValidator.php` + rule `src/Rules/ValidSlug.php` — lowercase kebab-case; unique per `(collection_id, locale)` **including trashed entries** (query `withTrashed()`); rejects slugs equal to any configured locale code or the first segment of the admin path; auto-generates from title with `Str::slug` when empty.
**Done when:** tests cover each rejection case.

### T4.4 Trash and reorder
**Depends on:** T4.2
**Files:**
- `src/Actions/Entries/TrashEntry`, `RestoreEntry`, `ForceDeleteEntry` (deletes references and redirects too).
- `src/Actions/Support/Reorder` — generic: given a model class and an ordered list of ids (and optional `parent_id`), updates `sort_order` (and `parent_id`) in one transaction. Used by entries, terms, menu items.
**Done when:** tests pass.

### T4.5 Redirect recording
**Depends on:** T4.1
**Files:**
- `src/Frontend/RedirectRecorder.php` — when a published translation's URL changes, upsert `sunrice_redirects` for the old path and locale pointing at the entry. Remove redirects whose `old_path` equals the entry's new URL (avoid loops).
**Done when:** tests cover a slug change and a change back.

### T4.6 Public entry query
**Depends on:** T3.6, T4.1
**Files:**
- `src/Query/EntryQuery.php` — fluent builder used by the Blade component, controllers and developers:
  `EntryQuery::collection('articles')->locale('en')->where('price', '>', 10)->whereTerm('category', 'news')->orderBy('published_at', 'desc')->limit(5)->with(['terms', 'assets'])->get()` / `->paginate(12, 'articles_page')`.
  - Always applies `published()` unless `->preview()` is set.
  - Joins/eager-loads translations and calls `resolveFor($locale)` on each result.
  - `orderBy` accepts standard columns (`published_at`, `sort_order`, `title` (from main translation)) or custom fields (via `JsonField` with the field's `sortCast`). Custom-field filters read the **resolved** translation's data: filter the main translation's `data`; document that filters use main-language values (simplest consistent rule).
  - `with('assets')` preloads all asset ids referenced by the result set (via `sunrice_references`) into the `HydrationContext` memo.
- `Sunrice::entries(string $collection): EntryQuery` and helper `sunrice_entries()`.
**Done when:** tests cover scheduled entries hidden, filters, term filter, custom-field sort, two independent page names, eager loading count (assert query count).

### T4.7 Collection and blueprint management actions
**Depends on:** T3.3
**Files (`src/Actions/Structure/`):** `SaveCollection`, `DeleteCollection` (blocked when entries exist unless forced), `SaveBlueprint`, `DeleteBlueprint` (blocked when in use by a collection, entry, taxonomy, global), `SaveFieldset`, `DeleteFieldset` (blocked when included anywhere), `RenameFieldHandle` (moves stored data across all translations/drafts/revisions of entries using that blueprint).
- `src/Console/RenameFieldCommand.php` — `sunrice:rename-field {blueprint} {from} {to}` calling `RenameFieldHandle`.
- Validation: handles are snake_case, unique; field handles unique within a blueprint level.
**Done when:** tests cover blocked deletes and the rename command.

### T4.8 Scheduled publishing command
**Depends on:** T4.2
**Files:**
- `src/Console/PublishScheduledCommand.php` — `sunrice:publish-scheduled`: finds entries with `status = published` and `published_at` between the last run (stored in `Setting` `scheduler.last_run`) and now; dispatches `EntryPublished` for each (used by caching). Stores the new `last_run`.
- Register the schedule in the provider (`$schedule->command('sunrice:publish-scheduled')->everyMinute()`), documented in README.
**Done when:** tests with `travel()` cover an entry going live between runs.

---

## Phase 5 — Permissions

### T5.1 Permission registry and super admin
**Depends on:** T2.1, T2.2
**Files:**
- Add `spatie/laravel-permission` to composer. `sunrice:install` (T15.1) publishes its migration if its tables do not exist.
- `src/Permissions/PermissionRegistry.php` — produces the full list of permission names from `SPEC.md` § Permission matrix:
  - per collection id: `sunrice.entries.{id}.{view|create|edit|edit-own|delete|delete-own|publish}`
  - per taxonomy id: `sunrice.terms.{id}.{view|create|edit|delete}`
  - per form id: `sunrice.forms.{id}.{edit|view-submissions|export-submissions|delete-submissions}`
  - per resource key: `sunrice.resources.{key}.{view|create|edit|delete}`
  - global: `sunrice.manage-structure`, `sunrice.manage-users`, `sunrice.manage-roles`, `sunrice.manage-settings`, `sunrice.manage-navigation`, `sunrice.manage-globals`, `sunrice.assets.view`, `sunrice.assets.upload`, `sunrice.assets.delete`, `sunrice.access-admin`.
  - Each entry also has a human label and a group for the role editor UI.
- `src/Permissions/SyncPermissions.php` — creates missing permissions and deletes permissions for removed collections/taxonomies/forms; called from the structure actions (T4.7, T10, T13) and from `sunrice:sync-permissions` command.
- In the provider: `Gate::before` returns true for users with role `config('sunrice.super_admin_role')`.
- Fill `actingAsSuperAdmin()` in `tests/Pest.php`.
**Done when:** creating a collection creates its 7 permissions; deleting it removes them; super admin passes any `can()`.

### T5.2 Policies
**Depends on:** T5.1
**Files (`src/Policies/`):** `EntryPolicy` (`view`, `create`, `update`, `delete`, `publish`; `update`/`delete` allow when user has `edit`/`delete`, or `edit-own`/`delete-own` and `entry.author_id === user.id`), `TermPolicy`, `AssetPolicy`, `FormPolicy`, `StructurePolicy` (collections, blueprints, fieldsets, taxonomies), `MenuPolicy`, `GlobalSetPolicy`, `UserPolicy`, `RolePolicy`. Register with `Gate::policy`.
**Done when:** tests cover Author (own only) vs Editor (all) per SPEC example.

### T5.3 Admin access middleware
**Depends on:** T5.1
**Files:**
- `src/Http/Middleware/Authenticate.php` — uses `sunrice.auth.guard`; redirects guests to `sunrice.admin.login`.
- `src/Http/Middleware/EnsureCanAccessAdmin.php` — requires `sunrice.access-admin` (or super admin), else 403.
**Done when:** tests cover guest redirect, non-admin 403, admin 200.

---

## Phase 6 — Admin backend foundation

### T6.1 Inertia setup, auth and layout data
**Depends on:** T5.3
**Files:**
- Add `inertiajs/inertia-laravel`.
- `src/Http/Middleware/HandleSunriceInertiaRequests.php` — extends `Inertia\Middleware`; `$rootView = 'sunrice::app'`; `version()` returns the md5 of `public/vendor/sunrice/manifest.json` (or `dist/` in dev); shares: `auth.user` (id, name, email), `permissions` (names the user has, or `['*']` for super admin), `navigation` (built by `src/Admin/Navigation.php` from collections, taxonomies, globals, menus, forms, resources the user can view), `locales`, `flash` (`success`, `error`), `adminPath`.
- `resources/views/app.blade.php` — loads `vendor/sunrice/manifest.json` entries through `Vite::useBuildDirectory('vendor/sunrice')->useHotFile(...)` with entry `resources/js/app.tsx`; `@inertia`, `@inertiaHead`.
- `routes/admin.php` — prefix `config('sunrice.admin.path')`, middleware `config('sunrice.admin.middleware')` + `HandleSunriceInertiaRequests`; guest routes: login (GET/POST), logout, forgot/reset password (using Laravel's `Password` broker); authenticated group with `Authenticate` + `EnsureCanAccessAdmin`: dashboard. Later tasks append groups in this file.
- `src/Http/Controllers/Admin/Auth/LoginController.php`, `PasswordResetController.php`, `DashboardController.php` (counts of entries per collection, recent edits, recent submissions).
- Rate-limit login (5/min per email+ip).
**Done when:** feature tests log in, see dashboard props, and log out. The admin does not affect host routes outside the admin path (test a host route still renders without Sunrice's Inertia middleware).

### T6.2 Generic table query
**Depends on:** T6.1, T3.6
**Files:**
- `src/Admin/Table/TableQuery.php` — applies request parameters to a builder: `search` (configured columns + JSON paths), `filters[field]=value` (standard columns, JSON fields via `JsonField`, term ids, status, trashed: `with|only`), `sort=field` / `-field`, `per_page` (10–100), returns `LengthAwarePaginator` plus `meta` (active filters, sort).
- `src/Admin/Table/Column.php` value object (`key`, `label`, `sortable`, `type`) used by entries, resources, submissions.
- User-chosen columns: stored per user per table in `Setting` key `table_columns.{user_id}.{table}`; endpoint `PUT {admin}/table-preferences/{table}`.
**Done when:** unit tests for each parameter.

### T6.3 Flash, errors, and shared form handling
**Depends on:** T6.1
**Files:**
- `src/Http/Requests/` base `AdminRequest` that authorizes via policies.
- Exception rendering for Inertia: 403/404/500 render `Error` page within the admin path only.
**Done when:** a test hitting a forbidden admin route returns the Inertia `Error` page with status 403.

### T6.4 CSV export
**Depends on:** T6.2
**Files:**
- Add `spatie/simple-excel`.
- `src/Admin/Export/CsvExporter.php` — streams a `TableQuery` result (same filters/search as the table, no pagination; chunked with `lazy()`) to a CSV download using `SimpleExcelWriter::streamDownload`. Columns: chosen columns; JSON values flattened with `json_encode` for arrays.
**Done when:** a test downloads a CSV and asserts header and rows.

---

## Phase 7 — Admin frontend foundation

### T7.1 Build tooling
**Depends on:** T6.1
**Files:**
- `package.json` — scripts `dev` (`vite`), `build` (`tsc --noEmit && vite build`), `typecheck`; dependencies from §2.
- `vite.config.ts` — `laravel-vite-plugin` with input `resources/js/app.tsx`, `buildDirectory` output to `dist/` (set `build.outDir = 'dist'`, `manifest: 'manifest.json'`), React plugin, Tailwind plugin, alias `@`.
- `tsconfig.json` — strict, `jsx: react-jsx`, paths `@/*`.
- `components.json` — shadcn config (style new-york, Tailwind v4, aliases to `@/components`).
- `resources/css/admin.css` — Tailwind v4 import, shadcn theme tokens (light and dark).
- `resources/js/app.tsx` — `createInertiaApp` resolving `./pages/**/*.tsx` with `import.meta.glob`, wrapping in `AppLayout` unless the page sets `layout = null`.
- Provider: `publishes([__DIR__.'/../dist' => public_path('vendor/sunrice')], 'sunrice-assets')`.
- `dist/` — committed build output.
**Done when:** `npm run build` produces `dist/manifest.json`, and a published workbench app loads the login page in a browser.

### T7.2 Layout and core UI
**Depends on:** T7.1
**Files (`resources/js/`):**
- `components/ui/*` — shadcn components: button, input, textarea, label, select, checkbox, switch, dialog, sheet, dropdown-menu, popover, command, table, tabs, badge, card, separator, tooltip, calendar, skeleton, sonner, sidebar, breadcrumb, alert-dialog.
- `layouts/AppLayout.tsx` — sidebar from shared `navigation`, top bar with breadcrumbs, user menu, locale indicator; toasts from `flash`.
- `layouts/AuthLayout.tsx`.
- `pages/Auth/Login.tsx`, `pages/Auth/ForgotPassword.tsx`, `pages/Auth/ResetPassword.tsx`, `pages/Dashboard.tsx`, `pages/Error.tsx`.
- `lib/route.ts` — `adminUrl(path)` helper using shared `adminPath` (no Ziggy).
- `lib/can.ts` — `can(permission)` from shared `permissions`.
- `types/index.d.ts` — shared props types.
**Done when:** login → dashboard works in the workbench; layout is responsive.

### T7.3 DataTable component
**Depends on:** T7.2, T6.2
**Files:**
- `components/data-table/DataTable.tsx` — TanStack Table in manual mode driven by server props: columns, rows, pagination, sort, search box (debounced), filter bar (select/date/status/trashed filters described by props), column chooser (persists via table-preferences endpoint), row selection with bulk actions (trash, restore, delete, publish), export button (link to the CSV endpoint with current query), and optional drag-and-drop row reordering (dnd-kit) when `reorderable` is true.
- `components/data-table/useTableQuery.ts` — syncs state to the URL query string using `router.get(..., { preserveState: true, replace: true })`.
**Done when:** used by a sample page in the workbench; typecheck passes.

### T7.4 Field components
**Depends on:** T7.2
**Files (`resources/js/fields/`):**
- `FieldRenderer.tsx` — given an admin schema field list, values, errors and an `onChange`, renders each field via a `fieldComponents` map keyed by `type`; recursive for containers. Error keys use dot paths matching Laravel's.
- One component per built-in type: `TextField`, `TextareaField`, `RichTextField` (TipTap: StarterKit, Link, Image with an "Insert from library" button opening the asset picker and setting `data-asset-id`), `NumberField`, `ToggleField`, `SelectField`, `DateField`, `LinkField` (URL or entry picker), `AssetField` (upload or pick from library; thumbnails; reorder for multiple), `EntriesField` (searchable picker using `GET {admin}/api/entries?collections=...&q=`), `TermsField` (`GET {admin}/api/terms?taxonomy=`), `GroupField`, `RepeaterField` (add/remove/reorder rows with dnd-kit), `FlexibleField` (add block from allowed fieldsets via a menu, collapse, reorder, remove), `FieldsetField` (renders expanded children).
- `components/AssetPicker.tsx` — dialog with folder tree, search, upload; returns selected asset(s). Uses asset endpoints from T9.3.
- `components/EntryPicker.tsx`.
**Done when:** a workbench page renders every field type including nested repeater-inside-flexible, and saving round-trips values.

---

## Phase 8 — Admin features (backend controller + React page per task)

Each task here adds routes to `routes/admin.php`, controllers in `src/Http/Controllers/Admin/`, form requests in `src/Http/Requests/Admin/`, and pages in `resources/js/pages/<Area>/`. Every controller method authorizes with the policies from T5.2. Feature tests cover authorization and the happy path for each endpoint.

### T8.1 Blueprint and fieldset builder
**Depends on:** T4.7, T7.4
**Files:**
- `BlueprintController` (index, create, store, edit, update, destroy), `FieldsetController` (same).
- Pages `Blueprints/Index.tsx`, `Blueprints/Edit.tsx`, `Fieldsets/Index.tsx`, `Fieldsets/Edit.tsx`.
- `components/field-builder/FieldBuilder.tsx` — list of fields with drag-and-drop ordering, "Add field" dialog listing registered types (from the registry, including custom fields), per-type settings form (rendered with `FieldRenderer` from each type's `settingsSchema()`, see T3.1), nested builders for group/repeater, fieldset selector for flexible/fieldset types. Warn when renaming a handle of a blueprint in use ("existing data will be hidden; use `sunrice:rename-field` to move it").
**Done when:** a blueprint with nested fields can be built, saved, and reloaded.

### T8.2 Collections management
**Depends on:** T4.7, T7.2
**Files:** `CollectionController` (CRUD), pages `Collections/Index.tsx`, `Collections/Edit.tsx` with tabs: General (title, handle, icon), Routing (route, archive route, has single, has archive), Content (default blueprint, translatable, taxonomies, default sort, per page), Templates (single and archive Blade view names, with a "view exists" indicator via `view()->exists()`), Archive (archive blueprint + its field values edited with `FieldRenderer`), Table (default columns).
**Done when:** creating a collection adds it to the sidebar and syncs its permissions.

### T8.3 Entries list
**Depends on:** T4.6, T7.3
**Files:** `EntryController@index` using `TableQuery` (search title/slug, filters: status (draft/scheduled/published), author, terms, trashed, custom fields marked filterable), bulk actions (trash, restore, force delete, publish, unpublish), reorder endpoint when collection sort is `manual`, CSV export endpoint. Page `Entries/Index.tsx` showing status badges (Draft / Scheduled / Published / "Has unpublished changes" when main translation `draft` is not null) and per-locale Ready badges.
**Done when:** tests cover filters, bulk actions, export and reorder.

### T8.4 Entry editor
**Depends on:** T4.2, T4.3, T7.4
**Files:**
- `EntryController@create|store|edit|update|destroy`, `EntryPublishController` (publish, unpublish, schedule), `EntryTranslationController` (create translation from main draft as a starting copy, mark ready, return to draft), `EntryRevisionController` (index, restore), `EntryBlueprintController` (change blueprint), `EntryPreviewController` (returns a signed, 1-hour preview URL — see T11.6).
- Page `Entries/Edit.tsx`: locale switcher tabs (with Ready/Draft/Outdated badges), title + slug (auto-generated, editable), `FieldRenderer` for the active blueprint, sidebar with: status, publish date/time picker (scheduling), author (read-only), template override (text input with exists indicator), blueprint selector, terms selectors for attached taxonomies, SEO panel (meta title, description, canonical, OG title/description/image), revisions drawer, Preview button, buttons Save draft / Publish (disabled without `publish` permission) / Unpublish. Warn on navigation with unsaved changes.
**Done when:** feature tests cover the full SPEC workflow (draft on published entry keeps live version; publish; schedule; restore revision; translation Ready/Draft; own-entry permissions).

### T8.5 Taxonomies and terms admin
**Depends on:** T10.1, T7.3
**Files:** `TaxonomyController` (CRUD: title, handle, hierarchical, blueprint, collections, archive settings and template), `TermController` (list as a tree with drag-and-drop for hierarchical taxonomies, create/edit with name, slug, translations tabs, blueprint fields; trash/restore). Pages `Taxonomies/*`, `Terms/*`. API endpoint `GET {admin}/api/terms` used by `TermsField`.
**Done when:** tests cover tree reorder and term translations.

### T8.6 Navigation admin
**Depends on:** T10.2, T7.4
**Files:** `MenuController` (CRUD), `MenuItemController` (save the whole tree in one request: `[{id?, type, target_id, url, labels, new_tab, children: [...]}]`). Page `Menus/Edit.tsx` with a nested drag-and-drop tree (dnd-kit sortable tree), item dialog with type selector (entry picker, collection select, term picker, custom URL), and per-locale label inputs.
**Done when:** tests cover saving a 3-level tree and that deleted items are removed.

### T8.7 Globals and template parts admin
**Depends on:** T10.3, T7.4
**Files:** `GlobalSetController` (CRUD for structure: title, handle, blueprint, group, translatable — requires `manage-structure`), `GlobalValueController` (edit values per locale — requires `manage-globals`). Pages `Globals/Index.tsx` (two sections: Globals and Template parts), `Globals/Edit.tsx`.
**Done when:** tests cover shared vs per-locale values.

### T8.8 Users and roles admin
**Depends on:** T5.1, T7.3
**Files:** `UserController` (list with search, create, edit name/email/password/roles, delete — cannot delete yourself or the last super admin), `RoleController` (list, create, edit with a permission matrix grouped by area using `PermissionRegistry` labels; super admin role is not editable). Pages `Users/*`, `Roles/*`.
**Done when:** tests cover role assignment and last-super-admin protection.

---

## Phase 9 — Assets

### T9.1 Upload, replace and folders (backend)
**Depends on:** T2.5, T6.1
**Files (`src/Actions/Assets/`):** `UploadAsset` (validates size from config and mime, stores on configured disk under `{directory}/{Y}/{m}/{unique-filename}`, reads dimensions, dispatches `GenerateImageSizes`), `ReplaceAsset` (overwrites the same path, increments `version`, regenerates sizes), `UpdateAssetMeta`, `MoveAsset`, `TrashAsset`, `RestoreAsset`, `ForceDeleteAsset` (deletes files and sizes; blocked when the asset has usages unless forced), `CreateFolder`, `RenameFolder`, `DeleteFolder` (only when empty).
**Done when:** tests with `Storage::fake` cover each action.

### T9.2 Image sizes
**Depends on:** T9.1
**Files:**
- Add `intervention/image` v3.
- `src/Jobs/GenerateImageSizes.php` — for each `sunrice.assets.image_sizes` entry `[width, height, mode]` (`crop` = center cover, `fit` = scale down keeping ratio), writes `{path-without-ext}-{name}.{ext}` and stores paths in `assets.sizes`. Skips non-images and SVGs.
- `src/Console/RegenerateImageSizesCommand.php` — `sunrice:regenerate-images`.
**Done when:** tests assert generated dimensions.

### T9.3 Asset endpoints
**Depends on:** T9.1
**Files:** `AssetController` (index with folder, search, filters: type image/document/video, trashed; upload; update meta; replace; move; trash/restore/delete; `usages` endpoint listing entries via references), `AssetFolderController`. JSON endpoints under `{admin}/api/assets` for the picker; the Inertia page under `{admin}/assets`.
**Done when:** feature tests cover permissions (`assets.view`, `assets.upload`, `assets.delete`).

### T9.4 Asset library page
**Depends on:** T9.3, T7.3
**Files:** `pages/Assets/Index.tsx` — folder tree, grid/list toggle, drag-and-drop upload with progress, detail sheet (preview, alt/title/caption, replace file, usages list, copy URL), bulk move/trash. Reuse the same components inside `AssetPicker`.
**Done when:** usable in the workbench.

---

## Phase 10 — Taxonomies, navigation and globals (domain)

### T10.1 Taxonomy domain
**Depends on:** T2.3, T3.3
**Files:** actions `SaveTaxonomy`, `DeleteTaxonomy`, `SaveTerm` (with translations; slug unique per taxonomy+locale incl. trashed), `TrashTerm`, `RestoreTerm`; `Term::resolveFor($locale)` with whole-term fallback like entries; `UrlGenerator::term()`; `EntryQuery::whereTerm($taxonomyHandle, $slugOrIds)` including descendants for hierarchical taxonomies (`->includeChildren()`).
**Done when:** tests pass.

### T10.2 Navigation domain
**Depends on:** T2.4, T4.1
**Files:**
- `src/Frontend/MenuBuilder.php` — `build(string $handle, ?string $locale): Collection<MenuNode>`; each `MenuNode` has `label` (locale label, else main label, else target title), `url` (via `UrlGenerator`, so it follows slug changes and the active locale), `newTab`, `isActive` (matches current URL), `children`. Items whose target is unpublished or trashed are skipped.
- Helper `sunrice_menu(string $handle): Collection` and `Sunrice::menu()`.
**Done when:** tests cover slug change reflected in URL, locale labels, skipped unpublished targets.

### T10.3 Globals domain
**Depends on:** T2.4, T3.3
**Files:**
- `src/Frontend/GlobalsRepository.php` — `get(string $handle, ?string $locale)` returns a hydrated value object with property and `get()` access; translatable globals fall back to the main-locale values as a whole when the locale row is missing.
- Helper `sunrice_global(string $handle)` and `Sunrice::global()`.
- Action `SaveGlobalValues`.
**Done when:** tests pass.

---

## Phase 11 — Frontend rendering

### T11.1 Locale detection
**Depends on:** T4.1
**Files:** `src/Http/Middleware/SetFrontendLocale.php` — if the first path segment is a non-main available locale, set `app()->setLocale()` to it and strip it for matching; otherwise use the main locale.
**Done when:** tests pass.

### T11.2 Catch-all route and request resolution
**Depends on:** T11.1, T10.1
**Files:**
- `routes/frontend.php` — registered in the provider's `packageBooted()` via `$this->app->booted(fn () => Route::middleware(...)->group(...))` so it is registered after host routes; only when `sunrice.frontend.enabled`. Routes: `/` and `/{locale}` (homepage), `/{path}` with `where('path', '.*')` and name `sunrice.frontend.show`.
- `src/Frontend/RouteMatcher.php` — compiles every collection's `route` and `archive_route` and every taxonomy's `route` into regexes (memoized per request; T12.1 adds `ContentCache` caching), matches the path, and returns a `Match` (`type`: `entry` | `archive` | `term`, collection/taxonomy, slug).
- `src/Http/Controllers/Frontend/PageController.php` — resolves the match; entry: finds the translation with that slug in that locale **or** the main slug (SPEC: language URL may use the original slug), requires `published()`; archive: the collection must have `has_archive`; term: the taxonomy must have `has_archive`. On no match, checks `sunrice_redirects` (301) then aborts 404.
- Homepage: `Setting::get('homepage_entry_id')`.
**Done when:** feature tests cover entry, archive, term, homepage in both locales, scheduled entry 404, redirect 301, and that a host route defined in the workbench still wins over the catch-all.

### T11.3 Template resolution and hooks
**Depends on:** T11.2
**Files:**
- `src/Frontend/TemplateResolver.php` — entry: `entry.template` → `collection.settings.template` → `sunrice.{collection}.show` → `sunrice.show` → `sunrice::defaults.show`; archive: `settings.archive_template` → `sunrice.{collection}.index` → `sunrice.index` → `sunrice::defaults.index`; term: taxonomy template → `sunrice.taxonomies.{taxonomy}.show` → `sunrice.taxonomies.show` → `sunrice::defaults.term`. First view that exists wins.
- `src/Frontend/TemplateContext.php` — `pageType`, `entry`, `collection`, `term`, `taxonomy`, `locale`.
- `Sunrice::resolveTemplateUsing(callable $hook)` — hooks run in registration order after normal resolution; each receives `(string $view, TemplateContext $ctx)` and returns a view name or null (keep current).
- `resources/views/defaults/show.blade.php`, `index.blade.php`, `term.blade.php` — minimal semantic HTML using `<x-sunrice::seo>`.
- Views receive `$entry` / `$entries` / `$term`, `$collection`, `$taxonomy`, `$locale`.
**Done when:** tests cover each priority level and a hook override.

### T11.4 Blade entries component
**Depends on:** T4.6
**Files:**
- `src/View/Components/Entries.php` — props: `collection` (string), `paginate` (bool, default false), `perPage` (int, default collection `per_page` or 12), `pageName` (default `{collection}_page`), `limit` (int, non-paginated), `where` (array of `[field, operator, value]` or `field => value`), `terms` (array `taxonomy => slug|slug[]`), `orderBy` (string, e.g. `published_at` or `-price`), `with` (string comma list). Public property `entries` (Collection or LengthAwarePaginator) built with `EntryQuery` in the constructor. The paginator uses `withQueryString()`.
- `resources/views/components/entries.blade.php` — `{{ $slot }}` only.
- Registered as `sunrice::entries`.
**Done when:** a Blade test renders the SPEC example with two paginated components on one page and confirms independent pages.

### T11.5 SEO component and helpers
**Depends on:** T11.3
**Files:**
- `src/View/Components/Seo.php` + `resources/views/components/seo.blade.php` — `<x-sunrice::seo :entry="$entry" />`: `<title>`, meta description, canonical (fallback pages → main-language URL; otherwise `seo.canonical` or the entry URL), Open Graph tags (og:title, og:description, og:image via `Asset::url('large')`, og:url, og:locale), hreflang alternates for main + Ready translations (+ `x-default` → main) — omitted entirely on fallback pages.
- Helper `sunrice_locale_urls(?Entry $entry): array` for language switchers (`locale => url`).
**Done when:** tests cover a fallback page canonical and hreflang lists.

### T11.6 Preview
**Depends on:** T11.3, T4.2
**Files:** `src/Http/Controllers/Frontend/PreviewController.php` at `/{admin}/preview/{entry}/{locale}` with `signed` middleware: renders the entry from its `draft` (falling back to live), ignoring publication status, with `HydrationContext::preview = true`, never cached, and adds `X-Robots-Tag: noindex`.
**Done when:** tests cover an expired signature (403) and draft content rendering.

### T11.7 Sitemap
**Depends on:** T11.2
**Files:** add `spatie/laravel-sitemap`; `src/Frontend/SitemapBuilder.php` — published entries of collections with `has_single`, archives, term archives; each entry once per locale where it is main or Ready (fallback URLs excluded), with `lastmod`; route `/sitemap.xml` registered before the catch-all; cached with the content cache (T12.1).
**Done when:** a test asserts included and excluded URLs.

### T11.8 Developer facade methods and docs
**Depends on:** T11.3–T11.5
**Files:** finalize `src/Sunrice.php` methods: `registerField`, `registerResource` (T14), `resolveTemplateUsing`, `entries`, `menu`, `global`. Write `docs/templates.md` (conventions, variables, blocks rendering pattern `@foreach ($entry->get('sections') as $block) @include('blocks.'.$block->type, ['block' => $block]) @endforeach`), `docs/blade-components.md`, `docs/helpers.md`.
**Done when:** docs reviewed against code.

---

## Phase 12 — Caching

### T12.1 Content version and query cache
**Depends on:** T11.2
**Files:**
- `src/Cache/ContentVersion.php` — `current(): int` (stored in the configured cache store, key `sunrice:content_version`, initialized to `time()`), `bump(): void`.
- `src/Cache/ContentCache.php` — `remember(string $key, Closure $callback)` with key `sunrice:{version}:{locale}:{key}` and TTL `sunrice.cache.ttl`; no-op when disabled or in preview.
- `EntryQuery` caches results keyed by a hash of its full state (collection, locale, filters, terms, order, limit, page, page name, per page). Menus, globals, route matcher and sitemap also use `ContentCache`.
**Done when:** tests prove a second identical query hits cache (query count) and a different page/locale does not.

### T12.2 Invalidation listeners
**Depends on:** T12.1
**Files:** `src/Cache/BumpContentVersion.php` listener registered for `EntryPublished`, `EntryUnpublished`, `EntryDeleted`, `EntryRestored`, term saves/deletes, menu saves, global value saves, asset updates/replacements/deletes, collection/taxonomy structure saves, and `Setting` changes to `homepage_entry_id`. Saving a draft must **not** bump.
**Done when:** tests assert bump/no-bump for each event, including `sunrice:publish-scheduled`.

### T12.3 Optional full-page cache
**Depends on:** T12.2
**Files:** add `spatie/laravel-responsecache`; when `sunrice.cache.full_page` is true, add its `CacheResponse` middleware to the frontend route group only and call `ResponseCache::clear()` inside `ContentVersion::bump()`. Preview and form POST routes are excluded.
**Done when:** tests with the flag on/off.

---

## Phase 13 — Forms

### T13.1 Form domain
**Depends on:** T3.3, T2.6
**Files:** actions `SaveForm` (fields via `BlueprintSchema`; allowed types for forms: text, textarea, number, toggle, select, date, plus a forms-only `file` type `src/Fields/Types/File.php` that stores to `sunrice.forms.upload_disk` under `form-uploads/{form}` and validates `mimes`/`max` from config), `DeleteForm`, `SubmitForm` (validate with schema rules, normalize, store `FormSubmission`, queue `FormSubmittedNotification` mail to `settings.notify_emails`).
**Done when:** tests pass.

### T13.2 Public submission endpoint and Blade component
**Depends on:** T13.1
**Files:**
- Add `spatie/laravel-honeypot`.
- Route `POST /sunrice/forms/{form:handle}` (registered before the catch-all) named `sunrice.frontend.forms.submit`, middleware: `ProtectAgainstSpam` + named rate limiter `sunrice-forms` from config (per IP + form).
- `src/View/Components/Form.php` + `resources/views/components/form.blade.php` — `<x-sunrice::form handle="contact">` outputs `<form>` with action, `@csrf`, honeypot fields, and exposes `$form` (fields schema), `$errors`, and `$success` (session flash) to the slot so developers write their own inputs. Redirects back with flash `sunrice_form_success.{handle}` or to `settings.redirect_url`.
**Done when:** tests cover validation errors, honeypot rejection, rate limit 429, stored submission, mail queued.

### T13.3 Forms admin
**Depends on:** T13.1, T8.1, T7.3, T6.4
**Files:** `FormController` (CRUD with `FieldBuilder` limited to allowed types; settings: notify emails, success message, redirect URL), `FormSubmissionController` (table with search/filter by date, detail view, file download from private disk with authorization, trash/restore/delete, CSV export). Pages `Forms/*`, `FormSubmissions/*`.
**Done when:** tests cover permissions `view-submissions`, `export-submissions`, `delete-submissions`.

### T13.4 Pruning schedule
**Depends on:** T13.1
**Files:** register `model:prune --model=Sunrice\Models\FormSubmission` daily in the provider schedule; also delete uploaded files of pruned submissions (`pruning()` hook).
**Done when:** test covers file deletion on prune.

---

## Phase 14 — Model resources (existing Laravel models)

### T14.1 Resource definition API
**Depends on:** T6.2, T5.1
**Files (`src/Resources/`):**
- `Resource.php` — abstract: `public static string $model`; `key()` (default kebab plural of model basename), `label()`, `navigationGroup()`, `navigationIcon()`, `fields(): array` (of `ResourceField`), `columns(): array` (of `Column`), `filters(): array` (of `Filter`), `searchable(): array`, `defaultSort(): string`, `rules(?Model $record): array`, `query(Builder $q): Builder` (hook), `with(): array`.
- `ResourceField.php` — fluent: `ResourceField::make('title')->type('text')->label(...)->config([...])`; type is any registered field type that maps to a scalar (text, textarea, rich_text, number, toggle, select, date) plus `belongs_to` (`->relationship('author', 'name')`, select of related records) and `belongs_to_many` (`->relationship('tags', 'name')`, multi-select).
- `Filter.php` — `select`, `boolean`, `date_range` filters on columns.
- `Sunrice::registerResource(string $class)` registry; permissions are generated by `PermissionRegistry` (`sunrice.resources.{key}.*`) and synced.
**Done when:** unit tests for a sample resource on a workbench `Product` model.

### T14.2 Resource controller and authorization
**Depends on:** T14.1
**Files:** `ResourceController` (generic index/create/store/edit/update/destroy + export) under `{admin}/resources/{resource}`; uses `TableQuery`; validates with `rules()`; persists attributes and syncs `belongs_to_many`; authorization requires the Sunrice resource permission **and** the model's own policy if one is registered (`Gate::getPolicyFor($model)`), per SPEC.
**Done when:** feature tests cover CRUD, validation errors, a policy denying update, and export.

### T14.3 Resource pages
**Depends on:** T14.2, T7.3, T7.4
**Files:** `pages/Resources/Index.tsx` (DataTable), `pages/Resources/Edit.tsx` (FieldRenderer with the resource's admin schema; `belongs_to` fields use a searchable select backed by `GET {admin}/api/resources/{resource}/options/{field}?q=`).
**Done when:** the workbench `Product` resource is fully usable.

---

## Phase 15 — Install, docs and release

### T15.1 Install command
**Depends on:** all backend phases
**Files:** `src/Console/InstallCommand.php` — `sunrice:install`: publish config; publish `spatie/laravel-permission` migrations/config if its tables do not exist; publish assets (`sunrice-assets`, `--force`); run `migrate`; run `sunrice:sync-permissions`; create the super admin role; optionally create a super admin user (asks name/email/password, or `--no-user`); check that the user model uses `Spatie\Permission\Traits\HasRoles` and print instructions if not; print the admin URL and the scheduler reminder.
- Also `sunrice:publish-assets` (republish compiled assets after upgrades).
**Done when:** a Testbench test runs the command non-interactively on a fresh app and can log in.

### T15.2 Documentation
**Depends on:** T15.1
**Files (`docs/`):** `installation.md`, `configuration.md` (every config key), `content-modeling.md` (collections, blueprints, fieldsets, field types), `templates.md`, `blade-components.md`, `helpers.md`, `multilingual.md` (fallback rules, URLs, Ready workflow), `custom-fields.md`, `resources.md`, `permissions.md` (matrix), `caching.md` (content version, scheduler, full-page cache), `forms.md`, `assets.md`, `upgrading.md`. Update `README.md` with quick start and links.
**Done when:** every public API in `src/Sunrice.php`, helpers and components is documented.

### T15.3 End-to-end verification
**Depends on:** T15.1
**Files:**
- `workbench/database/seeders/DemoSeeder.php` — two locales; collections `pages` (`/{slug}`) and `articles` (`/articles/{slug}`, archive `/articles`); taxonomy `categories` (hierarchical); a page with a flexible `sections` field (Hero, Text, Gallery); main menu; `site` global and `header`/`footer` template parts; a contact form; workbench Blade templates under `workbench/resources/views/sunrice/`.
- `tests/Feature/EndToEndTest.php` — HTTP-level journey: log in, create and publish an article with a translation, view it at both URLs, edit as draft and check the live page is unchanged, publish and check the cache refreshed, submit the contact form, export submissions.
**Done when:** the test passes on all three databases in CI.

### T15.4 Release
**Depends on:** T15.2, T15.3
**Files:** `CHANGELOG.md` `## 1.0.0`, rebuilt `dist/`, tag `v1.0.0` (after maintainer approval).
**Done when:** `composer require sunrice/cms` into a fresh Laravel 13 app followed by `php artisan sunrice:install` gives a working CMS without running npm.

---

## 5. Definition of done (every task)

- Code follows §1 conventions and `SPEC.md`.
- New behaviour has Pest tests; `composer test`, `composer lint`, `composer analyse` pass; frontend tasks also pass `npm run typecheck` and `npm run build`, with `dist/` committed.
- Authorization is enforced on the server for every admin endpoint.
- No new dependencies outside §2.
- Developer-facing changes are reflected in `docs/` and `CHANGELOG.md` (`## Unreleased`).
- The PR description lists the task ID, what was built, and any deviation from this plan or `SPEC.md`.
