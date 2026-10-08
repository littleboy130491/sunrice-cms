<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Actions\Structure\DeleteBlueprint;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Blueprint;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->listingBlueprint = Blueprint::create(['handle' => 'listing', 'title' => 'Listing', 'fields' => [
        ['handle' => 'description', 'type' => 'rich_text', 'label' => 'Description'],
        ['handle' => 'columns', 'type' => 'number', 'label' => 'Columns'],
    ]]);
    $this->news = createCollection('news', ['has_archive' => true, 'archive_blueprint_id' => $this->listingBlueprint->id]);
});

it('saves the listing blueprint from the collection form', function () {
    actingAsSuperAdmin();
    put("/cms/structure/collections/{$this->news->id}", [
        'title' => 'News', 'settings' => ['archive_blueprint_id' => $this->listingBlueprint->id],
    ])->assertSessionHasNoErrors();
    expect($this->news->fresh()->setting('archive_blueprint_id'))->toBe($this->listingBlueprint->id);

    expect(fn () => app(DeleteBlueprint::class)->handle($this->listingBlueprint))
        ->toThrow(DomainException::class, '1 archive/listing page(s)');
});

it('edits listing content per language, sharing non-translatable fields', function () {
    actingAsSuperAdmin();

    get('/cms/collections/news/listing')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Collections/Listing')
        ->has('fields', 2)
        ->where('can', ['edit' => true, 'translate' => true]));

    put('/cms/collections/news/listing', [
        'locale' => 'id', 'title' => 'Berita', 'intro' => 'Kabar terbaru',
        'data' => ['description' => '<p>Deskripsi</p>', 'columns' => 3],
    ])->assertSessionHas('success', 'Archive/listing page saved.');

    // English: its own description; the column count is shared and can't differ.
    put('/cms/collections/news/listing', [
        'locale' => 'en', 'title' => 'News',
        'data' => ['description' => '<p>Description</p>', 'columns' => 9],
    ])->assertSessionHasNoErrors();

    $news = $this->news->fresh();
    expect($news->archive('description', 'id'))->toBe('<p>Deskripsi</p>')
        ->and($news->archive('description', 'en'))->toBe('<p>Description</p>')
        ->and($news->archive('columns', 'en'))->toBe(3)
        ->and($news->archiveText('en'))->toBe(['title' => 'News', 'intro' => 'Kabar terbaru']);

    put('/cms/collections/news/listing', ['locale' => 'id', 'data' => ['columns' => 'many']])
        ->assertSessionHasErrors('data.columns');
});

it('lets translators edit only the other languages of the listing page', function () {
    app(SyncPermissions::class)->handle();
    $user = User::query()->create(['name' => 'T', 'email' => 't@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo(['sunrice.access-admin', "sunrice.entries.{$this->news->id}.view", "sunrice.entries.{$this->news->id}.translate"]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    get('/cms/collections/news/listing')->assertOk();
    put('/cms/collections/news/listing', ['locale' => 'en', 'title' => 'News'])->assertSessionHasNoErrors();
    put('/cms/collections/news/listing', ['locale' => 'id', 'title' => 'Berita'])->assertForbidden();
});

it('renders listing fields in the starter template', function () {
    $dir = sys_get_temp_dir().'/sunrice-starter-'.uniqid();
    mkdir($dir);
    symlink(realpath(__DIR__.'/../../stubs/templates'), $dir.'/sunrice');
    View::getFinder()->prependLocation($dir);

    $this->news->update(['archive_data' => [
        'id' => ['title' => 'Berita', 'data' => ['description' => '<p>Deskripsi</p>']],
        'en' => ['data' => ['description' => '<p>Description</p>']],
    ]]);
    RouteMatcher::flush();

    get('/news')->assertOk()->assertSee('<h1>Berita</h1>', false)->assertSee('<p>Deskripsi</p>', false);
    get('/en/news')->assertOk()->assertSee('<p>Description</p>', false);
});
