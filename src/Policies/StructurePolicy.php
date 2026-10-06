<?php

declare(strict_types=1);

namespace Sunrice\Policies;

/**
 * Collections, blueprints, fieldsets and taxonomies (structure):
 * one permission — sunrice.manage-structure.
 */
class StructurePolicy
{
    public function manage(mixed $user): bool
    {
        return $user->can('sunrice.manage-structure');
    }

    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.manage-structure');
    }

    public function create(mixed $user): bool
    {
        return $this->manage($user);
    }

    public function update(mixed $user): bool
    {
        return $this->manage($user);
    }

    public function delete(mixed $user): bool
    {
        return $this->manage($user);
    }
}
