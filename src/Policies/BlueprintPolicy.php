<?php

declare(strict_types=1);

namespace Sunrice\Policies;

class BlueprintPolicy extends StructurePolicy
{
    protected function area(): string
    {
        return 'blueprints';
    }
}
