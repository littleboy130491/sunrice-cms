<?php

declare(strict_types=1);

namespace Sunrice\Policies;

class CollectionPolicy extends StructurePolicy
{
    protected function area(): string
    {
        return 'collections';
    }
}
