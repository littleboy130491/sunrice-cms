<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\Menu;

class MenuPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.menus.view');
    }

    public function view(mixed $user, Menu $menu): bool
    {
        return $user->can('sunrice.menus.view');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.menus.create');
    }

    public function update(mixed $user, Menu $menu): bool
    {
        return $user->can('sunrice.menus.edit');
    }

    public function delete(mixed $user, Menu $menu): bool
    {
        return $user->can('sunrice.menus.delete');
    }
}
