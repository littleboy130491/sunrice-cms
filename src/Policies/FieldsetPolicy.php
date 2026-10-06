<?php

declare(strict_types=1);

namespace Sunrice\Policies;

class FieldsetPolicy extends StructurePolicy
{
    protected function area(): string
    {
        return 'fieldsets';
    }
}
