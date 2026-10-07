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
