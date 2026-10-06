<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Form;
use Sunrice\Models\Taxonomy;
use Sunrice\Sunrice;

/**
 * Builds the admin sidebar tree for the current user: only sections
 * they have permission to see.
 */
class Navigation
{
    /**
     * Each item carries a lucide icon name (kebab-case) for the sidebar.
     *
     * @return array<int, array{label: string, items: array<int, array{label: string, href: string, icon: string}>}>
     */
    public function for(mixed $user): array
    {
        $groups = [];

        $content = [];
        foreach (Collection::query()->orderBy('sort_order')->orderBy('title')->get() as $collection) {
            if ($user->can('viewAny', [Entry::class, $collection->id])) {
                $content[] = [
                    'label' => $collection->title,
                    'href' => "collections/{$collection->handle}/entries",
                    'icon' => (string) ($collection->setting('icon') ?: 'file-text'),
                ];
            }
        }
        if ($content !== []) {
            $groups[] = ['label' => 'Content', 'items' => $content];
        }

        $taxonomies = [];
        foreach (Taxonomy::query()->orderBy('handle')->get() as $taxonomy) {
            if ($user->can('view', $taxonomy->id)) {
                $taxonomies[] = ['label' => $taxonomy->title, 'href' => "taxonomies/{$taxonomy->handle}", 'icon' => 'tags'];
            }
        }
        if ($taxonomies !== []) {
            $groups[] = ['label' => 'Taxonomies', 'items' => $taxonomies];
        }

        $structure = [];
        if ($user->can('sunrice.manage-navigation')) {
            $structure[] = ['label' => 'Menus', 'href' => 'menus', 'icon' => 'list-tree'];
        }
        if ($user->can('sunrice.manage-globals')) {
            $structure[] = ['label' => 'Globals', 'href' => 'globals', 'icon' => 'globe'];
        }
        if ($user->can('viewAny', Form::class) || $user->can('sunrice.manage-structure')) {
            $forms = Form::query()->orderBy('handle')->get()
                ->filter(fn (Form $form) => $user->can('view', $form))
                ->map(fn (Form $form) => ['label' => $form->title, 'href' => "forms/{$form->handle}", 'icon' => 'inbox'])
                ->values()->all();
            if ($forms !== []) {
                $groups[] = ['label' => 'Forms', 'items' => $forms];
            }
        }

        if ($structure !== []) {
            $groups[] = ['label' => 'Structure', 'items' => $structure];
        }

        $resources = [];
        foreach (app(Sunrice::class)->resources() as $key => $resource) {
            if ($user->can("sunrice.resources.{$key}.view")) {
                $resources[] = ['label' => $resource::label(), 'href' => "resources/{$key}", 'icon' => 'database'];
            }
        }
        if ($resources !== []) {
            $groups[] = ['label' => 'Resources', 'items' => $resources];
        }

        $admin = [];
        if ($user->can('sunrice.assets.view')) {
            $admin[] = ['label' => 'Assets', 'href' => 'assets', 'icon' => 'image'];
        }
        if ($user->can('sunrice.manage-structure')) {
            $admin[] = ['label' => 'Collections', 'href' => 'structure/collections', 'icon' => 'library'];
            $admin[] = ['label' => 'Blueprints', 'href' => 'structure/blueprints', 'icon' => 'layout-template'];
            $admin[] = ['label' => 'Fieldsets', 'href' => 'structure/fieldsets', 'icon' => 'blocks'];
            $admin[] = ['label' => 'Taxonomies', 'href' => 'structure/taxonomies', 'icon' => 'tags'];
        }
        if ($user->can('sunrice.manage-users')) {
            $admin[] = ['label' => 'Users', 'href' => 'users', 'icon' => 'users'];
        }
        if ($user->can('sunrice.manage-roles')) {
            $admin[] = ['label' => 'Roles', 'href' => 'roles', 'icon' => 'shield'];
        }
        if ($admin !== []) {
            $groups[] = ['label' => 'Manage', 'items' => $admin];
        }

        return $groups;
    }
}
