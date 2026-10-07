<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Sunrice\Actions\Entries\ForceDeleteEntry;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Reference;
use Sunrice\Models\Scopes\HiddenWithParent;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Models\TermTranslation;
use Sunrice\Permissions\SyncPermissions;

/**
 * Permanently removes deleted (soft-deleted) collections and taxonomies
 * with the entries and terms they kept. Used by `sunrice:orphans --purge`.
 */
class PurgeDeleted
{
    public function __construct(protected ForceDeleteEntry $forceDeleteEntry) {}

    /**
     * Entries kept for a deleted collection, trashed ones included.
     *
     * @return Builder<Entry>
     */
    public static function entriesOf(Collection $collection): Builder
    {
        return Entry::withTrashed()->withoutGlobalScope(HiddenWithParent::class)->where('collection_id', $collection->id);
    }

    /**
     * Terms kept for a deleted taxonomy, trashed ones included.
     *
     * @return Builder<Term>
     */
    public static function termsOf(Taxonomy $taxonomy): Builder
    {
        return Term::withTrashed()->withoutGlobalScope(HiddenWithParent::class)->where('taxonomy_id', $taxonomy->id);
    }

    /** @return int entries removed */
    public function collection(Collection $collection): int
    {
        $count = 0;
        DB::transaction(function () use ($collection, &$count): void {
            static::entriesOf($collection)->lazyById(100)->each(function (Entry $entry) use (&$count): void {
                $entry->terms()->detach();
                $this->forceDeleteEntry->handle($entry);
                // Not left to the foreign key cascade (off on some SQLite setups).
                EntryTranslation::query()->withoutGlobalScope(HiddenWithParent::class)->where('entry_id', $entry->id)->delete();
                $count++;
            });
            $collection->forceDelete();
        });

        $this->synced('collection_purged');

        return $count;
    }

    /** @return int terms removed */
    public function taxonomy(Taxonomy $taxonomy): int
    {
        $count = 0;
        DB::transaction(function () use ($taxonomy, &$count): void {
            static::termsOf($taxonomy)->lazyById(100)->each(function (Term $term) use (&$count): void {
                $translations = TermTranslation::query()->withoutGlobalScope(HiddenWithParent::class)->where('term_id', $term->id);
                Reference::query()->where('source_type', 'term')->whereIn('source_id', (clone $translations)->pluck('id'))->delete();
                $translations->delete();
                $term->entries()->detach();
                $term->forceDelete();
                $count++;
            });
            $taxonomy->forceDelete();
        });

        $this->synced('taxonomy_purged');

        return $count;
    }

    protected function synced(string $reason): void
    {
        // Their permissions were kept while they could be restored.
        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch($reason);
    }
}
