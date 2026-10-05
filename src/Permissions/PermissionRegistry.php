<?php

declare(strict_types=1);

namespace Sunrice\Permissions;

use Sunrice\Models\Collection;
use Sunrice\Models\Form;
use Sunrice\Models\Taxonomy;
use Sunrice\Sunrice;

/**
 * Produces the full permission matrix: entity permissions keyed by id
 * (renaming a collection never breaks permissions) plus global ones.
 * Every entry carries a human label and group for the role editor.
 */
class PermissionRegistry
{
    public const ENTRY_ACTIONS = ['view', 'create', 'edit', 'edit-own', 'delete', 'delete-own', 'publish'];

    public const TERM_ACTIONS = ['view', 'create', 'edit', 'delete'];

    public const FORM_ACTIONS = ['edit', 'view-submissions', 'export-submissions', 'delete-submissions'];

    public const RESOURCE_ACTIONS = ['view', 'create', 'edit', 'delete'];

    /**
     * @return array<int, array{name: string, label: string, group: string}>
     */
    public function all(): array
    {
        return array_merge(
            $this->global(),
            $this->forEntries(),
            $this->forTaxonomies(),
            $this->forForms(),
            $this->forResources(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_column($this->all(), 'name');
    }

    /**
     * @return array<int, array{name: string, label: string, group: string}>
     */
    public function global(): array
    {
        $defs = [
            ['sunrice.access-admin', 'Access the admin panel', 'Admin'],
            ['sunrice.manage-structure', 'Manage collections, blueprints, fieldsets and taxonomies', 'Structure'],
            ['sunrice.manage-navigation', 'Manage menus', 'Structure'],
            ['sunrice.manage-globals', 'Manage globals', 'Content'],
            ['sunrice.manage-users', 'Manage users', 'Admin'],
            ['sunrice.manage-roles', 'Manage roles', 'Admin'],
            ['sunrice.manage-settings', 'Manage settings', 'Admin'],
            ['sunrice.assets.view', 'View assets', 'Assets'],
            ['sunrice.assets.upload', 'Upload assets', 'Assets'],
            ['sunrice.assets.delete', 'Delete assets', 'Assets'],
        ];

        return array_map(fn (array $d) => ['name' => $d[0], 'label' => $d[1], 'group' => $d[2]], $defs);
    }

    /**
     * @return array<int, array{name: string, label: string, group: string}>
     */
    public function forEntries(): array
    {
        $out = [];
        foreach (Collection::query()->orderBy('handle')->get() as $collection) {
            foreach (static::ENTRY_ACTIONS as $action) {
                $out[] = [
                    'name' => "sunrice.entries.{$collection->id}.{$action}",
                    'label' => "{$collection->title}: {$action}",
                    'group' => "Entries — {$collection->title}",
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{name: string, label: string, group: string}>
     */
    public function forTaxonomies(): array
    {
        $out = [];
        foreach (Taxonomy::query()->orderBy('handle')->get() as $taxonomy) {
            foreach (static::TERM_ACTIONS as $action) {
                $out[] = [
                    'name' => "sunrice.terms.{$taxonomy->id}.{$action}",
                    'label' => "{$taxonomy->title}: {$action}",
                    'group' => "Terms — {$taxonomy->title}",
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{name: string, label: string, group: string}>
     */
    public function forForms(): array
    {
        $out = [];
        foreach (Form::query()->orderBy('handle')->get() as $form) {
            foreach (static::FORM_ACTIONS as $action) {
                $out[] = [
                    'name' => "sunrice.forms.{$form->id}.{$action}",
                    'label' => "{$form->title}: {$action}",
                    'group' => "Forms — {$form->title}",
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{name: string, label: string, group: string}>
     */
    public function forResources(): array
    {
        $out = [];
        foreach (app(Sunrice::class)->resources() as $key => $resource) {
            foreach (static::RESOURCE_ACTIONS as $action) {
                $out[] = [
                    'name' => "sunrice.resources.{$key}.{$action}",
                    'label' => "{$resource::title()}: {$action}",
                    'group' => "Resources — {$resource::title()}",
                ];
            }
        }

        return $out;
    }

    /**
     * Names that are entity-scoped (should be deleted when the entity
     * is deleted) versus global ones (always kept).
     *
     * @return array<int, string>
     */
    public function scopedNames(): array
    {
        return array_column(
            array_merge($this->forEntries(), $this->forTaxonomies(), $this->forForms(), $this->forResources()),
            'name',
        );
    }
}
