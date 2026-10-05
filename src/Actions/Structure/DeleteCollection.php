<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;
use Sunrice\Permissions\SyncPermissions;

class DeleteCollection
{
    /**
     * Deletes a collection and (via FK cascade) its entries,
     * translations and pivots. Refuses while a collection's archive
     * setting or another collection's archive_entries_in points at it
     * is not checked — entry archive fields merely store a handle.
     */
    public function handle(Collection $collection): void
    {
        $collection->delete();

        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('collection_deleted');
    }
}
