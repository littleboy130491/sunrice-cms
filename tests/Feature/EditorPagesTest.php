<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\AdminUrls;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;
use Sunrice\Models\Form;
use Sunrice\Models\Menu;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;
use Workbench\App\Models\User;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
});

it('creates and edits terms on their own pages', function () {
    $taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'hierarchical' => true, 'settings' => ['has_archive' => true, 'route' => 'topics']]);
    $parent = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    get('/cms/taxonomies/topics/terms/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Taxonomies/TermEdit')
        ->where('term', null)
        ->has('parents', 1));

    post('/cms/taxonomies/topics/terms', ['parent_id' => $parent->id, 'translations' => [Locales::main() => ['title' => 'Local news']]])
        ->assertRedirect();
    $term = Term::query()->latest('id')->firstOrFail();

    get(AdminUrls::term($term))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Taxonomies/TermEdit')
        ->where('term.id', $term->id)
        ->where('term.parent_id', $parent->id)
        ->where('term.urls.'.Locales::main(), '/topics/local-news')
        // A term can't be its own parent.
        ->where('parents', fn ($parents) => collect($parents)->pluck('value')->doesntContain((string) $term->id)));

    delete("/cms/terms/{$term->id}")->assertRedirect('/cms/taxonomies/topics/terms');
});

it('creates and edits users on their own pages', function () {
    $role = Role::findOrCreate('Editor', 'web');

    get('/cms/users/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Users/Edit')->where('user', null));

    post('/cms/users', ['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'Secret-password-123', 'password_confirmation' => 'Secret-password-123', 'roles' => ['Editor']])
        ->assertRedirect('/cms/users');
    $user = User::query()->where('email', 'rina@example.com')->firstOrFail();

    get("/cms/users/{$user->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Users/Edit')
        ->where('user.email', 'rina@example.com')
        ->where('user.roles', [$role->name])
        ->has('others'));

    delete("/cms/users/{$user->id}", ['content' => 'keep'])->assertRedirect('/cms/users');
});

it('creates roles with their permissions on one page', function () {
    get('/cms/roles/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Roles/Edit')->where('role', null));

    post('/cms/roles', ['name' => 'Reviewer', 'permissions' => ['sunrice.access-admin']])->assertRedirect();

    $role = Role::findByName('Reviewer', 'web');
    expect($role->hasPermissionTo('sunrice.access-admin'))->toBeTrue();
});

it('creates menus on their own page', function () {
    get('/cms/menus/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Menus/Create'));

    post('/cms/menus', ['title' => 'Footer', 'handle' => 'footer'])->assertRedirect('/cms/menus/'.Menu::query()->where('handle', 'footer')->value('id').'/edit');
});

it('shows each asset on its own page, trashed ones too', function () {
    $asset = Asset::factory()->create(['title' => 'Logo']);

    get("/cms/assets/{$asset->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Assets/Edit')
        ->where('asset.title', 'Logo')
        ->where('usages', []));

    $asset->delete();
    get("/cms/assets/{$asset->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page->where('asset.trashed', true));

    delete("/cms/assets/{$asset->id}/force")->assertRedirect('/cms/assets');
});

it('creates and renames asset folders on their own page', function () {
    get('/cms/asset-folders/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Assets/Folder')->where('folder', null));

    post('/cms/asset-folders', ['name' => 'Logos'])->assertRedirect();
    $folder = AssetFolder::query()->where('name', 'Logos')->firstOrFail();

    get("/cms/asset-folders/{$folder->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page->where('folder.name', 'Logos'));
    put("/cms/asset-folders/{$folder->id}", ['name' => 'Brand'])->assertRedirect("/cms/assets?folder={$folder->id}");
    delete("/cms/asset-folders/{$folder->id}")->assertRedirect('/cms/assets');
});

it('nests the editor addresses and sends the older ones there', function () {
    $collection = createCollection('pages');
    $entry = createEntry($collection, 'About');
    $topics = Taxonomy::factory()->create(['handle' => 'topics']);
    $term = Term::factory()->create(['taxonomy_id' => $topics->id]);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    $item = $menu->items()->create(['type' => 'url', 'url' => '/x', 'labels' => ['id' => 'X'], 'sort_order' => 1]);
    $form = Form::factory()->create(['handle' => 'contact']);

    // Every editor ends in /edit, nested under what it belongs to.
    get("/cms/collections/pages/entries/{$entry->id}/edit")->assertOk();
    get("/cms/taxonomies/topics/terms/{$term->id}/edit")->assertOk();
    get("/cms/menus/{$menu->id}/edit")->assertOk();
    get("/cms/menus/{$menu->id}/items/{$item->id}/edit")->assertOk();
    get("/cms/forms/{$form->id}/edit")->assertOk();

    // The older addresses redirect there.
    get("/cms/entries/{$entry->id}")->assertRedirect("/cms/collections/pages/entries/{$entry->id}/edit");
    get("/cms/collections/pages/entries/{$entry->id}")->assertRedirect("/cms/collections/pages/entries/{$entry->id}/edit");
    get("/cms/terms/{$term->id}/edit")->assertRedirect("/cms/taxonomies/topics/terms/{$term->id}/edit");
    get("/cms/taxonomies/topics/terms/{$term->id}")->assertRedirect("/cms/taxonomies/topics/terms/{$term->id}/edit");
    get('/cms/taxonomies/topics?search=x')->assertRedirect('/cms/taxonomies/topics/terms?search=x');
    get("/cms/menus/{$menu->id}")->assertRedirect("/cms/menus/{$menu->id}/edit");
    get("/cms/menu-items/{$item->id}/edit")->assertRedirect("/cms/menus/{$menu->id}/items/{$item->id}/edit");
    get('/cms/forms/contact')->assertRedirect("/cms/forms/{$form->id}/edit");

    // Under the wrong collection: sent to the right one.
    createCollection('posts');
    get("/cms/collections/posts/entries/{$entry->id}/edit")->assertRedirect("/cms/collections/pages/entries/{$entry->id}/edit");
});
