<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Sunrice\Facades\Sunrice;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Frontend\TemplateContext;
use Sunrice\Models\Setting;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

use function Pest\Laravel\get;

beforeEach(function () {
    RouteMatcher::flush();
    $this->views = sys_get_temp_dir().'/sunrice-views-'.uniqid();
    File::ensureDirectoryExists($this->views.'/custom');
    File::put($this->views.'/custom/landing.blade.php', '<body @bodyClass(\'dark wide\')></body>');
    View::addLocation($this->views);

    $this->pages = createCollection('pages', ['route' => '/{slug}', 'template' => 'sunrice::defaults.show']);
});

afterEach(fn () => File::deleteDirectory($this->views));

/** The class attribute of the page's <body>. */
function bodyClasses(string $html): array
{
    preg_match('/<body[^>]*class="([^"]*)"/', $html, $m);

    return explode(' ', $m[1] ?? '');
}

it('describes an entry page', function () {
    $about = createEntry($this->pages, 'About');
    $team = createEntry($this->pages, 'Team');
    $team->update(['parent_id' => $about->id]);

    $classes = bodyClasses(get('/team')->assertOk()->getContent());

    expect($classes)->toContain('page-entry', 'collection-pages', "entry-{$team->id}", 'entry-team', 'has-parent', "parent-{$about->id}", 'template-defaults-show', 'lang-'.Locales::main())
        ->not->toContain('home', 'logged-in', 'is-draft');
});

it('marks the homepage, signed-in visitors and drafts', function () {
    $home = createEntry($this->pages, 'Welcome');
    Setting::set('homepage_entry_id', $home->id);
    expect(bodyClasses(get('/')->getContent()))->toContain('home', "entry-{$home->id}");

    $draft = createEntry($this->pages, 'Soon', status: 'draft');
    actingAsSuperAdmin();
    expect(bodyClasses(get('/soon')->assertOk()->getContent()))->toContain('logged-in', 'is-draft', "entry-{$draft->id}");
});

it('describes listing and term pages, with the page number', function () {
    $articles = createCollection('articles', ['route' => '/blog/{slug}', 'has_archive' => true, 'archive_route' => 'blog', 'archive_template' => 'sunrice::defaults.index']);
    createEntry($articles, 'One');
    expect(bodyClasses(get('/blog?page=2')->assertOk()->getContent()))
        ->toContain('page-archive', 'collection-articles', 'paged', 'paged-2', 'template-defaults-index');

    $topics = Taxonomy::factory()->create(['handle' => 'topics', 'settings' => ['has_archive' => true, 'route' => 'topics', 'template' => 'sunrice::defaults.term']]);
    $term = Term::factory()->create(['taxonomy_id' => $topics->id]);
    $term->translations()->first()->update(['name' => 'News', 'slug' => 'news']);
    RouteMatcher::flush();
    expect(bodyClasses(get('/topics/news')->assertOk()->getContent()))
        ->toContain('page-term', 'taxonomy-topics', "term-{$term->id}", 'term-news', 'template-defaults-term')
        ->not->toContain('paged');
});

it('takes extra classes from the template and from hooks', function () {
    $entry = createEntry($this->pages, 'Landing');
    $entry->update(['template' => 'custom.landing']);
    Sunrice::bodyClassUsing(function (array $classes, ?TemplateContext $page) {
        $classes[] = 'Section '.$page?->collection?->handle;

        return array_diff($classes, ['wide']);
    });

    $classes = bodyClasses(get('/landing')->assertOk()->getContent());

    expect($classes)->toContain('dark', 'template-custom-landing', 'section-pages')->not->toContain('wide');
});
