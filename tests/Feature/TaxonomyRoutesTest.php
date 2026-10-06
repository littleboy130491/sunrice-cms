<?php

declare(strict_types=1);

use Sunrice\Frontend\RouteMatcher;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->blog = createCollection('blog');
    $this->news = createCollection('news', [], Blueprint::create(['handle' => 'news', 'title' => 'News', 'fields' => []]));
});

/** A "category" taxonomy with one term, tagged on one blog and one news entry. */
function categoryWithEntries(array $settings): Term
{
    $taxonomy = Taxonomy::factory()->create(['handle' => 'category', 'title' => 'Category', 'settings' => $settings]);
    $taxonomy->collections()->sync([test()->blog->id, test()->news->id]);

    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Updates', 'slug' => 'updates']);

    createEntry(test()->blog, 'Blog post')->terms()->attach($term);
    createEntry(test()->news, 'News item')->terms()->attach($term);
    RouteMatcher::flush();

    return $term->fresh();
}

it('attaches collections and enables term pages from the taxonomy form', function () {
    post('/cms/structure/taxonomies', [
        'title' => 'Category', 'handle' => 'category',
        'settings' => ['has_archive' => true, 'route' => ''],
        'collection_ids' => [$this->blog->id, $this->news->id],
    ])->assertSessionHasNoErrors();

    $taxonomy = Taxonomy::query()->where('handle', 'category')->firstOrFail();
    expect($taxonomy->collections->pluck('handle')->sort()->values()->all())->toBe(['blog', 'news'])
        ->and($taxonomy->setting('has_archive'))->toBeTrue()
        ->and($taxonomy->settings)->not->toHaveKey('route');

    put("/cms/structure/taxonomies/{$taxonomy->id}", [
        'title' => 'Category', 'settings' => ['has_archive' => true, 'route' => 'topics'],
        'collection_ids' => [$this->blog->id],
    ])->assertSessionHasNoErrors();

    expect($taxonomy->fresh()->collections->pluck('handle')->all())->toBe(['blog'])
        ->and($taxonomy->fresh()->settings['route'])->toBe('/topics/{slug}');
});

it('gives each attached collection its own term page by default', function () {
    $term = categoryWithEntries(['has_archive' => true]);

    get('/blog/category/updates')->assertOk()->assertSee('Blog post')->assertDontSee('News item');
    get('/news/category/updates')->assertOk()->assertSee('News item')->assertDontSee('Blog post');
    get('/category/updates')->assertNotFound();

    $term->resolveFor('id');
    expect($term->urlIn($this->news))->toBe('/news/category/updates')
        ->and($term->url)->toBe('/blog/category/updates'); // first collection
});

it('uses one page across collections with a custom route', function () {
    categoryWithEntries(['has_archive' => true, 'route' => 'topics']);

    get('/topics/updates')->assertOk()->assertSee('Blog post')->assertSee('News item');
    get('/blog/category/updates')->assertNotFound();
});

it('falls back to /{taxonomy}/{slug} when attached to no collection', function () {
    $taxonomy = Taxonomy::factory()->create(['handle' => 'tags', 'settings' => ['has_archive' => true]]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Laravel', 'slug' => 'laravel']);
    RouteMatcher::flush();

    expect(app(UrlGenerator::class)->term($term->fresh(), 'id'))->toBe('/tags/laravel');
    get('/tags/laravel')->assertOk()->assertSee('Laravel');
});

it('lists every per-collection term page in the sitemap', function () {
    categoryWithEntries(['has_archive' => true]);

    get('/sitemap.xml')->assertOk()
        ->assertSee(url('/blog/category/updates'), false)
        ->assertSee(url('/news/category/updates'), false);
});
