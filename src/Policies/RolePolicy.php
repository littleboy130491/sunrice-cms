<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.manage-roles');
    }

    public function view(mixed $user, Role $role): bool
    {
        return $user->can('sunrice.manage-roles');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.manage-roles');
    }

    public function update(mixed $user, Role $role): bool
    {
        return $user->can('sunrice.manage-roles');
    }

    /**
     * The configured super-admin role can never be deleted through
     * the admin panel.
     */
    public function delete(mixed $user, Role $role): bool
    {
        if ($role->name === config('sunrice.super_admin_role')) {
            return false;
        }

        return $user->can('sunrice.manage-roles');
    }
}
