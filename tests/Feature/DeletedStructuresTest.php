<?php

declare(strict_types=1);

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Permissions\SyncPermissions;

use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    RouteMatcher::flush();
    $this->pages = createCollection('pages', ['route' => '/pages/{slug}']);
    $this->entry = createEntry($this->pages, 'About');
    $this->topics = Taxonomy::factory()->create(['handle' => 'topics', 'title' => 'Topics']);
    $this->term = Term::factory()->create(['taxonomy_id' => $this->topics->id]);
    $this->entry->terms()->attach($this->term->id);
});

it('hides a deleted collection\'s entries everywhere but keeps them in the database', function () {
    get('/pages/about')->assertOk();

    delete("/cms/structure/collections/{$this->pages->id}")->assertSessionHas('success');
    RouteMatcher::flush();

    expect(Entry::query()->find($this->entry->id))->toBeNull()
        ->and(EntryTranslation::query()->where('entry_id', $this->entry->id)->exists())->toBeFalse()
        ->and(Entry::query()->withoutGlobalScopes()->find($this->entry->id))->not->toBeNull();
    get('/pages/about')->assertNotFound();
    get('/cms/collections/pages/entries')->assertNotFound();
});

it('brings entries back when a collection is re-created with the same handle', function () {
    delete("/cms/structure/collections/{$this->pages->id}");

    post('/cms/structure/collections', ['handle' => 'pages', 'title' => 'Pages again', 'settings' => ['route' => '/pages/{slug}']])
        ->assertRedirect()
        ->assertSessionHas('success', 'Collection "Pages again" restored with its 1 entry.');
    RouteMatcher::flush();

    expect($this->pages->fresh()->title)->toBe('Pages again')
        ->and(Entry::query()->find($this->entry->id))->not->toBeNull();
    get('/pages/about')->assertOk();
});

it('refuses to rename a collection to a deleted collection\'s handle', function () {
    $news = createCollection('news');
    delete("/cms/structure/collections/{$this->pages->id}");

    put("/cms/structure/collections/{$news->id}", ['handle' => 'pages', 'title' => 'News'])->assertSessionHasErrors('handle');
});

it('hides a deleted taxonomy\'s terms and brings them back', function () {
    delete("/cms/structure/taxonomies/{$this->topics->id}")->assertSessionHas('success');

    expect(Term::query()->find($this->term->id))->toBeNull()
        ->and($this->entry->fresh()->terms)->toHaveCount(0);

    post('/cms/structure/taxonomies', ['handle' => 'topics', 'title' => 'Topics'])
        ->assertSessionHas('success', 'Taxonomy "Topics" restored with its 1 term.');

    expect($this->entry->fresh()->terms->pluck('id')->all())->toBe([$this->term->id]);
});

it('keeps role permissions of a deleted collection, even when the role is saved meanwhile', function () {
    app(SyncPermissions::class)->handle();
    $role = Role::findOrCreate('Writer', 'web');
    $role->givePermissionTo(['sunrice.access-admin', "sunrice.entries.{$this->pages->id}.edit"]);

    delete("/cms/structure/collections/{$this->pages->id}");
    // The editor no longer lists the deleted collection's permissions.
    put("/cms/roles/{$role->id}", ['permissions' => ['sunrice.access-admin']])->assertSessionHasNoErrors();

    post('/cms/structure/collections', ['handle' => 'pages', 'title' => 'Pages']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->hasPermissionTo("sunrice.entries.{$this->pages->id}.edit"))->toBeTrue();
});

it('lists orphaned content and purges it on request', function () {
    artisan('sunrice:orphans')->expectsOutputToContain('No orphaned content')->assertSuccessful();

    delete("/cms/structure/collections/{$this->pages->id}");
    delete("/cms/structure/taxonomies/{$this->topics->id}");

    artisan('sunrice:orphans')
        ->expectsTable(['Type', 'Handle', 'Title', 'Kept', 'Deleted at'], [
            ['Collection', 'pages', 'Pages', '1 entries', Collection::withTrashed()->find($this->pages->id)->deleted_at->toDateTimeString()],
            ['Taxonomy', 'topics', 'Topics', '1 terms', Taxonomy::withTrashed()->find($this->topics->id)->deleted_at->toDateTimeString()],
        ])
        ->assertSuccessful();
    // Listing deletes nothing.
    expect(Entry::query()->withoutGlobalScopes()->find($this->entry->id))->not->toBeNull();

    // Declining the confirmation deletes nothing either.
    artisan('sunrice:orphans --purge')->expectsConfirmation('Permanently delete everything listed above? This can\'t be undone.', 'no')->assertFailed();
    expect(Entry::query()->withoutGlobalScopes()->find($this->entry->id))->not->toBeNull();

    artisan('sunrice:orphans --purge --handle=pages --force')->assertSuccessful();
    expect(Entry::query()->withoutGlobalScopes()->withTrashed()->find($this->entry->id))->toBeNull()
        ->and(EntryTranslation::query()->withoutGlobalScopes()->where('entry_id', $this->entry->id)->exists())->toBeFalse()
        ->and(Permission::query()->where('name', "sunrice.entries.{$this->pages->id}.edit")->exists())->toBeFalse()
        // The taxonomy wasn't named: still kept.
        ->and(Term::query()->withoutGlobalScopes()->withTrashed()->find($this->term->id))->not->toBeNull();

    artisan('sunrice:orphans --purge --force')->assertSuccessful();
    expect(Term::query()->withoutGlobalScopes()->withTrashed()->find($this->term->id))->toBeNull()
        ->and(Taxonomy::withTrashed()->find($this->topics->id))->toBeNull();

    // A purged handle starts fresh.
    post('/cms/structure/collections', ['handle' => 'pages', 'title' => 'Pages'])->assertSessionHas('success', 'Collection "Pages" created.');
});
