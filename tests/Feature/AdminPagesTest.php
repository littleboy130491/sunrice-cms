<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Fieldset;
use Sunrice\Models\GlobalSet;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
});

// ---------------- structure ----------------

it('lists collections in the admin', function () {
    Collection::factory()->create(['title' => 'Pages']);

    get('/cms/structure/collections')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Structure/Collections/Index')
            ->has('collections', 1)
            ->where('collections.0.title', 'Pages'));
});

it('creates, updates and deletes a collection', function () {
    $blueprint = Blueprint::factory()->create();
    $taxonomy = Taxonomy::factory()->create();

    post('/cms/structure/collections', [
        'handle' => 'pages',
        'title' => 'Pages',
        'blueprint_id' => $blueprint->id,
        'taxonomy_ids' => [$taxonomy->id],
        'settings' => ['route' => 'pages', 'dated' => true],
    ])->assertRedirect(route('sunrice.admin.structure.collections.index'));

    $collection = Collection::where('handle', 'pages')->first();
    expect($collection)->not->toBeNull()
        ->and($collection->taxonomies)->toHaveCount(1)
        ->and($collection->settings['dated'])->toBeTrue();

    put("/cms/structure/collections/{$collection->id}", [
        'title' => 'Site pages',
        'blueprint_id' => $blueprint->id,
        'settings' => ['route' => 'site'],
    ])->assertRedirect();

    expect($collection->refresh()->title)->toBe('Site pages')
        ->and($collection->settings['route'])->toBe('site');

    delete("/cms/structure/collections/{$collection->id}")->assertRedirect();
    expect(Collection::find($collection->id))->toBeNull();
});

it('creates blueprints with a nested field tree', function () {
    post('/cms/structure/blueprints', [
        'title' => 'Article',
        'handle' => 'article',
        'fields' => [
            ['handle' => 'body', 'type' => 'rich_text', 'required' => true],
            ['handle' => 'meta', 'type' => 'group', 'fields' => [
                ['handle' => 'author', 'type' => 'text'],
            ]],
        ],
    ])->assertRedirect();

    $blueprint = Blueprint::where('handle', 'article')->first();
    expect($blueprint->fields)->toHaveCount(2)
        ->and($blueprint->fields[1]['fields'][0]['handle'])->toBe('author');
});

it('creates, updates and deletes fieldsets', function () {
    post('/cms/structure/fieldsets', [
        'title' => 'SEO',
        'handle' => 'seo',
        'fields' => [['handle' => 'description', 'type' => 'textarea']],
    ])->assertRedirect();

    $fieldset = Fieldset::where('handle', 'seo')->first();
    expect($fieldset->fields[0]['handle'])->toBe('description');

    put("/cms/structure/fieldsets/{$fieldset->id}", [
        'title' => 'SEO meta',
        'fields' => [['handle' => 'og_image', 'type' => 'asset']],
    ])->assertRedirect();
    expect($fieldset->refresh()->fields[0]['handle'])->toBe('og_image');

    delete("/cms/structure/fieldsets/{$fieldset->id}")->assertRedirect();
    expect(Fieldset::find($fieldset->id))->toBeNull();
});

it('creates taxonomies and terms via admin routes', function () {
    post('/cms/structure/taxonomies', [
        'title' => 'Categories',
        'handle' => 'categories',
        'hierarchical' => true,
        'settings' => ['route' => 'category'],
    ])->assertRedirect();

    $taxonomy = Taxonomy::where('handle', 'categories')->first();
    expect($taxonomy->hierarchical)->toBeTrue();

    post("/cms/taxonomies/{$taxonomy->handle}/terms", [
        'translations' => [
            'id' => ['name' => 'Berita'],
            'en' => ['title' => 'News'],
        ],
    ])->assertRedirect();

    $term = Term::where('taxonomy_id', $taxonomy->id)->first();
    expect($term->translations)->toHaveCount(2)
        ->and($term->mainTranslation()->slug)->toBe('berita');

    put("/cms/terms/{$term->id}", [
        'translations' => ['id' => ['title' => 'Berita Baru']],
    ])->assertRedirect();
    expect($term->refresh()->mainTranslation()->name)->toBe('Berita Baru');

    delete("/cms/terms/{$term->id}")->assertRedirect();
    expect(Term::find($term->id))->toBeNull();
});

