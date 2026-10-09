# Custom field types

A field type has two halves: **PHP** (validation, how the value is stored,
what templates receive) and an **admin control** (the input editors use).
How much you write depends on whether a built-in control already fits:

| You want… | Write | JavaScript? |
| --- | --- | --- |
| A built-in control with preset settings or extra rules (rating 1–5, SEO title ≤ 60 chars, a fixed list of options, a phone number) | A `CustomField` class | No |
| A different stored shape or template value, but a built-in control | A `CustomField` class overriding `normalize()` / `hydrate()` | No |
| A new kind of input (star widget, colour wheel, map picker) | A `FieldType` class **and** an admin script | Yes |

Register PHP classes in a service provider's `boot()`:

```php
use Sunrice\Facades\Sunrice;

Sunrice::registerField(RatingField::class);
```

Registered types appear in the blueprint and fieldset builders' **Add
field** list.

## Reusing a built-in control: `CustomField`

Extend `Sunrice\Fields\CustomField`, name the built-in `baseType()` whose
control and behaviour you build on, and add presets:

```php
use Sunrice\Fields\CustomField;

class RatingField extends CustomField
{
    public static function type(): string { return 'rating'; }

    // Edited with the number input; validated, stored and hydrated like a number.
    public static function baseType(): string { return 'number'; }

    // Merged under the field's own config (the blueprint can still override).
    public static function config(): array { return ['min' => 1, 'max' => 5]; }

    // Added after the base type's rules.
    public static function extraRules(): array { return ['integer', 'between:1,5']; }
}
```

```php
class SeoTitleField extends CustomField
{
    public static function type(): string { return 'seo_title'; }
    public static function baseType(): string { return 'text'; }
    public static function config(): array { return ['max' => 60]; } // the input stops at 60
    public static function extraRules(): array { return ['min:10']; }
}

class PriorityField extends CustomField
{
    public static function type(): string { return 'priority'; }
    public static function baseType(): string { return 'select'; }
    public static function config(): array
    {
        return ['options' => [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'high', 'label' => 'High'],
        ]];
    }
}
```

Everything else (normalising, hydration for templates, reference tracking,
translatability, filtering, sorting) is delegated to the base type with the
preset config applied. Override any of those methods to change it, e.g. to
give templates something richer:

```php
public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
{
    return ['value' => (int) $value, 'stars' => str_repeat('★', (int) $value)];
}
```

Useful base types: `text`, `textarea`, `rich_text`, `number`, `toggle`,
`select`, `date`, `link`, `asset`, `entries`, `terms`, `group`, `repeater`.

## A new kind of input: `FieldType` + an admin script

### 1. The PHP class

Extend `Sunrice\Fields\FieldType`:

```php
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

class ColorField extends FieldType
{
    public static function type(): string { return 'color'; }

    public function rules(array $field): array
    {
        return ['nullable', 'regex:/^#[0-9a-f]{6}$/i'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return is_string($value) && $value !== '' ? strtolower($value) : null;
    }

    // Optional: options shown in the field builder, read as field.config.*
    public function settingsSchema(): array
    {
        return [
            ['handle' => 'palette', 'type' => 'text', 'label' => 'Suggested colours (comma separated)'],
        ];
    }
}
```

| Method | Purpose |
| --- | --- |
| `type()` | The type key (also the admin component's key). |
| `rules($field)` | Laravel validation rules for the value. |
| `normalize($value, $field)` | The shape stored in the entry's data. |
| `hydrate($value, $field, HydrationContext $ctx)` | What templates receive (`$ctx->locale`, `$ctx->preview`, `entriesByIds()`, `termsByIds()`…). |
| `defaultValue($field)` | Value for new entries. |
| `references($value, $field)` | Entries/terms/assets this value points at, for "Used by" and usage tracking. |
| `translatableByDefault()` | Whether the value differs per language unless the field says otherwise. |
| `filterable()` / `sortCast()` | Admin table filtering; `'number'`/`'date'` casts for sorting and `EntryQuery::where()`. |
| `settingsSchema()` | Field-builder options. Setting `type`s: `text` (default), `number`, `toggle`, `select` / `multiselect` (with `options: [{value, label}]`), `key_value` (one `value|Label` per line). |
| `toAdminSchema($field)` | The field definition sent to the admin component (defaults to the definition itself). |

### 2. The admin component

The admin ships prebuilt, so your component is loaded as an **admin
script**: a JavaScript module that runs after the admin's own bundle. List
it in `config/sunrice.php`:

```php
'admin' => [
    // …
    'scripts' => ['js/sunrice-fields.js'],   // served through asset(); full URLs work too
    'styles' => [],                           // optional CSS for your components
],
```

or register it from a package's service provider:

```php
Sunrice::registerAdminScript('vendor/my-package/fields.js');
Sunrice::registerAdminStyle('vendor/my-package/fields.css');
```

The script registers a React component for the type on `window.Sunrice`:

```js
// public/js/sunrice-fields.js
const { React, registerField, ui } = window.Sunrice;
const h = React.createElement;

function ColorField({ field, value, onChange }) {
    const palette = String(field.config?.palette ?? '').split(',').map((c) => c.trim()).filter(Boolean);

    return h('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } },
        h('input', { type: 'color', value: value || '#000000', onChange: (e) => onChange(e.target.value) }),
        h(ui.Input, { value: value || '', placeholder: '#rrggbb', onChange: (e) => onChange(e.target.value), style: { maxWidth: '9rem' } }),
        ...palette.map((c) => h('button', {
            key: c, type: 'button', title: c, onClick: () => onChange(c),
            style: { width: 24, height: 24, borderRadius: 6, background: c, border: '1px solid #ccc' },
        })),
    );
}

registerField('color', ColorField);
```

Components receive:

| Prop | |
| --- | --- |
| `field` | The definition: `handle`, `label`, `required`, `config` (builder settings and presets)… |
| `value` | The current value (as stored by `normalize()`). |
| `onChange(value)` | Call with the new value. |
| `errors`, `pathPrefix` | Validation errors by path, and this field's path; the admin already shows `errors[pathPrefix]` under the field. |

`window.Sunrice` offers:

- `React`, `ReactDOM`: the admin's own copies. Always use these; a second
  React breaks hooks.
- `registerField(type, Component)`: add or replace a field control (also
  for a built-in type, to restyle it everywhere).
- `ui`: the admin's `Input`, `Textarea`, `Label`, `Button`, `Checkbox`,
  `Switch`, `Badge`, so custom fields match the admin.
- `fetchJson(url, init)`: `fetch` with the admin's CSRF token, returning
  JSON. Use it to call your own routes.
- `toast.success()` / `toast.error()`: admin notifications.
- More for whole pages (`registerPage`, Inertia, tables, cards): see
  [Writing a package](packages.md#what-windowsunrice-offers).

The admin's stylesheet only contains the Tailwind classes the admin itself
uses, so style custom components with inline styles or your own CSS
(`sunrice.admin.styles`) rather than arbitrary utility classes.

Writing JSX? Bundle the script with any tool (Vite library mode, esbuild…)
and treat `react` as external, mapped to `window.Sunrice.React`, e.g. with
esbuild: `--external:react --jsx-factory=Sunrice.React.createElement`.

Without an admin control, the editor shows a notice in the field's place
("no admin control for the field type…") instead of breaking the page.

## Using custom fields in templates

Custom fields behave like built-in ones: `$entry->get('rating')`
returns the hydrated value, `EntryQuery::where('rating', '>=', 4)` filters on
it (main-language value; set `sortCast()` to `'number'` for numeric
comparisons, which `CustomField` inherits from `number`).
