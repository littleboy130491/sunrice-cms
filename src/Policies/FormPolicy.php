<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\Form;

/**
 * Form abilities: sunrice.forms.{id}.{edit|view-submissions|
 * export-submissions|delete-submissions}. Creating/deleting forms
 * themselves is structure management.
 */
class FormPolicy
{
    public function view(mixed $user, Form $form): bool
    {
        return $user->can("sunrice.forms.{$form->id}.view-submissions")
            || $user->can("sunrice.forms.{$form->id}.edit");
    }

    public function create(mixed $user): bool
    {
        return $user->can('sunrice.manage-structure');
    }

    public function update(mixed $user, Form $form): bool
    {
        return $user->can("sunrice.forms.{$form->id}.edit");
    }

    public function delete(mixed $user, Form $form): bool
    {
        return $user->can('sunrice.manage-structure');
    }

    public function viewSubmissions(mixed $user, Form $form): bool
    {
        return $user->can("sunrice.forms.{$form->id}.view-submissions");
    }

    public function exportSubmissions(mixed $user, Form $form): bool
    {
        return $user->can("sunrice.forms.{$form->id}.export-submissions");
    }

    public function deleteSubmissions(mixed $user, Form $form): bool
    {
        return $user->can("sunrice.forms.{$form->id}.delete-submissions");
    }
}
