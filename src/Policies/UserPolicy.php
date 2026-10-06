<?php

declare(strict_types=1);

namespace Sunrice\Policies;

/**
 * Users need sunrice.users.<action>. Super admins can only be edited or
 * deleted by another super admin (who passes Gate::before), so a user
 * manager can't take over a super-admin account.
 */
class UserPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.users.view');
    }

    public function view(mixed $user, mixed $model): bool
    {
        return $user->can('sunrice.users.view');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.users.create');
    }

    public function update(mixed $user, mixed $model): bool
    {
        return $user->can('sunrice.users.edit') && ! static::isSuperAdmin($model);
    }

    public function delete(mixed $user, mixed $model): bool
    {
        return $user->can('sunrice.users.delete')
            && ! static::isSuperAdmin($model)
            && $model->getAuthIdentifier() !== $user->getAuthIdentifier();
    }

    /**
     * Only a super admin may give or take away the super-admin role.
     */
    public function assignSuperAdmin(mixed $user): bool
    {
        return false;
    }

    public static function isSuperAdmin(mixed $user): bool
    {
        $role = config('sunrice.super_admin_role');

        return $role !== null && is_object($user) && method_exists($user, 'hasRole') && $user->hasRole($role);
    }
}
