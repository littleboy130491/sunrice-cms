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

## Actions

Buttons on a record's editor ("Mark as paid", "Send invoice", "Download
PDF"), and with `bulk()` on the records selected in the list:

```php
use Sunrice\Resources\Action;
use Sunrice\Resources\ActionFailed;

public static function actions(): array
{
    return [
        Action::make('mark_paid')
            ->label('Mark as paid')
            ->confirm('Mark this order as paid?')
            ->visible(fn (Order $order) => $order->status === 'pending')
            ->handle(function (Order $order) {
                if (! $order->payment_reference) {
                    throw new ActionFailed('Add the payment reference first.');
                }
                $order->update(['status' => 'paid']);

                return "Order #{$order->number} is paid.";
            })
            ->bulk(),

        Action::make('refund')
            ->destructive()
            ->ability('delete')
            ->authorize(fn ($user, Order $order) => $user->can('commerce.orders.refund'))
            ->handle(fn (Order $order) => $order->refund()),

        Action::make('invoice')
            ->label('Download invoice')
            ->url(fn (Order $order) => route('commerce.invoices.pdf', $order)),
    ];
}
```

| Method | |
| --- | --- |
| `make($key)` | Lowercase key (`mark_paid`); `delete` is taken. The label defaults to its headline ("Mark Paid"). |
| `label()`, `confirm()`, `destructive()` | Button text, a question asked first, a red button. |
| `handle(fn ($record))` | What it does. Return a message to show, or throw `ActionFailed` to show an error instead. Without a message the admin shows "{label}: done." |
| `url(fn ($record), newTab: true)` | Open an address instead (a PDF, the record on the site). |
| `visible(fn ($record))` | Offer it only for some records. Posting it for a hidden record is refused; bulk runs skip those records. |
| `bulk()` | Also a button for the list's selection. The handler runs once per record; the admin reports how many were done, skipped or failed. |
| `ability($ability)` | The resource permission needed: `edit` (default), `view`, `create`, `delete` or `export`. The model policy's matching ability applies too. |
| `authorize(fn ($user, $record))` | An extra check, e.g. a [package permission](packages.md#permissions). |

Actions run on `POST {admin}/resources/{key}/{id}/actions/{action}`
(bulk: `POST {admin}/resources/{key}/bulk` with `action` and `ids`). The
editor reloads the record afterwards, so changed values show at once.