// ---------------- entries ----------------

it('creates, edits, publishes and trashes an entry through the admin', function () {
    $collection = createCollection('pages');

    post("/cms/collections/{$collection->handle}/entries", [
        'title' => 'About',
        'data' => ['body' => 'Hello'],
    ])->assertRedirect();

    $entry = Entry::first();
    expect($entry->mainTranslation()->draft['data']['body'] ?? $entry->mainTranslation()->data['body'])->toBe('Hello');

    put("/cms/entries/{$entry->id}", [
        'locale' => 'id',
        'title' => 'About v2',
        'slug' => 'about-v2',
        'data' => ['body' => 'Updated'],
    ])->assertRedirect();

    $translation = $entry->translations()->where('locale', 'id')->first();
    expect($translation->draft['data']['body'])->toBe('Updated')
        ->and($translation->draft['slug'])->toBe('about-v2');

    post("/cms/entries/{$entry->id}/publish", ['locale' => 'id'])->assertRedirect();
    expect($entry->refresh()->status)->toBe('published')
        ->and($translation->refresh()->data['body'])->toBe('Updated')
        ->and($translation->draft)->toBeNull();

    delete("/cms/entries/{$entry->id}")->assertRedirect();
    expect($entry->fresh()->trashed())->toBeTrue();

    post("/cms/entries/{$entry->id}/restore")->assertRedirect();
    expect($entry->refresh()->deleted_at)->toBeNull();

    delete("/cms/entries/{$entry->id}")->assertRedirect();
    delete("/cms/entries/{$entry->id}/force")->assertRedirect();
    expect(Entry::withTrashed()->find($entry->id))->toBeNull();
});

it('duplicates an entry', function () {
    $collection = Collection::factory()->create(['handle' => 'pages']);
    $entry = createEntry($collection, 'Home', ['x' => 1]);

    post("/cms/entries/{$entry->id}/duplicate")->assertRedirect();

    expect(Entry::count())->toBe(2);
    $copy = Entry::orderByDesc('id')->first();
    expect($copy->id)->not->toBe($entry->id)
        ->and($copy->mainTranslation()->draft['data'] ?? $copy->mainTranslation()->data)->toEqual(['x' => 1])
        ->and($copy->mainTranslation()->slug)->not->toBe('home');
});

it('saves a secondary translation and marks it ready', function () {
    $collection = Collection::factory()->create();
    $entry = createEntry($collection, 'Beranda');

    put("/cms/entries/{$entry->id}", [
        'locale' => 'en',
        'title' => 'Home',
        'slug' => 'home',
        'data' => [],
        'is_ready' => true,
    ])->assertRedirect();

    $en = $entry->translations()->where('locale', 'en')->first();
    expect($en)->not->toBeNull()
        ->and($en->is_ready)->toBeTrue()
        ->and($en->draft['title'])->toBe('Home');

    put("/cms/entry-translations/{$en->id}/return-to-draft")->assertRedirect();
    expect($en->refresh()->is_ready)->toBeFalse();
});

it('exports entries as csv honoring filters', function () {
    $collection = Collection::factory()->create(['handle' => 'pages']);
    createEntry($collection, 'Satu');
    createEntry($collection, 'Dua', [], 'draft');

    $response = get("/cms/collections/{$collection->handle}/entries/export?filters[status]=draft");
    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('Dua')
        ->and($content)->not->toContain('Satu');
});

// ---------------- menus / globals ----------------

it('builds a menu with nested items', function () {
    post('/cms/menus', ['handle' => 'main', 'title' => 'Main'])->assertRedirect();
    $menu = Menu::where('handle', 'main')->first();

    post("/cms/menus/{$menu->id}/items", [
        'type' => 'url', 'url' => '/about', 'labels' => ['id' => 'Tentang'],
    ])->assertRedirect();
    post("/cms/menus/{$menu->id}/items", [
        'type' => 'url', 'url' => '/blog', 'labels' => ['id' => 'Blog'],
    ])->assertRedirect();

    $parent = MenuItem::where('menu_id', $menu->id)->orderBy('id')->first();
    post("/cms/menus/{$menu->id}/items", [
        'parent_id' => $parent->id, 'type' => 'url', 'url' => '/child', 'labels' => ['id' => 'Child'],
    ])->assertRedirect();

    $other = MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->orderByDesc('id')->first();
    post("/cms/menus/{$menu->id}/items/reorder", [
        'items' => [
            ['id' => $other->id],
            ['id' => $parent->id],
            ['id' => $parent->children()->first()->id, 'parent_id' => $parent->id],
        ],
    ])->assertRedirect();

    expect($other->refresh()->sort_order)->toBe(1);

    $item = MenuItem::where('url', '/child')->first();
    delete("/cms/menu-items/{$item->id}")->assertRedirect();
    expect(MenuItem::find($item->id))->toBeNull();
});

