<?php

declare(strict_types=1);

namespace Sunrice\Policies;

class TaxonomyPolicy extends StructurePolicy
{
    protected function area(): string
    {
        return 'taxonomies';
    }
}
