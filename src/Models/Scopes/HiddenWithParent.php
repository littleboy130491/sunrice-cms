<?php

declare(strict_types=1);

namespace Sunrice\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides rows whose collection or taxonomy was deleted. Deleting one only
 * soft-deletes it: its entries and terms stay in the database (hidden in
 * the admin and on the site) until it is re-created with the same handle
 * or purged with `sunrice:orphans --purge`.
 *
 * Remove with ->withoutGlobalScope(HiddenWithParent::class).
 */
class HiddenWithParent implements Scope
{
    /**
     * @param  string  $column  foreign key on the model (collection_id, taxonomy_id)
     * @param  string  $parentTable  the soft-deleting parent table
     */
    public function __construct(protected string $column, protected string $parentTable) {}

    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotIn(
            $model->qualifyColumn($this->column),
            fn ($query) => $query->select('id')->from($this->parentTable)->whereNotNull('deleted_at'),
        );
    }
}
