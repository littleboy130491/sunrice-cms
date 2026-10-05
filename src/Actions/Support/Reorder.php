<?php

declare(strict_types=1);

namespace Sunrice\Actions\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Generic drag-and-drop reorder: updates sort_order (and optionally
 * parent_id) for an ordered list of ids in one transaction. Used by
 * entries, terms and menu items.
 */
class Reorder
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int|array{id: int, parent_id?: ?int}>  $items  ordered ids or id+parent pairs
     */
    public function handle(string $modelClass, array $items): void
    {
        DB::transaction(function () use ($modelClass, $items): void {
            foreach ($items as $position => $item) {
                $id = is_array($item) ? $item['id'] : $item;
                $attributes = ['sort_order' => $position];
                if (is_array($item) && array_key_exists('parent_id', $item)) {
                    $attributes['parent_id'] = $item['parent_id'];
                }
                $modelClass::query()->whereKey($id)->update($attributes);
            }
        });
    }
}
