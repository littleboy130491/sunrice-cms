<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Form;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
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
            if ($user->can('viewAny', [Term::class, $taxonomy->id])) {
                $taxonomies[] = ['label' => $taxonomy->title, 'href' => "taxonomies/{$taxonomy->handle}", 'icon' => 'tags'];
            }
        }
        if ($taxonomies !== []) {
            $groups[] = ['label' => 'Taxonomies', 'items' => $taxonomies];
        }

        $structure = [];
        if ($user->can('sunrice.menus.view')) {
            $structure[] = ['label' => 'Menus', 'href' => 'menus', 'icon' => 'list-tree'];
        }
        if ($user->can('sunrice.globals.view')) {
            $structure[] = ['label' => 'Globals', 'href' => 'globals', 'icon' => 'globe'];
        }
        // One Forms entry: the forms list links each form's submissions and builder.
        $canSeeForms = $user->can('create', Form::class)
            || Form::query()->get()->contains(fn (Form $form) => $user->can('view', $form));
        if ($canSeeForms) {
            $structure[] = ['label' => 'Forms', 'href' => 'forms', 'icon' => 'inbox'];
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
        $manage = [
            ['collections', 'Collections', 'structure/collections', 'library'],
            ['blueprints', 'Blueprints', 'structure/blueprints', 'layout-template'],
            ['fieldsets', 'Fieldsets', 'structure/fieldsets', 'blocks'],
            ['taxonomies', 'Taxonomies', 'structure/taxonomies', 'tags'],
            ['users', 'Users', 'users', 'users'],
            ['roles', 'Roles', 'roles', 'shield'],
        ];
        foreach ($manage as [$area, $label, $href, $icon]) {
            if ($user->can("sunrice.{$area}.view")) {
                $admin[] = ['label' => $label, 'href' => $href, 'icon' => $icon];
            }
        }
        if ($user->can('sunrice.settings.edit')) {
            $admin[] = ['label' => 'Settings', 'href' => 'settings', 'icon' => 'settings'];
        }
        if ($admin !== []) {
            $groups[] = ['label' => 'Manage', 'items' => $admin];
        }

        return $groups;
    }
}
