<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'hierarchical' => true]);
});

function topic(string $name, ?Term $parent = null, int $order = 0): Term
{
    $term = Term::factory()->create(['taxonomy_id' => test()->taxonomy->id, 'parent_id' => $parent?->id, 'sort_order' => $order]);
    $term->translations()->first()->update(['name' => $name, 'slug' => str($name)->slug()]);

    return $term;
}

it('lists terms in tree order with their depth', function () {
    $news = topic('News', order: 0);
    topic('Guides', order: 1);
    topic('Local', $news);

    get('/cms/taxonomies/topics')->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.0.title', 'News')
        ->where('rows.data.1.title', 'Local')
        ->where('rows.data.1.depth', 1)
        ->where('rows.data.1.parent', 'News')
        ->where('rows.data.2.title', 'Guides')
        ->where('reorderable', true));
});

it('searches, filters by parent and sorts', function () {
    $news = topic('News');
    topic('Guides', order: 1);
    topic('Local', $news);

    get('/cms/taxonomies/topics?search=gui')->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)->where('rows.data.0.title', 'Guides')->where('reorderable', false));
    get("/cms/taxonomies/topics?filters[parent]={$news->id}")->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)->where('rows.data.0.title', 'Local'));
    get('/cms/taxonomies/topics?filters[parent]=root')->assertInertia(fn (Assert $page) => $page->has('rows.data', 2));
    get('/cms/taxonomies/topics?sort=-title')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.title', 'News'));
});

it('keeps the full order when one page is reordered', function () {
    $a = topic('A', order: 0);
    $b = topic('B', order: 1);
    $c = topic('C', order: 2);
    $d = topic('D', order: 3);

    // Page 2 of 2 per page holds C and D: swapping them leaves A and B first.
    post('/cms/taxonomies/topics/terms/reorder', ['items' => [$d->id, $c->id]])->assertRedirect();

    expect(Term::query()->orderBy('sort_order')->pluck('id')->all())->toBe([$a->id, $b->id, $d->id, $c->id]);
});

it('deletes selected terms in bulk', function () {
    $news = topic('News');
    topic('Local', $news);
    $guides = topic('Guides');

    post('/cms/taxonomies/topics/terms/bulk', ['action' => 'delete', 'ids' => [$news->id]])->assertRedirect();

    expect(Term::query()->pluck('id')->all())->toBe([$guides->id]);
});
