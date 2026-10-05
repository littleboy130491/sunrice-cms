<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Actions\Entries\DuplicateEntry;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Actions\Entries\RestoreRevision;
use Sunrice\Actions\Entries\ReturnTranslationToDraft;
use Sunrice\Actions\Entries\SaveDraft;
use Sunrice\Actions\Entries\TrashEntry;
use Sunrice\Actions\Entries\UnpublishEntry;
use Sunrice\Actions\Structure\SaveCollection;
use Sunrice\Events\EntryPublished;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Revision;
use Sunrice\Permissions\PermissionRegistry;
use Sunrice\Permissions\SyncPermissions;
use Sunrice\Support\Locales;

it('creates a draft entry with a populated draft column', function () {
    $collection = createCollection('articles');

    $entry = app(\Sunrice\Actions\Entries\CreateEntry::class)->handle($collection, [
        'title' => 'Hello World',
        'data' => ['body' => '<p>Hi</p>'],
    ]);

    expect($entry->status)->toBe('draft');
    $t = $entry->translations->first();
    expect($t->locale)->toBe(Locales::main())
        ->and($t->slug)->toBe('hello-world')
        ->and($t->draft['data']['body'])->toBe('<p>Hi</p>');
});

it('keeps the live version untouched when saving a draft on a published entry', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'Live', ['body' => 'v1']);

    $t = $entry->translations->first();
    app(SaveDraft::class)->handle($t, ['title' => 'Live', 'slug' => 'live', 'data' => ['body' => 'v2']]);

    expect($t->fresh()->data['body'])->toBe('v1')
        ->and($t->fresh()->draft['data']['body'])->toBe('v2');
});

it('publishes: copies draft to live, clears draft, writes revision', function () {
    Event::fake([EntryPublished::class]);
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'Hello', [], 'draft');

    $t = $entry->translations->first();
    app(SaveDraft::class)->handle($t, ['title' => 'New Title', 'slug' => 'new-title', 'data' => ['body' => 'v2']]);
    app(PublishTranslation::class)->handle($t->fresh());

    $t = $t->fresh();
    expect($t->title)->toBe('New Title')
        ->and($t->draft)->toBeNull()
        ->and($t->content_published_at)->not->toBeNull()
        ->and($t->is_ready)->toBeTrue()
        ->and($entry->fresh()->status)->toBe('published')
        ->and($t->revisions)->toHaveCount(1)
        ->and($t->revisions->first()->content['data']['body'])->toBe('v2');

    Event::assertDispatched(EntryPublished::class);
});

it('restores a revision into the draft column only', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection);
    $t = $entry->translations->first();

    app(SaveDraft::class)->handle($t, ['title' => 'T', 'data' => ['body' => 'v1']]);
    app(PublishTranslation::class)->handle($t->fresh());
    app(SaveDraft::class)->handle($t->fresh(), ['title' => 'T', 'data' => ['body' => 'v2']]);
    app(PublishTranslation::class)->handle($t->fresh());

    $first = Revision::query()->oldest('id')->first();
    app(RestoreRevision::class)->handle($first);

    $t = $t->fresh();
    expect($t->draft['data']['body'])->toBe('v1')
        ->and($t->data['body'])->toBe('v2');
});

it('marks non-main translations ready and falls back after draft', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'ID title');

    $en = EntryTranslation::create([
        'entry_id' => $entry->id,
        'collection_id' => $collection->id,
        'locale' => 'en',
        'title' => 'EN title',
        'slug' => 'en-title',
        'data' => [],
        'seo' => [],
        'is_ready' => false,
    ]);
    $en->draft = ['title' => 'EN title', 'slug' => 'en-title', 'data' => [], 'seo' => []];
    $en->save();

    app(PublishTranslation::class)->handle($en->fresh());
    expect($en->fresh()->is_ready)->toBeTrue();

    $entry = $entry->fresh();
    $resolved = $entry->resolveFor('en');
    expect($resolved->locale)->toBe('en')->and($entry->isFallback)->toBeFalse();

    app(ReturnTranslationToDraft::class)->handle($en->fresh());
    $entry = $entry->fresh();
    $resolved = $entry->resolveFor('en');
    expect($resolved->locale)->toBe(Locales::main())->and($entry->isFallback)->toBeTrue();
});

