# Templates

## Starter templates

Publish a working set of templates into your app and edit them freely:

```bash
php artisan vendor:publish --tag=sunrice-templates
```

This copies them to `resources/views/sunrice/`, where the resolver below
picks them up:

| File | Used for | Shows how to |
| --- | --- | --- |
| `layouts/app.blade.php` | every page | `<x-sunrice::seo>`, shared layout |
| `partials/header.blade.php` | header | globals (`sunrice_global('site')`), menus (`sunrice_menu('main')`), language switcher (`sunrice_locale_urls()`) |
| `partials/footer.blade.php` | footer | template-part globals, rich text, repeaters |
| `partials/menu.blade.php` | menus | nested menu items |
| `partials/card.blade.php` | listings | entry teaser (image, date, excerpt) |
| `show.blade.php` | any entry | fields via `$entry->get()`, assets, flexible-content blocks |
| `index.blade.php` | collection archives | paginated `$entries`, archive fields |
| `articles/show.blade.php` | the `articles` collection only | dates, author, terms, relationship fields, `<x-sunrice::entries>` |
| `taxonomies/show.blade.php` | term archives | `$term`, child terms, term fields via `$term->get()` |
| `blocks/{hero,text,gallery,form}.blade.php` | flexible-content blocks | one file per fieldset handle |
| `blocks/default.blade.php` | unknown block types | debug hint when `APP_DEBUG` is on |

Every template works on a fresh install: missing globals, menus and
fields simply render nothing. Each file starts with a comment listing the
field handles it expects.

### Languages

- Interface text ("Latest articles", "Nothing here yet.", the form's
  Submit button…) comes from `__('sunrice::frontend.*')`, shipped in
  English and Indonesian. Add a language or change the wording with
  `php artisan vendor:publish --tag=sunrice-translations`, then edit
  `lang/vendor/sunrice/{locale}/frontend.php`.
- Layouts and partials read the page from `$sunricePage` (`->entry`,
  `->term`, `->collection`, `->taxonomy`), not `$entry`/`$term`: Blade
  hands a child template's variables to its layout, so after
  `@foreach ($entries as $entry)` a listing's `$entry` is its last card.
- The language switcher shows the names set under Settings → Languages
  and links each language's version of the entry, term page or listing.
- Collection and taxonomy titles can be set per language in their forms;
  templates use `$collection->titleIn($locale)` / `$taxonomy->titleIn($locale)`.

### Listing pages

A collection's listing page (archive) has its own content, edited from
the collection's entries list with the **Listing page** button, one tab
per language:

- a heading and intro, read with `$collection->archiveText($locale)`
  (`['title' => …, 'intro' => …]`, falling back to the main language);
- any fields you like: create a blueprint (e.g. `description` rich text,
  `image` asset, a `hero` flexible field…) and pick it as **Listing
  blueprint** under Structure → Collections → Pages & URLs. Read them with
  `$collection->archive('handle')`, hydrated like entry fields. As with
  entries, translatable fields can differ per language and the others are
  shared with the main language.

Editing the main language needs the collection's entry **edit**
permission; other languages also accept **translate**.

## Reading data

| Field type | `$entry->get('handle')` returns |
| --- | --- |
| text, textarea, select | string (multi-select: array) |
| number / toggle | number / bool |
| rich text | sanitized HTML — print with `{!! !!}` |
| date | `Carbon` |
| asset | `Asset` (`->url()`, `->url('thumbnail'\|'medium'\|'large')`, `->alt`); multiple → collection |
| entries | collection of `Entry`, resolved for the active language |
| terms | collection of `Term` (`->name`, `->url`) |
| link | `['url' => ..., 'label' => ..., 'new_tab' => bool]` |
| group | array of child values |
| repeater | collection of row arrays (hidden rows removed; `->byKey('key')`) |
| flexible | collection of `Block` (`->type`, `->id`, `->key`, values as properties; hidden blocks removed; `->byKey('key')`) |

Entries also expose `$entry->title`, `->slug`, `->url`, `->published_at`,
`->author`, `->terms` and `->isFallback` (true when a language shows the
main-language content because its translation isn't Ready). Terms expose
`->name`, `->slug`, `->url`, `->urlIn($collection)` (the term's page for
one collection) and `->get('handle')` for taxonomy-blueprint fields. Globals: `sunrice_global('handle')->field` or
`->get('field', 'default')`.

Render flexible content with one partial per block type:

```blade
@foreach ($entry->get('sections') ?? [] as $block)
    @includeFirst(['sunrice.blocks.'.$block->type, 'sunrice.blocks.default'], ['block' => $block])
@endforeach
```

Or, for a hand-built landing page, give blocks/rows a key in the admin and
fetch them directly:

```blade
@if ($hero = $entry->get('sections')->byKey('hero'))
    <h1>{{ $hero->heading }}</h1>
@endif
```

## Resolution order

Templates are plain Blade views. Resolution order for an entry page
(`pageType = 'entry'`):

1. The entry's own `template` column.
2. `collection.settings.template`.
3. `sunrice.{collection-handle}.show` in the app (`resources/views/`).
4. `sunrice.show` in the app.
5. `sunrice::defaults.show` bundled in the package.

Archives use `index` instead of `show` (`sunrice.{collection}.index` →
`sunrice.index` → `sunrice::defaults.index`); term archives use
`sunrice.taxonomies.{taxonomy}.show` → `sunrice.taxonomies.show` →
`sunrice::defaults.term`.

Every view receives `locale` and `pageType` (`entry`, `archive` or
`term`) plus: `entry` and `collection` on entry pages; `entries` (a
paginator) and `collection` on archives; `term`, `taxonomy`, `entries`
and `collection` (null when the page spans all collections) on term
archives. Entries are already resolved for the active language.

## Template hooks

Reorder or substitute resolution with a hook registered in a service
provider:

```php
use Sunrice\Facades\Sunrice;
use Sunrice\Frontend\TemplateContext;

Sunrice::resolveTemplateUsing(function (string $view, TemplateContext $context): ?string {
    return $context->entry?->collection->handle === 'products'
        ? 'shop.'.$view
        : null; // null keeps the current candidate
});
```

Hooks run after normal resolution, in registration order; the last
non-null return wins.

## Homepage

`Setting::set('homepage_entry_id', $entry->id)` (or the Settings admin
screen) points `/` at any published entry.

## Preview

Editors preview drafts via a signed URL (`sunrice.frontend.preview`)
rendered with the same template chain plus `preview: true` in the
hydration context; drafts are never cached and are marked `noindex`.
