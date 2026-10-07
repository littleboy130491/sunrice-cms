<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    RouteMatcher::flush();

    // A throwaway views folder for override templates.
    $this->views = sys_get_temp_dir().'/sunrice-views-'.uniqid();
    File::ensureDirectoryExists($this->views.'/custom');
    File::put($this->views.'/custom/entry.blade.php', 'CUSTOM ENTRY {{ $entry->title }}');
    File::put($this->views.'/custom/term.blade.php', 'CUSTOM TERM {{ $term->name }}');
    View::addLocation($this->views);

    $this->pages = createCollection('pages', ['route' => '/pages/{slug}', 'template' => 'sunrice::defaults.show']);
    $this->topics = Taxonomy::factory()->create(['handle' => 'topics', 'settings' => ['has_archive' => true, 'route' => 'topics']]);
    $this->term = Term::factory()->create(['taxonomy_id' => $this->topics->id]);
    $this->term->translations()->first()->update(['name' => 'News', 'slug' => 'news']);
});

afterEach(fn () => File::deleteDirectory($this->views));

it('lets an entry override its collection template', function () {
    $entry = createEntry($this->pages, 'About');

    put("/cms/entries/{$entry->id}", ['locale' => Locales::main(), 'title' => 'About', 'slug' => 'about', 'data' => [], 'seo' => [], 'template' => 'custom.entry'])
        ->assertSessionHasNoErrors();

    expect($entry->fresh()->template)->toBe('custom.entry');
    get('/pages/about')->assertOk()->assertSee('CUSTOM ENTRY About');
});

it('lets a term override its taxonomy template', function () {
    put("/cms/terms/{$this->term->id}", [
        'template' => 'custom.term',
        'translations' => [Locales::main() => ['title' => 'News', 'slug' => 'news']],
    ])->assertSessionHasNoErrors();

    get('/topics/news')->assertOk()->assertSee('CUSTOM TERM News');
});

it('stores and renders term SEO, with taxonomy defaults behind it', function () {
    $this->topics->update(['settings' => array_merge($this->topics->settings, ['seo' => ['description' => 'All our topics.', 'noindex' => true]])]);

    get('/topics/news')->assertOk()
        ->assertSee('<meta name="description" content="All our topics.">', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    put("/cms/terms/{$this->term->id}", [
        'translations' => [Locales::main() => ['title' => 'News', 'slug' => 'news', 'seo' => ['title' => 'Latest news', 'description' => 'Fresh stories.']]],
    ])->assertSessionHasNoErrors();

    expect($this->term->translations()->first()->seo)->toBe(['title' => 'Latest news', 'description' => 'Fresh stories.']);
    get('/topics/news')->assertOk()
        ->assertSee('<title>Latest news</title>', false)
        ->assertSee('<meta name="description" content="Fresh stories.">', false);
});

it('fills entry SEO from the collection defaults', function () {
    $this->pages->update(['settings' => array_merge($this->pages->settings, ['seo' => ['description' => 'Pages of Acme.']])]);
    createEntry($this->pages, 'About');

    get('/pages/about')->assertOk()->assertSee('<meta name="description" content="Pages of Acme.">', false);
});

it('saves listing page SEO per language and renders it', function () {
    $news = createCollection('news', ['has_archive' => true]);

    put('/cms/collections/news/listing', ['locale' => Locales::main(), 'title' => 'News', 'intro' => '', 'data' => [], 'seo' => ['title' => 'All the news', 'description' => 'Everything new.']])
        ->assertSessionHasNoErrors();

    expect($news->fresh()->archive_data[Locales::main()]['seo'])->toBe(['title' => 'All the news', 'description' => 'Everything new.']);
    get('/news')->assertOk()
        ->assertSee('<title>All the news</title>', false)
        ->assertSee('<meta name="description" content="Everything new.">', false);
});

it('validates SEO defaults on collections and taxonomies', function () {
    put("/cms/structure/collections/{$this->pages->id}", [
        'title' => 'Pages', 'handle' => 'pages', 'blueprint_id' => $this->pages->blueprint_id,
        'settings' => ['seo' => ['description' => 'Defaults', 'image' => 'not-an-id']],
    ])->assertSessionHasErrors('settings.seo.image');
});
