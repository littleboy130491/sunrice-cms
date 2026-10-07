<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Events\EntryDeleted;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Redirect;
use Sunrice\Models\Reference;
use Sunrice\Models\Scopes\HiddenWithParent;

class ForceDeleteEntry
{
    public function handle(Entry $entry): void
    {
        // Also the translations of an entry hidden with its deleted collection.
        $translationIds = EntryTranslation::query()->withoutGlobalScope(HiddenWithParent::class)
            ->where('entry_id', $entry->id)->pluck('id');

        Reference::query()
            ->whereIn('source_id', $translationIds)
            ->where('source_type', 'entry')
            ->delete();

        Redirect::query()->where('entry_id', $entry->id)->delete();

        $entry->forceDelete();

        EntryDeleted::dispatch($entry);
    }
}
