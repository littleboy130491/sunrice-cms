<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

it('keeps the queries of admin lists and editors flat as content grows', function () {
    actingAsSuperAdmin();
    $articles = createCollection('articles');
    $topics = Taxonomy::factory()->create(['handle' => 'topics']);
    $articles->taxonomies()->attach($topics);
    $terms = Term::factory()->count(3)->create(['taxonomy_id' => $topics->id]);
    $add = fn (int $i) => createEntry($articles, "Article $i")->terms()->attach($terms[$i % 3]->id);
    foreach (range(1, 3) as $i) {
        $add($i);
    }
    $edit = '/cms/collections/articles/entries/'.$articles->entries()->value('id').'/edit';
    $fewList = queriesFor('/cms/collections/articles/entries');
    $fewTerms = queriesFor('/cms/taxonomies/topics/terms');
    $fewEdit = queriesFor($edit);

    foreach (range(4, 30) as $i) {
        $add($i);
    }
    Term::factory()->count(20)->create(['taxonomy_id' => $topics->id]);

    expect(queriesFor('/cms/collections/articles/entries'))->toBe($fewList)
        ->and(queriesFor('/cms/taxonomies/topics/terms'))->toBe($fewTerms)
        ->and(queriesFor($edit))->toBe($fewEdit);
});

it('counts the entries of many terms in one query', function () {
    $articles = createCollection('articles');
    $other = createCollection('news');
    $topics = Taxonomy::factory()->create(['handle' => 'topics']);
    [$a, $b, $empty] = Term::factory()->count(3)->create(['taxonomy_id' => $topics->id])->all();
    createEntry($articles, 'One')->terms()->attach([$a->id, $b->id]);
    createEntry($articles, 'Two')->terms()->attach($a->id);
    createEntry($articles, 'Draft', [], 'draft')->terms()->attach($a->id);
    createEntry($other, 'Elsewhere')->terms()->attach($a->id);
    $gone = createEntry($articles, 'Gone');
    $gone->terms()->attach($b->id);
    $gone->delete();

    $terms = Term::query()->whereIn('id', [$a->id, $b->id, $empty->id])->get();
    DB::enableQueryLog();
    Term::loadEntryCounts($terms);
    expect(DB::getQueryLog())->toHaveCount(1);
    DB::disableQueryLog();
    expect($terms->pluck('entries_count', 'id')->all())->toBe([$a->id => 4, $b->id => 1, $empty->id => 0]);

    Term::loadEntryCounts($terms, fn ($q) => $q->where('sunrice_entries.status', 'published')->where('sunrice_entries.collection_id', $articles->id));
    expect($terms->pluck('entries_count', 'id')->all())->toBe([$a->id => 2, $b->id => 1, $empty->id => 0]);
});

it('indexes the columns used to look up content by term, form and folder', function () {
    $indexed = fn (string $table, string $column) => collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['columns'][0] === $column);

    expect($indexed('sunrice_entry_term', 'term_id'))->toBeTrue()
        ->and($indexed('sunrice_terms', 'taxonomy_id'))->toBeTrue()
        ->and($indexed('sunrice_revisions', 'entry_translation_id'))->toBeTrue()
        ->and($indexed('sunrice_form_submissions', 'form_id'))->toBeTrue()
        ->and($indexed('sunrice_assets', 'folder_id'))->toBeTrue();
});
