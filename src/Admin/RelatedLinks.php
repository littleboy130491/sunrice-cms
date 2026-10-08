<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Support\Facades\Gate;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

/**
 * "Go to" links for the entry and term editors' ⋮ menu: the pages an
 * editor tends to need next (list, settings, blueprint, listing page…),
 * only those the signed-in user may open.
 *
 * @phpstan-type Link array{label: string, href: string, external?: bool, group: string}
 */
class RelatedLinks
{
    /** @return array<int, array<string, mixed>> */
    public static function forEntry(Collection $collection, ?Entry $entry): array
    {
        $links = [];
        $add = function (string $group, string $label, string $href, bool $external = false) use (&$links): void {
            $links[] = array_filter(['group' => $group, 'label' => $label, 'href' => $href, 'external' => $external ?: null]);
        };

        $add($collection->title, "All {$collection->title}", route('sunrice.admin.entries.index', $collection));
        if ($entry?->parent !== null) {
            $parent = $entry->parent->mainTranslation();
            $add($collection->title, 'Parent: '.($parent === null ? '#'.$entry->parent->id : $parent->title), route('sunrice.admin.entries.edit', $entry->parent));
        }
        if ($collection->setting('has_archive')) {
            if (Gate::allows("sunrice.entries.{$collection->id}.edit") || Gate::allows("sunrice.entries.{$collection->id}.translate")) {
                $add('Listing page', 'Edit listing page', route('sunrice.admin.listing.edit', $collection));
            }
            $add('Listing page', 'View listing page', app(UrlGenerator::class)->archive($collection, Locales::main()), true);
        }

        foreach ($collection->taxonomies as $taxonomy) {
            if (Gate::allows('viewAny', [Term::class, $taxonomy->id])) {
                $add('Terms', $taxonomy->title, route('sunrice.admin.terms.index', $taxonomy));
            }
        }

        if (Gate::allows('update', $collection)) {
            $add('Structure', 'Collection settings', route('sunrice.admin.structure.collections.edit', $collection));
        }
        $blueprint = $entry?->activeBlueprint() ?? $collection->blueprint;
        if ($blueprint !== null && Gate::allows('update', $blueprint)) {
            $add('Structure', "Blueprint: {$blueprint->title}", route('sunrice.admin.structure.blueprints.edit', $blueprint));
        }

        return $links;
    }

    /** @return array<int, array<string, mixed>> */
    public static function forTerm(Taxonomy $taxonomy, ?Term $term): array
    {
        $links = [];
        $add = function (string $group, string $label, string $href) use (&$links): void {
            $links[] = ['group' => $group, 'label' => $label, 'href' => $href];
        };

        $add($taxonomy->title, "All {$taxonomy->title}", route('sunrice.admin.terms.index', $taxonomy));
        if ($term?->parent_id !== null && ($parent = Term::query()->with('translations')->find($term->parent_id)) !== null) {
            $translation = $parent->translations->firstWhere('locale', Locales::main());
            $name = $translation === null ? '#'.$parent->id : $translation->name;
            $add($taxonomy->title, "Parent: {$name}", route('sunrice.admin.terms.edit', $parent));
        }

        foreach ($taxonomy->collections as $collection) {
            if (Gate::allows('viewAny', [Entry::class, $collection->id])) {
                $add('Used by', $collection->title, route('sunrice.admin.entries.index', $collection));
            }
        }

        if (Gate::allows('update', $taxonomy)) {
            $add('Structure', 'Taxonomy settings', route('sunrice.admin.structure.taxonomies.edit', $taxonomy));
        }
        $blueprint = $taxonomy->blueprint;
        if ($blueprint instanceof Blueprint && Gate::allows('update', $blueprint)) {
            $add('Structure', "Blueprint: {$blueprint->title}", route('sunrice.admin.structure.blueprints.edit', $blueprint));
        }

        return $links;
    }
}
