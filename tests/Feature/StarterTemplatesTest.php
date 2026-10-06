<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Fieldset;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Models\GlobalSet;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

use function Pest\Laravel\get;

/*
 * Renders real pages through stubs/templates — the files that
 * `vendor:publish --tag=sunrice-templates` copies to resources/views/sunrice.
 */
beforeEach(function () {
    $dir = sys_get_temp_dir().'/sunrice-starter-'.uniqid();
    mkdir($dir);
    symlink(realpath(__DIR__.'/../../stubs/templates'), $dir.'/sunrice');
    View::getFinder()->prependLocation($dir);
    RouteMatcher::flush();
});

it('publishes the starter templates under the sunrice-templates tag', function () {
    $this->artisan('vendor:publish', ['--tag' => 'sunrice-templates', '--force' => true])->assertSuccessful();

    expect(resource_path('views/sunrice/show.blade.php'))->toBeFile()
        ->and(resource_path('views/sunrice/layouts/app.blade.php'))->toBeFile();
});

it('renders a page with globals, a menu and flexible content blocks', function () {
    Fieldset::create(['handle' => 'hero', 'title' => 'Hero', 'fields' => [
        ['handle' => 'heading', 'type' => 'text', 'label' => 'Heading'],
        ['handle' => 'button', 'type' => 'link', 'label' => 'Button'],
    ]]);
    Fieldset::create(['handle' => 'text', 'title' => 'Text', 'fields' => [
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
    ]]);
    $blueprint = Blueprint::create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'Sections', 'config' => ['fieldsets' => ['hero', 'text']]],
    ]]);
    $pages = createCollection('pages', ['route' => '/pages/{slug}'], $blueprint);
    createEntry($pages, 'About', [
        'body' => '<p>Intro body</p>',
        'sections' => [
            ['id' => 'b1', 'type' => 'hero', 'values' => [
                'heading' => 'Big hello',
                'button' => ['type' => 'url', 'url' => 'https://example.com/start', 'label' => 'Get started', 'new_tab' => false],
            ]],
            ['id' => 'b2', 'type' => 'text', 'values' => ['body' => '<p>Block text</p>']],
        ],
    ]);

    $siteBlueprint = Blueprint::create(['handle' => 'site', 'title' => 'Site', 'fields' => [
        ['handle' => 'name', 'type' => 'text', 'label' => 'Name'],
    ]]);
    $site = GlobalSet::factory()->create(['handle' => 'site', 'blueprint_id' => $siteBlueprint->id, 'translatable' => false]);
    $site->values()->create(['locale' => null, 'data' => ['name' => 'Acme Studio']]);

    $menu = Menu::factory()->create(['handle' => 'main']);
    MenuItem::query()->create([
        'menu_id' => $menu->id, 'sort_order' => 0, 'type' => 'url', 'url' => '/pages/about',
        'labels' => [Locales::main() => 'About us'], 'new_tab' => false,
    ]);

    get('/pages/about')
        ->assertOk()
        ->assertSee('Acme Studio')
        ->assertSee('About us')
        ->assertSee('<p>Intro body</p>', false)
        ->assertSee('Big hello')
        ->assertSee('href="https://example.com/start"', false)
        ->assertSee('<p>Block text</p>', false);
});

it('renders the articles override, archive and term archive', function () {
    $blueprint = Blueprint::create(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt'],
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
    ]]);
    $articles = createCollection('articles', [
        'route' => '/articles/{slug}', 'has_archive' => true, 'archive_route' => '/articles',
    ], $blueprint);
    $first = createEntry($articles, 'First post', ['excerpt' => 'A short summary', 'body' => '<p>Hello</p>']);
    createEntry($articles, 'Second post');

    $taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'title' => 'Topics', 'settings' => ['has_archive' => true, 'route' => '/topics/{slug}']]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Laravel', 'slug' => 'laravel']);
    $first->terms()->attach($term);
    RouteMatcher::flush();

    get('/articles/first-post')
        ->assertOk()
        ->assertSee('First post')
        ->assertSee('A short summary')
        ->assertSee('Artikel terbaru')
        ->assertSee('Second post')
        ->assertSee('href="'.$term->fresh()->url.'"', false);

    get('/articles')->assertOk()->assertSee('First post')->assertSee('Second post');

    get('/topics/laravel')->assertOk()->assertSee('Laravel')->assertSee('First post')->assertDontSee('Second post');
});

