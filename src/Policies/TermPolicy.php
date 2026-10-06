<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\Term;

class TermPolicy
{
    public function view(mixed $user, Term|int $termOrTaxonomy): bool
    {
        return $user->can('sunrice.terms.'.$this->taxonomyId($termOrTaxonomy).'.view');
    }

    public function viewAny(mixed $user, int $taxonomyId): bool
    {
        return $user->can("sunrice.terms.{$taxonomyId}.view");
    }

    /**
     * Reordering a taxonomy changes every term's position.
     */
    public function reorder(mixed $user, int $taxonomyId): bool
    {
        return $user->can("sunrice.terms.{$taxonomyId}.edit");
    }

    public function create(mixed $user, int $taxonomyId): bool
    {
        return $user->can("sunrice.terms.{$taxonomyId}.create");
    }

    public function update(mixed $user, Term $term): bool
    {
        return $user->can("sunrice.terms.{$term->taxonomy_id}.edit");
    }

    public function delete(mixed $user, Term $term): bool
    {
        return $user->can("sunrice.terms.{$term->taxonomy_id}.delete");
    }

    protected function taxonomyId(Term|int $termOrTaxonomy): int
    {
        return $termOrTaxonomy instanceof Term ? $termOrTaxonomy->taxonomy_id : $termOrTaxonomy;
    }
}
