<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.roles.view');
    }

    public function view(mixed $user, Role $role): bool
    {
        return $user->can('sunrice.roles.view');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.roles.create');
    }

    /**
     * The super-admin role bypasses every check by name, so it can't be
     * renamed or edited (only another super admin passes Gate::before).
     */
    public function update(mixed $user, Role $role): bool
    {
        if ($role->name === config('sunrice.super_admin_role')) {
            return false;
        }

        return $user->can('sunrice.roles.edit');
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

        return $user->can('sunrice.roles.delete');
    }
}
