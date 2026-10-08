<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Setting;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

/** Queries run by one GET request (after a warm-up request). */
function queriesFor(string $url): int
{
    test()->get($url)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('keeps the queries of public pages flat as content grows', function () {
    $articles = createCollection('articles', ['route' => '/articles/{slug}', 'has_archive' => true, 'archive_route' => 'articles', 'per_page' => 12]);
    $topics = Taxonomy::factory()->create(['handle' => 'topics', 'settings' => ['has_archive' => true, 'route' => 'topics']]);
    $articles->taxonomies()->attach($topics);
    $news = Term::factory()->create(['taxonomy_id' => $topics->id]);
    $news->translations()->first()->update(['name' => 'News', 'slug' => 'news']);
    $add = fn (int $i) => createEntry($articles, "Article $i")->terms()->attach($news->id);
    foreach (range(1, 3) as $i) {
        $add($i);
    }
    RouteMatcher::flush();
    $fewListing = queriesFor('/articles');
    $fewEntry = queriesFor('/articles/article-2');
    $fewTerm = queriesFor('/topics/news');

    foreach (range(4, 30) as $i) {
        $add($i);
    }
    RouteMatcher::flush();

    // A full page of 12 cards costs the same as 3: no query per entry.
    expect(queriesFor('/articles'))->toBe($fewListing)
        ->and(queriesFor('/topics/news'))->toBe($fewTerm)
        ->and(queriesFor('/articles/article-2'))->toBe($fewEntry)
        ->and($fewEntry)->toBeLessThanOrEqual(20)
        ->and($fewTerm)->toBeLessThanOrEqual(20);
});

it('reads a setting once per request and sees its own writes', function () {
    Setting::set('demo', 1);
    DB::enableQueryLog();
    foreach (range(1, 5) as $_) {
        expect(Setting::get('demo'))->toBe(1);
    }
    expect(collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'sunrice_settings'))->count())->toBe(1);

    Setting::set('demo', 2);
    expect(Setting::get('demo'))->toBe(2)
        ->and(Setting::get('missing', 'fallback'))->toBe('fallback');
});
