<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Entry;
use Sunrice\Models\Term;

/**
 * Tags an entry with terms of the taxonomies attached to its collection
 * (the entry editor's Taxonomies card). Terms of other taxonomies are
 * left alone; a taxonomy the collection limits to one term
 * (settings.single_term_taxonomies) accepts at most one.
 */
class SyncEntryTerms
{
    /**
     * @param  array<int, int|string>  $termIds
     */
    public function handle(Entry $entry, array $termIds): void
    {
        $collection = $entry->collection;
        $taxonomyIds = $collection->taxonomies()->pluck('sunrice_taxonomies.id')->all();
        $single = array_map('intval', (array) $collection->setting('single_term_taxonomies', []));

        $terms = Term::query()
            ->whereIn('id', array_map('intval', $termIds))
            ->whereIn('taxonomy_id', $taxonomyIds)
            ->with('taxonomy')
            ->get();

        foreach ($terms->groupBy('taxonomy_id') as $taxonomyId => $picked) {
            if (in_array((int) $taxonomyId, $single, true) && $picked->count() > 1) {
                throw ValidationException::withMessages([
                    'term_ids' => "Pick one {$picked->first()->taxonomy->title} term: this collection allows only one.",
                ]);
            }
        }

        // Replace the terms of the attached taxonomies only.
        $current = $entry->terms()->whereIn('taxonomy_id', $taxonomyIds)->pluck('sunrice_terms.id')->all();
        $wanted = $terms->pluck('id')->all();
        $detach = array_diff($current, $wanted);
        $attach = array_diff($wanted, $current);
        if ($detach === [] && $attach === []) {
            return;
        }

        $entry->terms()->detach($detach);
        $entry->terms()->attach($attach);
        ContentChanged::dispatch('entry_terms_changed');
    }
}
