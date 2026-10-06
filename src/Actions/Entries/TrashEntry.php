<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Events\EntryDeleted;
use Sunrice\Models\Entry;

class TrashEntry
{
    public function handle(Entry $entry): Entry
    {
        $entry->delete();

        EntryDeleted::dispatch($entry);

        return $entry;
    }
}
