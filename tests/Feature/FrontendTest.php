<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Entry;
use Sunrice\Models\Redirect;
use Sunrice\Models\Setting;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;
use Sunrice\View\Components\Entries;

use function Pest\Laravel\get;

beforeEach(function () {
    actingAsSuperAdmin();
    RouteMatcher::flush();
});

// ---------------- routing (T11.2) ----------------

it('renders a published entry in the main locale and its Ready translation', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about', 'title' => 'Tentang']);
    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => 'en',
        'title' => 'About us',
        'slug' => 'about-en',
        'data' => [],
        'is_ready' => true,
        'content_published_at' => now(),
    ]);

    get('/pages/about')->assertOk()->assertSee('Tentang');
    get('/en/pages/about-en')->assertOk()->assertSee('About us');
});

it('serves the main slug under non-main locales for fallback pages', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about']);

    // No 'en' translation — fallback resolves through main slug.
    get('/en/pages/about')->assertOk()->assertSee('About');
});

it('does not route draft translation slugs and falls back to the main entry', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'Tentang');
    $entry->mainTranslation()->update(['slug' => 'tentang']);
    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => 'en',
        'title' => 'About draft',
        'slug' => 'about-draft',
        'data' => [],
        'is_ready' => false,
    ]);

    get('/en/pages/about-draft')->assertNotFound();
    get('/en/pages/tentang')->assertOk()->assertSee('Tentang')->assertDontSee('About draft');
});

it('resolves entries on term archives for the active locale', function () {
    $collection = createCollection('posts', ['route' => '/posts/{slug}']);
    $entry = createEntry($collection, title: 'Halo Dunia');
    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => 'en',
        'title' => 'Hello World EN',
        'slug' => 'hello-world-en',
        'data' => [],
        'is_ready' => true,
        'content_published_at' => now(),
    ]);

    $taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'settings' => ['has_archive' => true, 'route' => '/topics/{slug}']]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Laravel', 'slug' => 'laravel']);
    $entry->terms()->attach($term);
    RouteMatcher::flush();

    get('/topics/laravel')->assertOk()->assertSee('Halo Dunia');
    get('/en/topics/laravel')->assertOk()->assertSee('Hello World EN')->assertDontSee('Halo Dunia');
});

it('renders archive and term archive pages', function () {
    $collection = createCollection('posts', ['route' => '/posts/{slug}', 'has_archive' => true, 'archive_route' => '/posts']);
    createEntry($collection, title: 'First Post');

    get('/posts')->assertOk()->assertSee('First Post');

    $taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'settings' => ['has_archive' => true, 'route' => '/topics/{slug}']]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Laravel', 'slug' => 'laravel']);
    RouteMatcher::flush();

    get('/topics/laravel')->assertOk()->assertSee('Laravel');
});

it('renders the configured homepage entry', function () {
    $collection = createCollection('pages', ['route' => '/{slug}']);
    $entry = createEntry($collection, title: 'Welcome');
    Setting::set('homepage_entry_id', $entry->id);

    get('/')->assertOk()->assertSee('Welcome');
    get('/en')->assertOk()->assertSee('Welcome');
});

it('404s for scheduled (future-dated) entries', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = Entry::create(['collection_id' => $collection->id, 'status' => 'published', 'published_at' => now()->addDay()]);
    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => Locales::main(),
        'title' => 'Later',
        'slug' => 'later',
        'data' => [],
        'is_ready' => true,
        'content_published_at' => now()->addDay(),
    ]);

    get('/pages/later')->assertNotFound();
});

it('301-redirects old paths recorded on slug change', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'new-about']);
    Cache::flush();
    Redirect::query()->create([
        'old_path' => '/pages/old-about',
        'locale' => Locales::main(),
        'entry_id' => $entry->id,
    ]);

    get('/pages/old-about')->assertRedirect('/pages/new-about')->assertStatus(301);
});

it('lets host-application routes win over the catch-all', function () {
    get('/host-page')->assertOk()->assertSee('host page');
});

// ---------------- template resolution (T11.3) ----------------

it('resolves templates by priority with hooks', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about']);

    // Default fallback.
    get('/pages/about')->assertOk()->assertSee('<h1>About</h1>', false);

    // Collection settings template wins.
    view()->addNamespace('testviews', __DIR__.'/../fixtures/views');
    $collection->update(['settings' => $collection->settings + ['template' => 'testviews::custom-entry']]);
    file_put_contents(__DIR__.'/../fixtures/views/custom-entry.blade.php', 'CUSTOM TEMPLATE {{ $entry->title }}');
    RouteMatcher::flush();

    get('/pages/about')->assertOk()->assertSee('CUSTOM TEMPLATE');

    // A hook overrides everything.
    Sunrice\Facades\Sunrice::resolveTemplateUsing(fn ($view, $ctx) => 'testviews::hooked');
    file_put_contents(__DIR__.'/../fixtures/views/hooked.blade.php', 'HOOKED');
    get('/pages/about')->assertOk()->assertSee('HOOKED');
});

// ---------------- entries component (T11.4) ----------------

