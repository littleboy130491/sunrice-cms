<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\GlobalSet;

class GlobalSetPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.manage-globals');
    }

    public function view(mixed $user, GlobalSet $set): bool
    {
        return $user->can('sunrice.manage-globals');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.manage-globals');
    }

    public function update(mixed $user, GlobalSet $set): bool
    {
        return $user->can('sunrice.manage-globals');
    }

    public function delete(mixed $user, GlobalSet $set): bool
    {
        return $user->can('sunrice.manage-globals');
    }
}
