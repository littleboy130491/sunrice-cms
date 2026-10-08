<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\AdminUrls;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

use function Pest\Laravel\get;

beforeEach(fn () => actingAsSuperAdmin());

it('gives the entries list a menu to the listing page, terms, collection and blueprint', function () {
    $collection = createCollection('articles', ['has_archive' => true, 'archive_route' => '/blog']);
    $tags = Taxonomy::factory()->create(['handle' => 'tags', 'title' => 'Tags']);
    $collection->taxonomies()->attach($tags);

    get('/cms/collections/articles/entries')->assertInertia(fn (Assert $page) => $page->where('related', function ($links) {
        $labels = collect($links)->pluck('label')->all();

        return ! in_array('All Articles', $labels, true) && in_array('Edit archive/listing page', $labels, true)
            && in_array('View archive/listing page', $labels, true) && in_array('Tags', $labels, true)
            && in_array('Collection settings', $labels, true) && in_array('Blueprint: Articles', $labels, true)
            && collect($links)->firstWhere('label', 'View archive/listing page')['href'] === '/blog';
    }));
});

it('gives the terms list a menu to the collections, taxonomy and blueprint', function () {
    $collection = createCollection('articles');
    $tags = Taxonomy::factory()->create(['handle' => 'tags', 'title' => 'Tags']);
    $collection->taxonomies()->attach($tags);

    get('/cms/taxonomies/tags/terms')->assertInertia(fn (Assert $page) => $page->where('related', fn ($links) => collect($links)->pluck('label')->intersect(['Articles', 'Taxonomy settings'])->count() === 2
        && ! collect($links)->pluck('label')->contains('All Tags')));
});

it('no longer puts the menu in the editors', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'Hello');
    $tags = Taxonomy::factory()->create(['handle' => 'tags']);
    $term = Term::factory()->create(['taxonomy_id' => $tags->id]);

    get(AdminUrls::entry($entry))->assertInertia(fn (Assert $page) => $page->missing('related'));
    get(AdminUrls::term($term))->assertInertia(fn (Assert $page) => $page->missing('related'));
});
