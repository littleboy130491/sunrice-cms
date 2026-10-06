<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Taxonomy;

class DeleteTaxonomy
{
    /**
     * Deletes a taxonomy with its terms and translations.
     */
    public function handle(Taxonomy $taxonomy): void
    {
        foreach ($taxonomy->terms()->withTrashed()->get() as $term) {
            $term->translations()->delete();
            $term->entries()->detach();
            $term->forceDelete();
        }

        $taxonomy->delete();
        ContentChanged::dispatch('taxonomy_deleted');
    }
}
