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
     * @return array<int, array{label: string, items: array<int, array{label: string, href: string}>}>
     */
    public function for(mixed $user): array
    {
        $groups = [];

        $content = [];
        foreach (Collection::query()->orderBy('sort_order')->orderBy('title')->get() as $collection) {
            if ($user->can('viewAny', [Entry::class, $collection->id])) {
                $content[] = ['label' => $collection->title, 'href' => "collections/{$collection->handle}/entries"];
            }
        }
        if ($content !== []) {
            $groups[] = ['label' => 'Content', 'items' => $content];
        }

        $taxonomies = [];
        foreach (Taxonomy::query()->orderBy('handle')->get() as $taxonomy) {
            if ($user->can('view', $taxonomy->id)) {
                $taxonomies[] = ['label' => $taxonomy->title, 'href' => "taxonomies/{$taxonomy->handle}"];
            }
        }
        if ($taxonomies !== []) {
            $groups[] = ['label' => 'Taxonomies', 'items' => $taxonomies];
        }

        $structure = [];
        if ($user->can('sunrice.manage-navigation')) {
            $structure[] = ['label' => 'Menus', 'href' => 'menus'];
        }
        if ($user->can('sunrice.manage-globals')) {
            $structure[] = ['label' => 'Globals', 'href' => 'globals'];
        }
        if ($user->can('viewAny', Form::class) || $user->can('sunrice.manage-structure')) {
            $forms = Form::query()->orderBy('handle')->get()
                ->filter(fn (Form $form) => $user->can('view', $form))
                ->map(fn (Form $form) => ['label' => $form->title, 'href' => "forms/{$form->handle}"])
                ->values()->all();
            if ($forms !== []) {
                $groups[] = ['label' => 'Forms', 'items' => $forms];
            }
        }

        if ($structure !== []) {
            $groups[] = ['label' => 'Structure', 'items' => $structure];
        }

        foreach (app(Sunrice::class)->resources() as $key => $resource) {
            if ($user->can("sunrice.resources.{$key}.view")) {
                $groups[] = ['label' => 'Resources', 'items' => [['label' => $resource::label(), 'href' => "resources/{$key}"]]];
            }
        }

        $admin = [];
        if ($user->can('sunrice.assets.view')) {
            $admin[] = ['label' => 'Assets', 'href' => 'assets'];
        }
        if ($user->can('sunrice.manage-structure')) {
            $admin[] = ['label' => 'Collections', 'href' => 'structure/collections'];
            $admin[] = ['label' => 'Blueprints', 'href' => 'structure/blueprints'];
            $admin[] = ['label' => 'Fieldsets', 'href' => 'structure/fieldsets'];
            $admin[] = ['label' => 'Taxonomies', 'href' => 'structure/taxonomies'];
        }
        if ($user->can('sunrice.manage-users')) {
            $admin[] = ['label' => 'Users', 'href' => 'users'];
        }
        if ($user->can('sunrice.manage-roles')) {
            $admin[] = ['label' => 'Roles', 'href' => 'roles'];
        }
        if ($admin !== []) {
            $groups[] = ['label' => 'Manage', 'items' => $admin];
        }

        return $groups;
    }
}
