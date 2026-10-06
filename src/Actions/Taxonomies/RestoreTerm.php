<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Sunrice\Events\ContentChanged;
use Sunrice\Models\Term;

class RestoreTerm
{
    /**
     * Restores a term and any descendants trashed alongside it.
     */
    public function handle(Term $term): Term
    {
        $term->restore();
        Term::query()->withTrashed()
            ->whereIn('id', $term->descendantIds())
            ->whereKeyNot($term->id)
            ->each(fn (Term $child) => $child->restore());
        ContentChanged::dispatch('term_restored');

        return $term->refresh();
    }
}
