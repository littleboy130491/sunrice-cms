<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Fieldset;
use Sunrice\Models\Form;
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
        ->assertSee('Latest articles')
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