it('renders a form block with inputs generated from the form fields', function () {
    Fieldset::create(['handle' => 'form', 'title' => 'Form', 'fields' => [
        ['handle' => 'heading', 'type' => 'text', 'label' => 'Heading'],
        ['handle' => 'form', 'type' => 'text', 'label' => 'Form handle'],
    ]]);
    $blueprint = Blueprint::create(['handle' => 'landing', 'title' => 'Landing', 'fields' => [
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'Sections', 'config' => ['fieldsets' => ['form']]],
    ]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'settings' => [], 'fields' => [
        ['handle' => 'email', 'type' => 'text', 'label' => 'Email', 'required' => true],
        ['handle' => 'topic', 'type' => 'select', 'label' => 'Topic', 'config' => ['options' => [['value' => 'sales', 'label' => 'Sales']]]],
        ['handle' => 'message', 'type' => 'textarea', 'label' => 'Message'],
    ]]);
    $pages = createCollection('pages', ['route' => '/{slug}'], $blueprint);
    createEntry($pages, 'Contact', ['sections' => [
        ['id' => 'f1', 'type' => 'form', 'values' => ['heading' => 'Get in touch', 'form' => 'contact']],
    ]]);

    get('/contact')
        ->assertOk()
        ->assertSee('Get in touch')
        ->assertSee('action="'.route('sunrice.frontend.forms.submit', 'contact').'"', false)
        ->assertSee('name="data[email]"', false)
        ->assertSee('type="email"', false)
        ->assertSee('<option value="sales"', false)
        ->assertSee('name="data[message]"', false);
});

it('serves archives and term pages next to a collection at the site root', function () {
    $pages = createCollection('pages', ['route' => '/']);
    createEntry($pages, 'About');
    $articles = createCollection('articles', ['has_archive' => true]);
    $articles->update(['archive_data' => ['title' => 'All the news', 'intro' => 'Fresh every week']]);
    $old = createEntry($articles, 'Older post');
    $old->update(['published_at' => now()->subDays(3)]);
    createEntry($articles, 'Newest post');
    $taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'title' => 'Topics', 'settings' => ['has_archive' => true, 'route' => '/topics/{slug}']]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Laravel', 'slug' => 'laravel']);
    $old->terms()->attach($term);
    RouteMatcher::flush();

    get('/about')->assertOk()->assertSee('About');
    get('/articles')->assertOk()->assertSee('All the news')->assertSee('Fresh every week')->assertSee('Older post');
    get('/topics/laravel')->assertOk()->assertSee('Older post');

    // "Latest articles" lists the newest first, without the article itself.
    get('/articles/older-post')->assertOk()->assertSeeInOrder(['Artikel terbaru', 'Newest post']);
});

it('links tags only for taxonomies with term pages', function () {
    $articles = createCollection('articles');
    $post = createEntry($articles, 'Post');
    $taxonomy = Taxonomy::factory()->create(['handle' => 'labels', 'title' => 'Labels', 'settings' => []]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['name' => 'Internal', 'slug' => 'internal']);
    $post->terms()->attach($term);
    RouteMatcher::flush();

    get('/articles/post')->assertOk()->assertSee('<span>Internal</span>', false)->assertDontSee('/articles/labels/internal');
});

it('defaults entry link text to the entry title and shows a switcher on listings', function () {
    Fieldset::create(['handle' => 'hero', 'title' => 'Hero', 'fields' => [
        ['handle' => 'heading', 'type' => 'text', 'label' => 'Heading'],
        ['handle' => 'button', 'type' => 'link', 'label' => 'Button'],
    ]]);
    $blueprint = Blueprint::create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'Sections', 'config' => ['fieldsets' => ['hero']]],
    ]]);
    $pages = createCollection('pages', ['has_archive' => true], $blueprint);
    $pricing = createEntry($pages, 'Pricing plans');
    createEntry($pages, 'Home', ['sections' => [
        ['id' => 'b1', 'type' => 'hero', 'values' => ['heading' => 'Hi', 'button' => ['type' => 'entry', 'entry_id' => $pricing->id]]],
    ]]);
    RouteMatcher::flush();

    get('/pages/home')->assertOk()
        ->assertSee('href="/pages/pricing-plans"', false)
        ->assertSee('Pricing plans');

    get('/pages?page=2')->assertOk()
        ->assertSee('hreflang="en"', false)
        ->assertSee('href="/en/pages"', false)
        ->assertSee('<link rel="canonical" href="'.url('/pages').'?page=2">', false);
});

