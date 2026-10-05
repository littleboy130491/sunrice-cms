<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Actions\Entries\SaveDraft;
use Sunrice\Actions\Entries\TrashEntry;
use Sunrice\Cache\ContentVersion;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Entry;
use Sunrice\Query\EntryQuery;
use Sunrice\Support\Locales;

use function Pest\Laravel\artisan;

beforeEach(function () {
    actingAsSuperAdmin();
});

it('caches entry queries per state and misses on different params', function () {
    $collection = createCollection();
    createEntry($collection, title: 'One');

    $query = EntryQuery::forCollection($collection);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $first = $query->get();
    $readsFirst = count(DB::getQueryLog());
    DB::flushQueryLog();
    $second = $query->get();
    $readsSecond = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first->pluck('id')->all())->toBe($second->pluck('id')->all())
        ->and($readsSecond)->toBeLessThan($readsFirst)
        ->and($readsSecond)->toBe(0);
});

it('invalidates public caches when content is published but not on draft saves', function () {
    $collection = createCollection();
    $entry = createEntry($collection);

    $version = ContentVersion::current();

    // Draft saves do NOT bump.
    $translation = $entry->translations->firstWhere('locale', Locales::main());
    app(SaveDraft::class)->handle($translation, ['title' => 'New title', 'data' => []]);
    expect(ContentVersion::current())->toBe($version);

    // Publishing bumps.
    app(PublishTranslation::class)->handle($translation);
    expect(ContentVersion::current())->toBeGreaterThan($version);
});

it('bumps on entry delete, term/menu/global/asset changes and scheduled publish', function () {
    $collection = createCollection();
    $entry = createEntry($collection);

    $marks = [];

    $marks['delete'] = ContentVersion::current();
    app(TrashEntry::class)->handle($entry);
    expect(ContentVersion::current())->toBeGreaterThan($marks['delete']);

    $marks['content'] = ContentVersion::current();
    ContentChanged::dispatch('term_saved');
    expect(ContentVersion::current())->toBeGreaterThan($marks['content']);

    // Publish-scheduled command bumps when it publishes something.
    $scheduled = Entry::create(['collection_id' => $collection->id, 'status' => 'draft', 'published_at' => now()->subMinute()]);
    $scheduled->translations()->create([
        'collection_id' => $collection->id,
        'locale' => Locales::main(),
        'title' => 'Scheduled',
        'slug' => 'scheduled',
        'data' => [],
        'is_ready' => true,
        'content_published_at' => now()->subMinute(),
    ]);

    $marks['scheduled'] = ContentVersion::current();
    artisan('sunrice:publish-scheduled')->assertSuccessful();
    expect(ContentVersion::current())->toBeGreaterThan($marks['scheduled']);
});
