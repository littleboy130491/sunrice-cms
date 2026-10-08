<?php

declare(strict_types=1);

use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

it('adds demo articles in their own collection and removes them again', function () {
    $pages = createCollection('pages', ['route' => '/{slug}']);
    createEntry($pages, 'About');

    artisan('sunrice:demo-content', ['count' => 30])->assertSuccessful()->expectsOutputToContain('Added 30 demo articles');
    artisan('sunrice:demo-content', ['count' => 5])->assertSuccessful()->expectsOutputToContain('(35 in total)');

    $demo = Collection::query()->where('handle', 'demo')->firstOrFail();
    expect(Entry::query()->where('collection_id', $demo->id)->count())->toBe(35)
        ->and(Taxonomy::query()->where('handle', 'demo_topics')->firstOrFail()->terms()->count())->toBe(10);

    RouteMatcher::flush();
    get('/demo')->assertOk()->assertSee('Demo article 1');
    get('/demo/demo-article-12')->assertOk()->assertSee('Demo article 12');
    get('/demo_topics/topic-2')->assertOk();

    artisan('sunrice:demo-content', ['--remove' => true, '--force' => true])->assertSuccessful()->expectsOutputToContain('Removed 35 demo articles and 10 demo topics');
    expect(Collection::withTrashed()->where('handle', 'demo')->exists())->toBeFalse()
        ->and(Taxonomy::withTrashed()->where('handle', 'demo_topics')->exists())->toBeFalse()
        ->and(Blueprint::query()->where('handle', 'demo')->exists())->toBeFalse()
        // Real content is untouched.
        ->and(Entry::query()->where('collection_id', $pages->id)->count())->toBe(1);
});

it('refuses to touch a collection that is not demo content', function () {
    createCollection('demo');

    artisan('sunrice:demo-content', ['count' => 3])->assertFailed()->expectsOutputToContain("isn't demo content");
    artisan('sunrice:demo-content', ['--remove' => true, '--force' => true])->assertFailed();
    expect(Collection::query()->where('handle', 'demo')->exists())->toBeTrue();
});
