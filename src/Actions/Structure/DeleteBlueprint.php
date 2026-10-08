<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use DomainException;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;

/**
 * Deleting a blueprint is blocked while entries or collections use
 * it — the error names the dependents so the UI can explain.
 */
class DeleteBlueprint
{
    public function handle(Blueprint $blueprint): void
    {
        $collections = $blueprint->collections()->count();
        $entries = $blueprint->entries()->count();
        $taxonomies = $blueprint->taxonomies()->count();
        $globals = $blueprint->globalSets()->count();
        // Collections using it for their listing page.
        $listings = Collection::query()->get()
            ->filter(fn (Collection $c) => (int) $c->setting('archive_blueprint_id') === (int) $blueprint->id)
            ->count();

        if ($collections + $entries + $taxonomies + $globals + $listings > 0) {
            throw new DomainException(
                "Blueprint \"{$blueprint->handle}\" is still in use: "
                ."{$collections} collection(s), {$entries} entr(y/ies), {$taxonomies} taxonom(y/ies), {$globals} global(s), {$listings} archive/listing page(s)."
            );
        }

        $blueprint->delete();
    }
}
