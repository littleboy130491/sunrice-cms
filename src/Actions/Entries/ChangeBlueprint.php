<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Models\Blueprint;
use Sunrice\Models\Entry;

/**
 * Changes the entry's blueprint override. No data is moved: fields
 * absent from the new blueprint keep their stored values (hidden),
 * and switching back restores access to them.
 */
class ChangeBlueprint
{
    public function handle(Entry $entry, ?Blueprint $blueprint): Entry
    {
        $entry->blueprint_id = $blueprint?->id;
        $entry->save();

        return $entry;
    }
}
