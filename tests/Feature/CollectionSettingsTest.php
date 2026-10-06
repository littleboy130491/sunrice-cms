<?php

declare(strict_types=1);

use Sunrice\Admin\Navigation;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->admin = actingAsSuperAdmin();
    $this->blueprint = Blueprint::factory()->create();
});

function saveCollection(string $handle, array $settings = []): Illuminate\Testing\TestResponse
{
    return post('/cms/structure/collections', [
        'handle' => $handle, 'title' => ucfirst($handle),
        'blueprint_id' => test()->blueprint->id, 'settings' => $settings,
    ]);
}

it('turns a route prefix into a URL pattern', function (string $typed, ?string $stored, string $route) {
    saveCollection('blog', ['route' => $typed])->assertSessionHasNoErrors();

    $collection = Collection::query()->where('handle', 'blog')->firstOrFail();
    expect($collection->settings['route'] ?? null)->toBe($stored)
        ->and($collection->entryRoute())->toBe($route);
})->with([
    'empty follows the handle' => ['', null, '/blog/{slug}'],
    'the handle itself' => ['blog', null, '/blog/{slug}'],
    'a prefix' => ['news', '/news/{slug}', '/news/{slug}'],
    'slashes' => ['/articles/', '/articles/{slug}', '/articles/{slug}'],
    'nested prefix' => ['en/news', '/en/news/{slug}', '/en/news/{slug}'],
    'full pattern' => ['/posts/{slug}', '/posts/{slug}', '/posts/{slug}'],
    'site root' => ['/', '/{slug}', '/{slug}'],
]);

it('rejects a route another collection already uses', function () {
    saveCollection('blog')->assertSessionHasNoErrors();

    // Typed differently, same URL pattern as blog's handle default.
    saveCollection('news', ['route' => '/blog/'])->assertSessionHasErrors('settings.route');
    expect(Collection::query()->where('handle', 'news')->exists())->toBeFalse();

    saveCollection('pages', ['route' => '/'])->assertSessionHasNoErrors();
    saveCollection('landing', ['route' => '/{slug}'])->assertSessionHasErrors('settings.route');
});

it('rejects a handle whose default route is taken', function () {
    saveCollection('pages', ['route' => 'news'])->assertSessionHasNoErrors();

    saveCollection('news')->assertSessionHasErrors('settings.route');
});

it('generates the same URL the router serves when no route is set', function () {
    saveCollection('blog')->assertSessionHasNoErrors();
    $collection = Collection::query()->where('handle', 'blog')->firstOrFail();
    $entry = createEntry($collection, 'Hello world');
    RouteMatcher::flush();

    $url = app(UrlGenerator::class)->entry($entry, 'id');

    expect($url)->toBe('/blog/hello-world');
    get($url)->assertOk()->assertSee('Hello world');
});

it('saves the sidebar icon', function () {
    saveCollection('blog', ['icon' => 'newspaper'])->assertSessionHasNoErrors();
    saveCollection('shop', ['icon' => 'Not An Icon'])->assertSessionHasErrors('settings.icon');

    $items = collect(app(Navigation::class)->for($this->admin))->firstWhere('label', 'Content')['items'];
    expect(collect($items)->firstWhere('label', 'Blog')['icon'])->toBe('newspaper');
});
