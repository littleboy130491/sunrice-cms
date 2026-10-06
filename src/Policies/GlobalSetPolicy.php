<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\GlobalSet;

class GlobalSetPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.globals.view');
    }

    public function view(mixed $user, GlobalSet $set): bool
    {
        return $user->can('sunrice.globals.view');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.globals.create');
    }

    public function update(mixed $user, GlobalSet $set): bool
    {
        return $user->can('sunrice.globals.edit');
    }

    public function delete(mixed $user, GlobalSet $set): bool
    {
        return $user->can('sunrice.globals.delete');
    }
}
