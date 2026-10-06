<?php

declare(strict_types=1);

namespace Sunrice\Policies;

/**
 * Collections, blueprints, fieldsets and taxonomies each have their own
 * view/create/edit/delete permissions: sunrice.<area>.<action>.
 */
abstract class StructurePolicy
{
    /** Permission area, e.g. 'collections'. */
    abstract protected function area(): string;

    public function viewAny(mixed $user): bool
    {
        return $user->can("sunrice.{$this->area()}.view");
    }

    public function view(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $user->can("sunrice.{$this->area()}.create");
    }

    public function update(mixed $user): bool
    {
        return $user->can("sunrice.{$this->area()}.edit");
    }

    public function delete(mixed $user): bool
    {
        return $user->can("sunrice.{$this->area()}.delete");
    }
}
