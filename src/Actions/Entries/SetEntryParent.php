<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Closure;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;

/**
 * Places an entry under another one in a hierarchical collection (or
 * at the top level with null). Its URL, and its children's, change at
 * once; the old addresses redirect.
 */
class SetEntryParent
{
    /**
     * @throws ValidationException
     */
    public function handle(Entry $entry, ?int $parentId): void
    {
        if (! $entry->collection->isHierarchical() || $parentId === $entry->parent_id) {
            return;
        }
        if ($parentId !== null) {
            validator(['parent_id' => $parentId], ['parent_id' => ['integer', static::rule($entry->collection, $entry)]])->validate();
        }

        $entry->update(['parent_id' => $parentId]);
        ContentChanged::dispatch('entry_parent');
    }

    /**
     * A parent must be another entry of the same collection, not one of
     * the entry's own children (that would make a loop), within the
     * nesting limit.
     */
    public static function rule(Collection $collection, ?Entry $entry): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($collection, $entry): void {
            $parent = Entry::query()->where('collection_id', $collection->id)->find((int) $value);
            if ($parent === null) {
                $fail('Choose an entry of this collection as the parent.');
            } elseif ($entry !== null && ($parent->id === $entry->id || in_array($parent->id, $entry->descendantIds(), true))) {
                $fail('An entry can\'t be placed under itself or one of its children.');
            } elseif (count($parent->ancestors()) >= Entry::MAX_DEPTH - 1) {
                $fail('Entries can be nested at most '.Entry::MAX_DEPTH.' levels deep.');
            }
        };
    }
}