it('renders two independently paginated entries components on one page', function () {
    // Manual order: entries in the order they were created.
    $a = createCollection('news', ['route' => '/news/{slug}', 'sort' => 'manual']);
    $b = createCollection('events', ['route' => '/events/{slug}', 'sort' => 'manual']);
    for ($i = 1; $i <= 3; $i++) {
        createEntry($a, title: "News {$i}");
        createEntry($b, title: "Event {$i}");
    }

    Route::get('/listing', fn () => view('testviews::double', []))
        ->name('listing');
    view()->addNamespace('testviews', __DIR__.'/../fixtures/views');
    file_put_contents(__DIR__.'/../fixtures/views/double.blade.php', <<<'BLADE'
        <x-sunrice::entries collection="news" :paginate="true" :per-page="1">
            @foreach($component->entries as $e)<span class="n">{{ $e->title }}</span>@endforeach
        </x-sunrice::entries>
        <x-sunrice::entries collection="events" :paginate="true" :per-page="1">
            @foreach($component->entries as $e)<span class="v">{{ $e->title }}</span>@endforeach
        </x-sunrice::entries>
        BLADE);

    get('/listing?news_page=2&events_page=3')
        ->assertOk()
        ->assertSee('News 2')
        ->assertSee('Event 3')
        ->assertDontSee('News 3')
        ->assertDontSee('Event 1');
});

// ---------------- seo component (T11.5) ----------------

it('renders canonical and hreflang tags', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about']);
    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => 'en',
        'title' => 'About',
        'slug' => 'about-en',
        'data' => [],
        'is_ready' => true,
        'content_published_at' => now(),
    ]);

    get('/pages/about')->assertOk()
        ->assertSee('hreflang="id"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('hreflang="x-default"', false);
});

it('canonicalizes fallback pages to the main URL without hreflang', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about']);

    Route::get('/seo-check', fn () => view('testviews::seo', ['entry' => tap($entry, fn ($e) => $e->resolveFor('en'))]));
    view()->addNamespace('testviews', __DIR__.'/../fixtures/views');
    file_put_contents(__DIR__.'/../fixtures/views/seo.blade.php', '<x-sunrice::seo :entry="$entry" />');

    get('/seo-check')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/pages/about').'">', false)
        ->assertDontSee('hreflang', false);
});

it('renders robots, Open Graph and Twitter tags with absolute URLs', function () {
    config(['sunrice.seo.twitter_site' => '@acme']);
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about', 'seo' => ['title' => 'About Acme', 'description' => 'Who we are']]);

    get('/pages/about')->assertOk()
        ->assertSee('<title>About Acme</title>', false)
        ->assertSee('<meta property="og:url" content="'.url('/pages/about').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:site" content="@acme">', false)
        ->assertSee('<meta name="twitter:description" content="Who we are">', false)
        ->assertDontSee('name="robots"', false);
});

it('hides noindex entries from search engines and the sitemap', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $hidden = createEntry($collection, title: 'Thank you');
    $hidden->mainTranslation()->update(['slug' => 'thank-you', 'seo' => ['noindex' => true]]);
    $visible = createEntry($collection, title: 'About');
    $visible->mainTranslation()->update(['slug' => 'about']);

    get('/pages/thank-you')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    get('/pages/about')->assertOk()->assertDontSee('name="robots"', false);

    expect(get('/sitemap.xml')->assertOk()->getContent())
        ->toContain(url('/pages/about'))
        ->not->toContain('/pages/thank-you');
});

it('hides every page when the site-wide noindex switch is on', function () {
    config(['sunrice.seo.noindex' => true]);
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about']);

    get('/pages/about')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
});

// ---------------- preview (T11.6) ----------------

it('rejects unsigned or expired preview links', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');

    get("/cms/preview/{$entry->id}/id")->assertForbidden();
});

it('renders draft content behind a signed preview link', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'Live Title');
    $entry->mainTranslation()->update(['draft' => ['title' => 'Draft Title'], 'data' => []]);

    $url = URL::temporarySignedRoute('sunrice.frontend.preview', now()->addMinutes(30), [
        'entry' => $entry->id, 'locale' => Locales::main(),
    ]);

    get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
});

// ---------------- sitemap (T11.7) ----------------

it('includes published entries per available locale and excludes fallbacks', function () {
    $collection = createCollection('pages', ['route' => '/pages/{slug}']);
    $entry = createEntry($collection, title: 'About');
    $entry->mainTranslation()->update(['slug' => 'about']);
    $entry->translations()->create([
        'collection_id' => $collection->id,
        'locale' => 'en',
        'title' => 'About',
        'slug' => 'about-en',
        'data' => [],
        'is_ready' => true,
        'content_published_at' => now(),
    ]);
    // Fallback-only entry (no en translation).
    $other = createEntry($collection, title: 'Other');
    $other->mainTranslation()->update(['slug' => 'other']);

    $response = get('/sitemap.xml')->assertOk();
    $xml = $response->getContent();

    expect($xml)->toContain('/pages/about')
        ->toContain('/en/pages/about-en')
        ->toContain('/pages/other')
        ->not->toContain('/en/pages/other');
});

it('orders <x-sunrice::entries> by "-field", "field desc" or several fields', function () {
    $news = createCollection('news');
    $a = createEntry($news, 'Alpha');
    $a->update(['published_at' => now()->subDays(2)]);
    createEntry($news, 'Bravo');

    $titles = fn (string $orderBy) => (new Entries('news', orderBy: $orderBy))->entries->pluck('title')->all();

    expect($titles('published_at desc'))->toBe(['Bravo', 'Alpha'])
        ->and($titles('-published_at'))->toBe(['Bravo', 'Alpha'])
        ->and($titles('published_at'))->toBe(['Alpha', 'Bravo'])
        ->and($titles('title desc, -published_at'))->toBe(['Bravo', 'Alpha']);
});
