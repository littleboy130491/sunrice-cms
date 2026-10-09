<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sunrice\Actions\Structure\PurgeDeleted;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

/**
 * sunrice:demo-content — fills a separate demo collection with fake,
 * published articles (and a demo taxonomy) to see how a site performs
 * with lots of content; --remove deletes all of it again. It never
 * touches other collections.
 */
class DemoContentCommand extends Command
{
    protected $signature = 'sunrice:demo-content
        {count=1000 : How many articles to add}
        {--collection=demo : Handle of the demo collection (its pages live at /{handle}/…)}
        {--remove : Delete the demo collection, its taxonomy and every demo entry}
        {--force : Skip the confirmation when removing}';

    protected $description = 'Add (or remove) fake articles in a separate demo collection, to test the site with lots of content';

    protected const TOPICS = 10;

    public function handle(PurgeDeleted $purge, ActivityLogger $activity): int
    {
        // Thousands of fake articles would bury real activity in the log.
        return $activity->withoutLogging(fn () => $this->fill($purge));
    }

    protected function fill(PurgeDeleted $purge): int
    {
        $handle = Str::slug((string) $this->option('collection'), '_');
        if ($handle === '') {
            $this->components->error('Give a collection handle, e.g. --collection=demo.');

            return self::FAILURE;
        }
        $taxonomyHandle = $handle.'_topics';
        $collection = Collection::withTrashed()->where('handle', $handle)->first();

        if ($collection !== null && ! $collection->setting('demo')) {
            $this->components->error("The collection \"{$handle}\" exists and isn't demo content. Pick another handle with --collection=….");

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove($purge, $collection, $taxonomyHandle);
        }

        $count = max(1, (int) $this->argument('count'));
        [$collection, $topics] = $this->structure($collection, $handle, $taxonomyHandle);

        $start = (int) Entry::withTrashed()->where('collection_id', $collection->id)->count();
        $main = Locales::main();
        $bar = $this->output->createProgressBar($count);
        $started = microtime(true);

        foreach (array_chunk(range($start + 1, $start + $count), 200) as $chunk) {
            DB::transaction(function () use ($chunk, $collection, $topics, $main, $bar): void {
                foreach ($chunk as $i) {
                    $entry = Entry::query()->create([
                        'collection_id' => $collection->id,
                        'status' => 'published',
                        'published_at' => now()->subMinutes($i),
                    ]);
                    $entry->translations()->create([
                        'collection_id' => $collection->id,
                        'locale' => $main,
                        'title' => "Demo article {$i}",
                        'slug' => "demo-article-{$i}",
                        'data' => [
                            'excerpt' => "A short summary of demo article {$i}.",
                            'body' => '<p>'.implode('</p><p>', array_fill(0, 4, "Demo article {$i}. ".self::LOREM)).'</p>',
                            'price' => ($i % 50) * 10 + 9,
                        ],
                        'seo' => [],
                        'is_ready' => true,
                    ]);
                    $entry->terms()->attach($topics[$i % count($topics)]->id);
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->newLine(2);
        ContentChanged::dispatch('demo_content');

        $total = $start + $count;
        $this->components->info(sprintf('Added %d demo articles in %.1fs (%d in total).', $count, microtime(true) - $started, $total));
        $this->line('Pages to try (open them, or time them with curl -w "%{time_total}\n" -o /dev/null -s URL):');
        foreach ([
            "/{$handle}" => 'listing page',
            "/{$handle}?page=".max(1, intdiv($total, 12)) => 'last listing page',
            "/{$handle}/demo-article-".max(1, intdiv($total, 2)) => 'an article',
            "/{$taxonomyHandle}/topic-1" => 'a term page',
            '/search?q=demo+article+'.max(1, intdiv($total, 3)) => 'search',
            '/'.config('sunrice.admin.path', 'cms')."/collections/{$handle}/entries" => 'admin list',
        ] as $path => $label) {
            $this->line(sprintf('  %-18s %s', $label, url($path)));
        }
        $this->line("Remove it all with <comment>php artisan sunrice:demo-content --remove --collection={$handle}</comment>.");

        return self::SUCCESS;
    }

    /** @return array{0: Collection, 1: \Illuminate\Support\Collection<int, Term>} */
    protected function structure(?Collection $collection, string $handle, string $taxonomyHandle): array
    {
        $blueprint = Blueprint::query()->firstOrCreate(['handle' => $handle], [
            'title' => 'Demo article',
            'fields' => [
                ['handle' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt'],
                ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
                ['handle' => 'price', 'type' => 'number', 'label' => 'Price'],
            ],
        ]);

        if ($collection === null) {
            $collection = Collection::query()->create([
                'handle' => $handle,
                'title' => 'Demo articles',
                'blueprint_id' => $blueprint->id,
                'settings' => [
                    'demo' => true,
                    'route' => "/{$handle}/{slug}",
                    'has_archive' => true,
                    'archive_route' => $handle,
                    'per_page' => 12,
                ],
            ]);
        } elseif ($collection->trashed()) {
            $collection->restore();
        }

        $taxonomy = Taxonomy::withTrashed()->firstOrCreate(['handle' => $taxonomyHandle], [
            'title' => 'Demo topics',
            'settings' => ['demo' => true, 'has_archive' => true, 'route' => $taxonomyHandle],
        ]);
        $collection->taxonomies()->syncWithoutDetaching([$taxonomy->id]);

        $topics = Term::query()->where('taxonomy_id', $taxonomy->id)->orderBy('id')->get();
        for ($i = $topics->count() + 1; $i <= self::TOPICS; $i++) {
            $term = Term::query()->create(['taxonomy_id' => $taxonomy->id, 'sort_order' => $i]);
            $term->translations()->create(['taxonomy_id' => $taxonomy->id, 'locale' => Locales::main(), 'name' => "Topic {$i}", 'slug' => "topic-{$i}", 'data' => [], 'seo' => []]);
            $topics->push($term);
        }

        return [$collection, $topics->values()];
    }

    protected function remove(PurgeDeleted $purge, ?Collection $collection, string $taxonomyHandle): int
    {
        $taxonomy = Taxonomy::withTrashed()->where('handle', $taxonomyHandle)->first();
        if ($collection === null && ($taxonomy === null || ! $taxonomy->setting('demo'))) {
            $this->components->info('There is no demo content to remove.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Delete the demo collection, its topics and every demo article? This can\'t be undone.', false)) {
            $this->components->warn('Nothing deleted.');

            return self::FAILURE;
        }

        $entries = $collection === null ? 0 : $purge->collection($collection);
        $terms = $taxonomy !== null && $taxonomy->setting('demo') ? $purge->taxonomy($taxonomy) : 0;
        if ($collection !== null) {
            Blueprint::query()->where('handle', $collection->handle)->whereDoesntHave('collections')->delete();
        }
        $this->components->info("Removed {$entries} demo articles and {$terms} demo topics.");

        return self::SUCCESS;
    }

    protected const LOREM = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.';
}
