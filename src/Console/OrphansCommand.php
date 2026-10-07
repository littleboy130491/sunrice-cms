<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Actions\Structure\PurgeDeleted;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

/**
 * sunrice:orphans — lists content kept from deleted collections and
 * taxonomies (hidden everywhere, restored when one is re-created with the
 * same handle) and, with --purge, deletes it permanently.
 */
class OrphansCommand extends Command
{
    protected $signature = 'sunrice:orphans
        {--purge : Permanently delete the listed collections and taxonomies with their entries and terms}
        {--handle=* : Only these collection or taxonomy handles}
        {--force : Skip the confirmation when purging}';

    protected $description = 'List (or permanently delete) entries and terms kept from deleted collections and taxonomies';

    public function handle(PurgeDeleted $purge): int
    {
        $handles = (array) $this->option('handle');
        $collections = Collection::onlyTrashed()->orderBy('handle')
            ->when($handles !== [], fn ($q) => $q->whereIn('handle', $handles))->get();
        $taxonomies = Taxonomy::onlyTrashed()->orderBy('handle')
            ->when($handles !== [], fn ($q) => $q->whereIn('handle', $handles))->get();

        if ($collections->isEmpty() && $taxonomies->isEmpty()) {
            $this->components->info('No orphaned content: no deleted collections or taxonomies are holding entries or terms.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($collections as $collection) {
            $rows[] = ['Collection', $collection->handle, $collection->title, PurgeDeleted::entriesOf($collection)->count().' entries', $collection->deleted_at?->toDateTimeString()];
        }
        foreach ($taxonomies as $taxonomy) {
            $rows[] = ['Taxonomy', $taxonomy->handle, $taxonomy->title, PurgeDeleted::termsOf($taxonomy)->count().' terms', $taxonomy->deleted_at?->toDateTimeString()];
        }
        $this->table(['Type', 'Handle', 'Title', 'Kept', 'Deleted at'], $rows);

        if (! $this->option('purge')) {
            $this->line('Hidden in the admin and on the site. Create a collection or taxonomy with the same handle to bring it back,');
            $this->line('or run <comment>php artisan sunrice:orphans --purge</comment> to delete it permanently (add --handle=… for just one).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Permanently delete everything listed above? This can\'t be undone.', false)) {
            $this->components->warn('Nothing deleted.');

            return self::FAILURE;
        }

        foreach ($collections as $collection) {
            $count = $purge->collection($collection);
            $this->components->info("Deleted collection \"{$collection->handle}\" and {$count} entries.");
        }
        foreach ($taxonomies as $taxonomy) {
            $count = $purge->taxonomy($taxonomy);
            $this->components->info("Deleted taxonomy \"{$taxonomy->handle}\" and {$count} terms.");
        }

        return self::SUCCESS;
    }
}
