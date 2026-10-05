<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

/**
 * Test fixture: denies the `edit` ability so T14.2 can verify the
 * model's own policy is enforced alongside Sunrice permissions.
 */
class ProductPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }

    public function create(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return false;
    }
}
