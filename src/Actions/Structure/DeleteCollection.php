<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;

class DeleteCollection
{
    /**
     * Soft-deletes a collection. Its entries stay in the database but are
     * hidden in the admin and on the site (HiddenWithParent); creating a
     * collection with the same handle brings them back. Their permissions
     * are kept for that. `sunrice:orphans --purge` deletes them for good.
     */
    public function handle(Collection $collection): void
    {
        $collection->delete();

        ContentChanged::dispatch('collection_deleted');
    }
}
