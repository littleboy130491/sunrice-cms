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
| `sunrice_locale_urls(?Entry $entry)` | `[locale => url]` alternates for an entry. |

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

Public queries only ever return published, non-trashed entries and
resolve the whole-entity translation (with `main` fallback depending on
`collection.settings.fallback`).
