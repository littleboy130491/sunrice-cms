<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Events\EntryDeleted;
use Sunrice\Models\Entry;
use Sunrice\Models\Redirect;
use Sunrice\Models\Reference;

class ForceDeleteEntry
{
    public function handle(Entry $entry): void
    {
        $entry->load('translations');

        Reference::query()
            ->whereIn('source_id', $entry->translations->pluck('id'))
            ->where('source_type', 'entry')
            ->delete();

        Redirect::query()->where('entry_id', $entry->id)->delete();

        $entry->forceDelete();

        EntryDeleted::dispatch($entry);
    }
}
