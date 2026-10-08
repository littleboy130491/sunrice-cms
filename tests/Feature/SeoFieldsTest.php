<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Actions\Structure\SaveCollection;
use Sunrice\Actions\Taxonomies\SaveTaxonomy;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Asset;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Taxonomy;
use Sunrice\Support\SeoFields;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->blueprint = Blueprint::create(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'phone', 'type' => 'text'],
        ['handle' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt'],
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
        ['handle' => 'cover', 'type' => 'asset', 'label' => 'Cover'],
    ]]);
    $this->collection = createCollection('articles', ['route' => '/articles/{slug}'], $this->blueprint);
    RouteMatcher::flush();
});

it('picks fitting fields automatically and honours a choice', function () {
    expect(SeoFields::resolve($this->blueprint, []))->toBe(['title' => null, 'description' => 'excerpt', 'image' => 'cover'])
        ->and(SeoFields::resolve($this->blueprint, ['description_field' => 'body', 'image_field' => 'none']))
        ->toBe(['title' => null, 'description' => 'body', 'image' => null])
        // A field that no longer exists falls back to the automatic pick.
        ->and(SeoFields::resolve($this->blueprint, ['description_field' => 'gone'])['description'])->toBe('excerpt');
});

it('fills empty meta tags from the fields', function () {
    $asset = Asset::factory()->create();
    $entry = createEntry($this->collection, 'Hello', ['excerpt' => "A short\nsummary of the <b>article</b>.", 'cover' => $asset->id]);
    $slug = $entry->translations()->first()->slug;

    get("/articles/{$slug}")->assertOk()
        ->assertSee('<meta name="description" content="A short summary of the article.">', false)
        ->assertSee($asset->url('large'), false);

    // The page's own description still wins.
    $entry->translations()->first()->update(['seo' => ['description' => 'Own words']]);
    get("/articles/{$slug}")->assertSee('<meta name="description" content="Own words">', false);
});

it('saves the choices from Settings → SEO', function () {
    get('/cms/settings/seo')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Seo')
        ->where('collections.0.resolved.description', 'excerpt')
        ->where('collections.0.fields.description', fn ($f) => collect($f)->pluck('handle')->all() === ['phone', 'excerpt', 'body']));

    put('/cms/settings/seo', ['items' => [['kind' => 'collection', 'id' => $this->collection->id, 'chosen' => ['title' => '', 'description' => 'body', 'image' => 'none']]]])
        ->assertSessionHas('success');

    expect($this->collection->fresh()->setting('seo'))->toMatchArray(['description_field' => 'body', 'image_field' => 'none'])
        ->and($this->collection->fresh()->setting('seo'))->not->toHaveKey('title_field');
});

it('keeps the field choices when a collection or taxonomy is saved again', function () {
    $this->collection->update(['settings' => ['seo' => ['description' => 'News', 'title_field' => 'excerpt']] + $this->collection->settings]);
    app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles', 'settings' => ['seo' => ['description' => 'News 2', 'title_field' => 'excerpt']]], $this->collection);
    expect($this->collection->fresh()->setting('seo'))->toEqual(['description' => 'News 2', 'title_field' => 'excerpt']);

    $tags = Taxonomy::factory()->create(['handle' => 'tags', 'settings' => ['seo' => ['image_field' => 'none']]]);
    app(SaveTaxonomy::class)->handle(['handle' => 'tags', 'title' => 'Tags', 'settings' => ['seo' => ['image_field' => 'none', 'noindex' => true]]], $tags);
    expect($tags->fresh()->setting('seo'))->toEqual(['image_field' => 'none', 'noindex' => true]);
});

it('keeps HTML out of the meta tags whatever the source', function () {
    $views = sys_get_temp_dir().'/sunrice-seo-'.uniqid();
    File::ensureDirectoryExists($views.'/custom');
    // A template passing a rich-text field straight to the component.
    File::put($views.'/custom/job.blade.php', '<head><x-sunrice::seo :description="$entry->get(\'body\')" /></head>');
    View::addLocation($views);

    $entry = createEntry($this->collection, 'Job', ['body' => '<ul><li><p>Minimal D3 &amp; S1</p></li><li><p>8–10 tahun</p></li></ul>']);
    $entry->update(['template' => 'custom.job']);
    $entry->translations()->first()->update(['seo' => ['title' => 'Head of <b>Service</b>']]);

    get('/articles/'.$entry->translations()->first()->slug)->assertOk()
        ->assertSee('<meta name="description" content="Minimal D3 &amp; S1 8–10 tahun">', false)
        ->assertSee('<title>Head of Service</title>', false)
        ->assertDontSee('&lt;ul&gt;', false);

    File::deleteDirectory($views);
});
