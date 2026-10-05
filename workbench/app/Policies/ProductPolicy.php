<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

/**
 * Test fixture: denies the `edit` ability so T14.2 can verify the
 * model's own policy is enforced alongside Sunrice permissions.
 */
class ProductPolicy
{
    public function update(): bool
    {
        return false;
    }
}
