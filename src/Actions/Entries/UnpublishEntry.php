<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Events\EntryUnpublished;
use Sunrice\Models\Entry;

class UnpublishEntry
{
    public function handle(Entry $entry): Entry
    {
        $entry->status = 'draft';
        $entry->save();

        EntryUnpublished::dispatch($entry);

        return $entry;
    }
}