it('returns form errors to the right form, in the page language, with labels', function () {
    Fieldset::create(['handle' => 'form', 'title' => 'Form', 'fields' => [
        ['handle' => 'form', 'type' => 'text', 'label' => 'Form handle'],
    ]]);
    $blueprint = Blueprint::create(['handle' => 'landing', 'title' => 'Landing', 'fields' => [
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'Sections', 'config' => ['fieldsets' => ['form']]],
    ]]);
    foreach (['contact', 'newsletter'] as $handle) {
        Form::query()->create(['handle' => $handle, 'title' => ucfirst($handle), 'settings' => [], 'fields' => [
            ['handle' => 'email', 'type' => 'text', 'label' => 'Your email', 'required' => true],
        ]]);
    }
    $pages = createCollection('pages', ['route' => '/{slug}'], $blueprint);
    $page = createEntry($pages, 'Contact', ['sections' => [
        ['id' => 'f1', 'type' => 'form', 'values' => ['form' => 'contact']],
        ['id' => 'f2', 'type' => 'form', 'values' => ['form' => 'newsletter']],
    ]]);
    RouteMatcher::flush();

    $this->from('/contact')
        ->post('/sunrice/forms/contact', ['_form' => 'contact', '_locale' => 'en', 'data' => ['email' => '']])
        ->assertRedirect(url('/contact').'#sunrice-form-contact');

    $html = $this->get('/contact')->assertOk()->getContent();
    // One message, under the contact form only, naming the field by its label.
    expect(substr_count($html, 'class="error"'))->toBe(1)
        ->and($html)->toContain('The Your email field is required.')
        ->and(strpos($html, 'class="error"'))->toBeLessThan(strpos($html, 'id="sunrice-form-newsletter"'));

    $this->from('/contact')
        ->post('/sunrice/forms/contact', ['_form' => 'contact', '_locale' => 'en', 'data' => ['email' => 'a@b.c']])
        ->assertRedirect(url('/contact').'#sunrice-form-contact');
    expect(FormSubmission::query()->first()->locale)->toBe('en');
    $this->get('/contact')->assertSee('sunrice-form-success', false);
});

it('describes listing pages by the page itself, not the last card of the loop', function () {
    config(['sunrice.locales.names' => ['id' => 'Bahasa Indonesia', 'en' => 'English']]);
    $articles = createCollection('articles', ['has_archive' => true]);
    $articles->update(['archive_data' => [
        'id' => ['title' => 'Semua berita', 'intro' => 'Setiap minggu'],
        'en' => ['title' => 'All the news'],
    ]]);
    $post = createEntry($articles, 'Kabar');
    $post->translations()->create([
        'collection_id' => $articles->id, 'locale' => 'en', 'title' => 'News item',
        'slug' => 'news-item', 'data' => [], 'is_ready' => true, 'content_published_at' => now(),
    ]);
    RouteMatcher::flush();

    // Main language: Indonesian text, switcher links to the listing, not to the post.
    get('/articles')->assertOk()
        ->assertSee('<h1>Semua berita</h1>', false)
        ->assertSee('Setiap minggu')
        ->assertSee('<link rel="canonical" href="'.url('/articles').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/articles').'">', false)
        ->assertSee('href="/en/articles" hreflang="en" lang="en"', false)
        ->assertSee('>English</a>', false)
        ->assertDontSee('/en/articles/news-item" hreflang', false);

    // English: its own heading, the main-language intro, English interface text.
    $empty = createCollection('notes', ['has_archive' => true]);
    RouteMatcher::flush();
    get('/en/articles')->assertOk()->assertSee('<h1>All the news</h1>', false)->assertSee('Setiap minggu');
    get('/en/notes')->assertOk()->assertSee('Nothing here yet.');
    get('/notes')->assertOk()->assertSee('Belum ada konten.');
});

it('previews a translation with that language\'s menus and interface text', function () {
    $articles = createCollection('articles');
    $post = createEntry($articles, 'Kabar');
    $post->translations()->create([
        'collection_id' => $articles->id, 'locale' => 'en', 'title' => 'News item',
        'slug' => 'news-item', 'data' => [], 'is_ready' => false,
    ]);
    $menu = Menu::factory()->create(['handle' => 'main']);
    MenuItem::query()->create([
        'menu_id' => $menu->id, 'sort_order' => 0, 'type' => 'url', 'url' => '/',
        'labels' => ['id' => 'Beranda', 'en' => 'Home'], 'new_tab' => false,
    ]);
    RouteMatcher::flush();

    $url = URL::signedRoute('sunrice.frontend.preview', ['entry' => $post->id, 'locale' => 'en']);

    get($url)->assertOk()->assertSee('News item')->assertSee('>Home</a>', false)
        ->assertSee('Latest articles')->assertDontSee('Beranda');
});
