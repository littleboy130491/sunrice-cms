<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Events\EntryRestored;
use Sunrice\Models\Entry;

class RestoreEntry
{
    public function handle(Entry $entry): Entry
    {
        $entry->restore();

        EntryRestored::dispatch($entry);

        return $entry;
    }
}
