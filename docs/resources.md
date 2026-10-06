# Model resources

Manage any existing Eloquent model through the admin by registering a
resource class:

```php
use Sunrice\Facades\Sunrice;
use Sunrice\Resources\Resource;
use Sunrice\Resources\ResourceField;
use Sunrice\Admin\Table\Column;
use Sunrice\Resources\Filter;

class ProductResource extends Resource
{
    public static string $model = Product::class;

    public static function fields(): array
    {
        return [
            ResourceField::make('title')->type('text'),
            ResourceField::make('price')->type('number'),
            ResourceField::make('active')->type('toggle'),
            ResourceField::make('owner')->type('belongs_to')->relationship('owner', 'name'),
            ResourceField::make('tags')->type('belongs_to_many')->relationship('tags', 'name'),
        ];
    }

    public static function columns(): array
    {
        return [new Column('title', 'Title', sortable: true)];
    }

    public static function filters(): array
    {
        return [Filter::boolean('active'), Filter::select('type')->options([...])];
    }

    public static function searchable(): array { return ['title', 'sku']; }
    public static function rules(?Model $record = null): array { /* ... */ }
}

Sunrice::registerResource(ProductResource::class);
```

- Admin URL: `{admin}/resources/{key}` where `key()` defaults to the
  kebab-case plural of the model basename (`products`).
- Permissions `sunrice.resources.{key}.{view|create|edit|delete|export}`
  are created by `sunrice:sync-permissions`.
- If the model has a policy (`Gate::getPolicyFor`), its abilities are
  enforced on top of the Sunrice permissions.
- `belongs_to`/`belongs_to_many` fields render searchable selects backed
  by `{admin}/api/resources/{key}/options/{field}?q=` and persist via
  `associate()`/`sync()`.
