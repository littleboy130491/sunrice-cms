<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Taxonomy;

class DeleteTaxonomy
{
    /**
     * Soft-deletes a taxonomy. Its terms (and their links to entries) stay
     * in the database, hidden; creating a taxonomy with the same handle
     * brings them back. `sunrice:orphans --purge` deletes them for good.
     */
    public function handle(Taxonomy $taxonomy): void
    {
        $taxonomy->delete();

        ContentChanged::dispatch('taxonomy_deleted');
    }
}
