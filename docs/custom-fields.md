# Custom field types

Register a class extending `Sunrice\Fields\FieldType` in a service
provider:

```php
use Sunrice\Facades\Sunrice;
use Sunrice\Fields\FieldType;

class RatingField extends FieldType
{
    public static function type(): string { return 'rating'; }

    public function rules(array $field): array
    {
        return ['integer', 'min:1', 'max:5'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return (int) $value;
    }

    // Optional: hydrate(), references(), toAdminSchema(),
    // settingsSchema(), filterable(), sortCast(), defaultValue()
}

Sunrice::registerField(RatingField::class);
```

Key methods:

- `rules($field)` — Laravel validation rules for the field's value.
- `normalize($value, $field)` — stored-shape normalization (draft data).
- `hydrate($value, $field, HydrationContext $ctx)` — public-facing value
  (e.g. resolve asset ids to URLs). `HydrationContext` carries `locale`,
  `preview`, and `resolve` helpers.
- `references($value, $field)` — declared references (entries, terms,
  assets) so reference tracking and usage counts stay correct.
- `toAdminSchema($field)` — shape sent to the React admin.
- `settingsSchema()` — builder UI for field config.
- `filterable()` / `sortCast()` — admin table filtering/sorting support.
- `defaultValue($field)` — value used for new drafts.

The admin field component maps from `field.type` via the
`fieldComponents` registry in `resources/js/fields/registry.ts` —
register a React component with the same `type` key.