it('creates a global set and saves values per locale', function () {
    $blueprint = Blueprint::factory()->create();

    post('/cms/globals', [
        'handle' => 'footer', 'title' => 'Footer', 'group' => 'global',
        'blueprint_id' => $blueprint->id, 'translatable' => true,
    ])->assertRedirect();

    $set = GlobalSet::where('handle', 'footer')->first();
    expect($set->translatable)->toBeTrue();

    put("/cms/globals/{$set->id}", ['locale' => 'id', 'values' => ['text' => 'Hak cipta']])->assertRedirect();
    put("/cms/globals/{$set->id}", ['locale' => 'en', 'values' => ['text' => 'Copyright']])->assertRedirect();

    expect($set->valuesFor('en'))->toBe(['text' => 'Copyright'])
        ->and($set->valuesFor('id'))->toBe(['text' => 'Hak cipta']);
});

// ---------------- users / roles ----------------

it('manages users and roles', function () {
    app(SyncPermissions::class)->handle();
    post('/cms/roles', ['name' => 'editor'])->assertRedirect();
    $role = Role::where('name', 'editor')->first();
    expect($role)->not->toBeNull();

    put("/cms/roles/{$role->id}", ['name' => 'editor', 'permissions' => ['sunrice.access-admin']])->assertRedirect();
    expect($role->fresh()->permissions->pluck('name')->all())->toContain('sunrice.access-admin');

    post('/cms/users', [
        'name' => 'Ed', 'email' => 'ed@x.com',
        'password' => 'secret-pw-123', 'password_confirmation' => 'secret-pw-123',
        'roles' => ['editor'],
    ])->assertRedirect();

    $user = User::where('email', 'ed@x.com')->first();
    expect($user->roles->pluck('name')->all())->toContain('editor');

    put("/cms/users/{$user->id}", ['roles' => []])->assertRedirect();
    expect($user->roles()->count())->toBe(0);

    delete("/cms/users/{$user->id}")->assertRedirect();
    expect(User::find($user->id))->toBeNull();

    // role can be deleted once unassigned
    delete("/cms/roles/{$role->id}")->assertRedirect();
    expect(Role::find($role->id))->toBeNull();
});

it('serves entry and term search endpoints for pickers', function () {
    $collection = Collection::factory()->create(['handle' => 'pages']);
    createEntry($collection, 'Kontak Kami');

    $taxonomy = Taxonomy::factory()->create(['handle' => 'cats']);
    $term = Term::factory()->for($taxonomy)->create();
    $term->translations->first()->update(['name' => 'Berita', 'slug' => 'berita']);

    get('/cms/api/entries?q=kontak')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Kontak Kami');

    get('/cms/api/terms?taxonomy=cats&q=beri')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Berita');
});

it('limits term search to taxonomies the user may use', function () {
    $collection = Collection::factory()->create(['handle' => 'posts']);
    $taxonomy = Taxonomy::factory()->create(['handle' => 'cats']);
    $collection->taxonomies()->attach($taxonomy);
    $term = Term::factory()->for($taxonomy)->create();
    $term->translations->first()->update(['name' => 'Berita', 'slug' => 'berita']);
    app(SyncPermissions::class)->handle();

    $user = User::query()->create(['name' => 'Author', 'email' => 'author@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo('sunrice.access-admin');
    test()->actingAs($user);

    get('/cms/api/terms?taxonomy=cats')->assertOk()->assertJsonCount(0, 'data');

    $user->givePermissionTo("sunrice.entries.{$collection->id}.edit-own");
    $user->unsetRelation('permissions');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    get('/cms/api/terms?taxonomy=cats')->assertOk()->assertJsonPath('data.0.title', 'Berita');
});