it('refuses to return the main translation to draft', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection);
    app(ReturnTranslationToDraft::class)->handle($entry->translations->first());
})->throws(DomainException::class);

it('unpublishes an entry', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection);
    app(UnpublishEntry::class)->handle($entry);
    expect($entry->fresh()->status)->toBe('draft');
});

it('duplicates an entry as a draft with unique slugs', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'Original');

    $copy = app(DuplicateEntry::class)->handle($entry);
    expect($copy->status)->toBe('draft')
        ->and($copy->translations->first()->slug)->toBe('original-copy');
});

it('trashed entries keep occupying their slug', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'Gone');
    app(TrashEntry::class)->handle($entry);

    expect(\Sunrice\Support\SlugValidator::isUniqueForEntry('gone', $collection->id, Locales::main()))->toBeFalse();
});

it('schedules publication via the command', function () {
    $collection = createCollection('articles');
    $entry = createEntry($collection, 'Soon', [], 'draft');
    $entry->published_at = now()->addMinute();
    $entry->save();

    $this->artisan('sunrice:publish-scheduled')->assertSuccessful();
    expect($entry->fresh()->status)->toBe('draft');

    $this->travel(2)->minutes();
    $this->artisan('sunrice:publish-scheduled')->assertSuccessful();
    expect($entry->fresh()->status)->toBe('published');
});

it('syncs permissions for collections', function () {
    $collection = app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles']);

    $expected = collect(PermissionRegistry::ENTRY_ACTIONS)
        ->map(fn ($a) => "sunrice.entries.{$collection->id}.{$a}");
    expect(Permission::query()->whereIn('name', $expected)->count())->toBe(7);

    app(\Sunrice\Actions\Structure\DeleteCollection::class)->handle($collection);
    expect(Permission::query()->whereIn('name', $expected)->count())->toBe(0);
});

it('lets super admin pass any gate check', function () {
    $user = actingAsSuperAdmin();
    expect($user->can('sunrice.access-admin'))->toBeTrue()
        ->and($user->can('sunrice.entries.999.delete'))->toBeTrue();
});

it('enforces edit-own vs edit for entry policies', function () {
    $collection = app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles']);
    $entry = createEntry($collection);

    $author = \Workbench\App\Models\User::create(['name' => 'A', 'email' => 'a@x.com', 'password' => 'x']);
    $entry->author_id = $author->id;
    $entry->save();

    $role = Role::create(['name' => 'author-role', 'guard_name' => 'web']);
    $role->givePermissionTo("sunrice.entries.{$collection->id}.edit-own");
    $author->assignRole($role);

    expect($author->can('update', $entry))->toBeTrue();

    $other = \Workbench\App\Models\User::create(['name' => 'B', 'email' => 'b@x.com', 'password' => 'x']);
    $other->assignRole($role);
    expect($other->can('update', $entry))->toBeFalse();

    $editorRole = Role::create(['name' => 'editor-role', 'guard_name' => 'web']);
    $editorRole->givePermissionTo("sunrice.entries.{$collection->id}.edit");
    $other->assignRole($editorRole);
    expect($other->can('update', $entry))->toBeTrue();
});

it('queries published entries with field filters, terms and ordering', function () {
    $blueprint = \Sunrice\Models\Blueprint::create([
        'handle' => 'article',
        'title' => 'Article',
        'fields' => [['handle' => 'price', 'type' => 'number', 'config' => []]],
    ]);
    $collection = createCollection('articles', [], $blueprint);
    createEntry($collection, 'Cheap', ['price' => 5]);
    createEntry($collection, 'Dear', ['price' => 50]);
    createEntry($collection, 'Hidden', ['price' => 1], 'draft');

    $results = \Sunrice\Query\EntryQuery::collection('articles')
        ->where('price', '>', 10)
        ->orderBy('price', 'desc')
        ->get();

    expect($results)->toHaveCount(1)->and($results->first()->title)->toBe('Dear');
});

it('paginates entry queries', function () {
    $collection = createCollection('articles');
    foreach (range(1, 15) as $i) {
        createEntry($collection, "E{$i}");
    }

    $page = \Sunrice\Query\EntryQuery::collection('articles')->paginate(10);
    expect($page->total())->toBe(15)->and($page->count())->toBe(10);
});
