<?php

declare(strict_types=1);

namespace Sunrice\Fields;

use Illuminate\Support\Collection;

/**
 * Hydrated repeater rows or flexible blocks, hidden items already
 * removed. Items given a key in the admin can be fetched directly:
 *
 *     $entry->get('sections')->byKey('hero')
 *     $entry->get('features')->byKey('pricing')['title']
 *
 * @extends Collection<int, mixed>
 */
class Items extends Collection
{
    /**
     * The first item whose key matches, or null.
     */
    public function byKey(string $key): mixed
    {
        return $this->first(fn ($item) => static::keyOf($item) === $key);
    }

    /**
     * Whether an item with this key exists (and is shown).
     */
    public function hasKey(string $key): bool
    {
        return $this->byKey($key) !== null;
    }

    protected static function keyOf(mixed $item): ?string
    {
        if ($item instanceof Block) {
            return $item->key;
        }

        return is_array($item) && is_string($item['_key'] ?? null) ? $item['_key'] : null;
    }
}
