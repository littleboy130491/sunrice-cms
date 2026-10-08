<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Sunrice\Events\ContentChanged;
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

it('describes the current page from a bare <x-sunrice::seo /> in any layout', function () {
    File::put($this->views.'/custom/bare.blade.php', '<head><x-sunrice::seo /></head>');
    $this->pages->update(['settings' => array_merge($this->pages->settings, ['template' => 'custom.bare'])]);
    $entry = createEntry($this->pages, 'About');
    $entry->mainTranslation()->update(['seo' => ['title' => 'About Acme', 'description' => 'Who we are.']]);
    $this->topics->update(['settings' => array_merge($this->topics->settings, ['template' => 'custom.bare'])]);
    $this->term->translations()->first()->update(['seo' => ['description' => 'News stories.']]);
    $news = createCollection('news', ['has_archive' => true, 'archive_template' => 'custom.bare']);
    $news->update(['archive_data' => [Locales::main() => ['title' => 'News', 'seo' => ['title' => 'All the news']]]]);

    get('/pages/about')->assertOk()
        ->assertSee('<title>About Acme</title>', false)
        ->assertSee('<meta name="description" content="Who we are.">', false)
        ->assertSee('<link rel="canonical" href="'.url('/pages/about').'">', false);
    get('/topics/news')->assertOk()
        ->assertSee('<title>News</title>', false)
        ->assertSee('<meta name="description" content="News stories.">', false);
    get('/news')->assertOk()->assertSee('<title>All the news</title>', false);
});

it('keeps error pages out of search results', function () {
    request()->attributes->set('sunrice.error_status', 404);
    $html = Blade::render('<x-sunrice::seo />');

    expect($html)->toContain('<meta name="robots" content="noindex, follow">')
        ->not->toContain('rel="canonical"')->not->toContain('hreflang')->not->toContain('og:url');
});

it('points a term page without a translation at the main-language page', function () {
    get('/en/topics/news')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/topics/news').'">', false)
        ->assertDontSee('rel="alternate" hreflang', false);
});

it('drops language links when the canonical points elsewhere', function () {
    $entry = createEntry($this->pages, 'About');
    $entry->translations()->first()->update(['seo' => ['canonical' => '/somewhere-else']]);

    get('/pages/about')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/somewhere-else').'">', false)
        ->assertDontSee('rel="alternate" hreflang', false);
});

it('marks entries as articles with their dates', function () {
    $entry = createEntry($this->pages, 'About');

    get('/pages/about')->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="article:published_time" content="'.$entry->published_at->toIso8601String().'">', false);
});

it('keeps noindex collections, taxonomies and terms out of the sitemap', function () {
    createEntry($this->pages, 'About');
    $news = createCollection('news', ['route' => '/news/{slug}', 'seo' => ['noindex' => true]]);
    createEntry($news, 'Hidden story');
    $this->term->translations()->first()->update(['seo' => ['noindex' => true]]);
    $other = Term::factory()->create(['taxonomy_id' => $this->topics->id]);
    $other->translations()->first()->update(['name' => 'Guides', 'slug' => 'guides']);

    expect(get('/sitemap.xml')->assertOk()->getContent())
        ->toContain('/pages/about')->toContain('/topics/guides')
        ->not->toContain('hidden-story')->not->toContain('/topics/news');

    $this->topics->update(['settings' => array_merge($this->topics->settings, ['seo' => ['noindex' => true]])]);
    ContentChanged::dispatch('taxonomy_saved');
    expect(get('/sitemap.xml')->getContent())->not->toContain('/topics/');
});
