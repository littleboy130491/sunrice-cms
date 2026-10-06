<?php

declare(strict_types=1);

namespace Sunrice\References;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Sunrice\Models\Reference;

/**
 * Query helpers for records that appear in the references table.
 *
 * @mixin Model
 */
trait HasReferences
{
    /**
     * Records pointing at this model.
     *
     * @return Collection<int, Reference>
     */
    public function referencedBy(): Collection
    {
        return Reference::query()
            ->where('target_type', ReferenceSync::sourceType($this))
            ->where('target_id', $this->getKey())
            ->get();
    }

    /**
     * Records this model points at.
     *
     * @return Collection<int, Reference>
     */
    public function references(): Collection
    {
        return Reference::query()
            ->where('source_type', ReferenceSync::sourceType($this))
            ->where('source_id', $this->getKey())
            ->get();
    }
}
