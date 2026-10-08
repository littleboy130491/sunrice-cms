<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

use function Pest\Laravel\get;

beforeEach(fn () => actingAsSuperAdmin());

it('links an entry to its list, listing page, terms, collection and blueprint', function () {
    $collection = createCollection('articles', ['has_archive' => true, 'archive_route' => '/blog']);
    $tags = Taxonomy::factory()->create(['handle' => 'tags', 'title' => 'Tags']);
    $collection->taxonomies()->attach($tags);
    $entry = createEntry($collection, 'Hello');

    get("/cms/entries/{$entry->id}")->assertInertia(fn (Assert $page) => $page->where('related', function ($links) {
        $labels = collect($links)->pluck('label')->all();

        return in_array('All Articles', $labels, true) && in_array('Edit listing page', $labels, true)
            && in_array('View listing page', $labels, true) && in_array('Tags', $labels, true)
            && in_array('Collection settings', $labels, true) && in_array('Blueprint: Articles', $labels, true)
            && collect($links)->firstWhere('label', 'View listing page')['href'] === '/blog';
    }));
});

it('links a term to its list, collections, taxonomy and blueprint', function () {
    $collection = createCollection('articles');
    $tags = Taxonomy::factory()->create(['handle' => 'tags', 'title' => 'Tags']);
    $collection->taxonomies()->attach($tags);
    $term = Term::factory()->create(['taxonomy_id' => $tags->id]);

    get("/cms/terms/{$term->id}/edit")->assertInertia(fn (Assert $page) => $page->where('related', fn ($links) => collect($links)->pluck('label')->intersect(['All Tags', 'Articles', 'Taxonomy settings'])->count() === 3));
});
