<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\Asset;

class AssetPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('sunrice.assets.view');
    }

    public function view(mixed $user, Asset $asset): bool
    {
        return $user->can('sunrice.assets.view');
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.assets.upload');
    }

    public function update(mixed $user, Asset $asset): bool
    {
        return $user->can('sunrice.assets.edit');
    }

    public function delete(mixed $user, Asset $asset): bool
    {
        return $user->can('sunrice.assets.delete');
    }

    public function createFolder(mixed $user): bool
    {
        return $user->can('sunrice.assets.upload');
    }

    public function updateFolder(mixed $user): bool
    {
        return $user->can('sunrice.assets.edit');
    }

    public function deleteFolder(mixed $user): bool
    {
        return $user->can('sunrice.assets.delete');
    }
}
