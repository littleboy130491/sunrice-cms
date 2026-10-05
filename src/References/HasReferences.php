<?php

declare(strict_types=1);

namespace Sunrice\References;

use Illuminate\Database\Eloquent\Model;
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
     * @return \Illuminate\Support\Collection<int, Reference>
     */
    public function referencedBy(): \Illuminate\Support\Collection
    {
        /** @var Model $this */
        return Reference::query()
            ->where('target_type', ReferenceSync::sourceType($this))
            ->where('target_id', $this->getKey())
            ->get();
    }

    /**
     * Records this model points at.
     *
     * @return \Illuminate\Support\Collection<int, Reference>
     */
    public function references(): \Illuminate\Support\Collection
    {
        /** @var Model $this */
        return Reference::query()
            ->where('source_type', ReferenceSync::sourceType($this))
            ->where('source_id', $this->getKey())
            ->get();
    }
}
