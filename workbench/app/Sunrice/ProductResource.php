<?php

declare(strict_types=1);

namespace Workbench\App\Sunrice;

use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Table\Column;
use Sunrice\Resources\Filter;
use Sunrice\Resources\Resource;
use Sunrice\Resources\ResourceField;
use Workbench\App\Models\Product;

/**
 * Workbench resource managing host-app Product models — the T14 fixture.
 */
class ProductResource extends Resource
{
    public static string $model = Product::class;

    public static function fields(): array
    {
        return [
            ResourceField::make('title')->type('text')->label('Title'),
            ResourceField::make('sku')->type('text')->label('SKU'),
            ResourceField::make('price')->type('number')->label('Price'),
            ResourceField::make('active')->type('toggle')->label('Active'),
            ResourceField::make('owner')->type('belongs_to')->label('Owner')
                ->relationship('owner', 'name'),
        ];
    }

    public static function columns(): array
    {
        return [
            new Column('title', 'Title', sortable: true),
            new Column('sku', 'SKU', sortable: true),
            new Column('price', 'Price', sortable: true, type: 'number'),
            new Column('active', 'Active', type: 'boolean'),
        ];
    }

    public static function filters(): array
    {
        return [Filter::boolean('active')];
    }

    public static function searchable(): array
    {
        return ['title', 'sku'];
    }

    public static function rules(?Model $record = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['boolean'],
        ];
    }
}
