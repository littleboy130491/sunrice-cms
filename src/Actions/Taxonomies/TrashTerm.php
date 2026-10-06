<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Term;

class TrashTerm
{
    /**
     * Soft-deletes a term and its descendants (hierarchical taxonomies).
     */
    public function handle(Term $term): void
    {
        Term::query()->whereIn('id', $term->descendantIds())
            ->whereKeyNot($term->id)
            ->each(fn (Term $child) => $child->delete());
        $term->delete();
        ContentChanged::dispatch('term_trashed');
    }
}
