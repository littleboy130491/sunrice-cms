# Templates

Templates are plain Blade views. Resolution order for an entry page
(`pageType = 'single'`):

1. The entry's own `template` column.
2. `collection.settings.template`.
3. `sunrice.{collection-handle}.show` in the app (`resources/views/`).
4. `sunrice.show` in the app.
5. `sunrice::defaults.show` bundled in the package.

Archives use `index` instead of `show` (`sunrice.{collection}.index` →
`sunrice.index` → `sunrice::defaults.index`); term archives use `term`
(`sunrice.{taxonomy}.term` → `sunrice.term` → `sunrice::defaults.term`).

Every view receives a `TemplateContext`-derived payload: `entry`,
`resolved` (the hydrated translation), `collection`, `taxonomy`,
`term`, `entries` (archives), `locale`, `pageType`.

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
