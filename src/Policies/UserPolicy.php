<?php

declare(strict_types=1);

namespace Sunrice\Policies;

class UserPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.manage-users');
    }

    public function view(mixed $user, mixed $model): bool
    {
        return $user->can('sunrice.manage-users');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.manage-users');
    }

    public function update(mixed $user, mixed $model): bool
    {
        return $user->can('sunrice.manage-users');
    }

    public function delete(mixed $user, mixed $model): bool
    {
        return $user->can('sunrice.manage-users')
            && $model->getAuthIdentifier() !== $user->getAuthIdentifier();
    }
}
