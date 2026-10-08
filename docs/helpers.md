# Helpers and facade

Facade: `Sunrice\Facades\Sunrice` (or `app(\Sunrice\Sunrice::class)`).

| API | Description |
| --- | --- |
| `Sunrice::version()` | Package version string. |
| `Sunrice::fields()` | The `FieldRegistry`. |
| `Sunrice::registerField(string $class)` | Register a custom field type. |
| `Sunrice::registerResource(string $class)` | Register a model resource (see resources.md). |
| `Sunrice::resources()` / `Sunrice::resource($key)` | Registered resources map / lookup. |
| `Sunrice::resolveTemplateUsing(callable)` | Template resolution hook. |
| `Sunrice::entries($collection)` | `EntryQuery` for a collection handle. |
| `Sunrice::menu($handle, $locale = null)` | Resolved menu items (`MenuNode` collection). |
| `Sunrice::global($handle, $locale = null)` | Hydrated `GlobalData` (or null values array). |

## Global functions

| Function | Description |
| --- | --- |
| `sunrice_entries($collection)` | Alias of `Sunrice::entries()`. |
| `sunrice_menu($handle, $locale)` | Alias of `Sunrice::menu()`. |
| `sunrice_global($handle, $locale)` | Alias of `Sunrice::global()`. |
| `sunrice_body_class(array\|string $extra = [])` | The page's body classes as one string (see [Body classes](templates.md#body-classes)); `@bodyClass` prints the whole `class="..."` attribute. |
| `sunrice_locale_urls(Entry\|Term\|null $page = null, ?Collection $collection = null)` | `[locale => url]` for a language switcher: the entry, the term (with the collection on per-collection term pages), or the current listing page. |

## EntryQuery

```php
Sunrice::entries('articles')
    ->locale('en')          // defaults to current locale
    ->where('featured', true)
    ->whereTerm('news', includeChildren: true)
    ->orderBy('published_at', 'desc')
    ->limit(5)
    ->get();                // or ->paginate(12)
```

`where()` reads custom fields from the main language's data, plus these
entry columns: `id`, `parent_id`, `title`, `status`, `author_id`,
`published_at`, `sort_order`, `created_at`, `updated_at`. Columns also
take `in` / `not in` with an array, and `null` for "has none":

```php
// Other jobs than this one (a "related" sidebar)
sunrice_entries('careers')->where('id', '!=', $entry->id)->limit(3)->get();

// Top-level pages, articles from before 2025
sunrice_entries('pages')->where('parent_id', null)->get();
sunrice_entries('articles')->where('published_at', '<', '2025-01-01')->get();
```

`published_at` is the date shown on the site; editors change it in the
entry's Status card (backdate, or a future date to schedule).

`search($text, $fields)` keeps entries whose title or given fields contain
the text (main language, case-insensitive); custom-field filters can also
match a list field holding any of several values:

```php
sunrice_entries('products')->search('linen', ['title', 'summary'])->get();
sunrice_entries('products')->where('colors', 'contains any', ['red', 'blue'])->get();
```

### Reverse relationships

`entries` and `terms` fields store ids on the entry that links. To go the
other way, from the linked entry or term to the entries pointing at it:

```php
// Articles whose "related_articles" entries field includes $entry
sunrice_entries('articles')->whereEntry('related_articles', $entry)->get();

// Projects whose "industries" terms field includes $term
sunrice_entries('projects')->whereFieldTerm('industries', $term)->get();
```

- `whereEntry($field, $entry)` takes an `Entry`, an id, or an array of them
  (matches any).
- `whereFieldTerm($field, $term, includeChildren: false)` takes a `Term`, an
  id, a slug (looked up in the field's taxonomy) or an array of them;
  `includeChildren: true` also matches child terms.
- `whereTerm($taxonomy, …)` is different: it filters by the taxonomies
  attached to the collection (the editor's Taxonomies card), not by a
  `terms` field in the blueprint.

Both read the main language's values, like `where()`, and combine with
the other filters.

Public queries only ever return published, non-trashed entries and
resolve the whole-entity translation (Ready translation, otherwise the
whole main-locale translation).
