<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Frontend\MenuBuilder;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    $this->blog = createCollection('blog', ['has_archive' => true, 'archive_route' => '/blog']);
    $this->taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'title' => 'Topics', 'settings' => ['has_archive' => true, 'route' => 'topics']]);
    $this->term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $this->term->translations()->first()->update(['name' => 'Laravel', 'slug' => 'laravel']);
});

function addItem(array $data): TestResponse
{
    return post('/cms/menus/'.test()->menu->id.'/items', $data);
}

it('links collection archives and term pages, using their titles as labels', function () {
    addItem(['type' => 'collection', 'target_id' => $this->blog->id, 'labels' => ['id' => '', 'en' => '']])->assertSessionHasNoErrors();
    addItem(['type' => 'term', 'target_id' => $this->term->id, 'labels' => ['en' => 'Laravel news']])->assertSessionHasNoErrors();

    $collectionItem = MenuItem::query()->where('type', 'collection')->firstOrFail();
    expect($collectionItem->target_id)->toBe($this->blog->id)
        ->and($collectionItem->labels)->toBe([]);

    $nodes = app(MenuBuilder::class)->build('main', 'id');
    expect($nodes->pluck('label')->all())->toBe(['Blog', 'Laravel'])
        ->and($nodes->pluck('url')->all())->toBe(['/blog', '/topics/laravel']);

    expect(app(MenuBuilder::class)->build('main', 'en')->pluck('label')->all())->toBe(['Blog', 'Laravel news']);
});

it('validates the link target for its type', function () {
    addItem(['type' => 'collection', 'labels' => []])->assertSessionHasErrors('target_id');
    addItem(['type' => 'term', 'target_id' => 999999, 'labels' => []])->assertSessionHasErrors('target_id');
    addItem(['type' => 'url', 'labels' => ['id' => 'X']])->assertSessionHasErrors('url');

    expect(MenuItem::query()->count())->toBe(0);
});

it('keeps only the field that matches the type and can switch type', function () {
    addItem(['type' => 'url', 'url' => '/about', 'target_id' => $this->blog->id, 'labels' => ['id' => 'Tentang']])->assertSessionHasNoErrors();
    $item = MenuItem::query()->firstOrFail();
    expect($item->target_id)->toBeNull();

    put("/cms/menu-items/{$item->id}", ['type' => 'term', 'labels' => ['id' => 'Topik']])->assertSessionHasErrors('target_id');
    put("/cms/menu-items/{$item->id}", ['type' => 'term', 'target_id' => $this->term->id, 'url' => '/ignored', 'labels' => ['id' => 'Topik']])
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->type)->toBe('term')
        ->and($item->target_id)->toBe($this->term->id)
        ->and($item->url)->toBeNull();

    // Changing only the label keeps the target.
    put("/cms/menu-items/{$item->id}", ['labels' => ['id' => 'Topik baru']])->assertSessionHasNoErrors();
    expect($item->refresh()->target_id)->toBe($this->term->id)->and($item->labels['id'])->toBe('Topik baru');
});

it('shows what each item links to in the editor', function () {
    addItem(['type' => 'term', 'target_id' => $this->term->id, 'labels' => []]);

    get("/cms/menus/{$this->menu->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Menus/Edit')
            ->where('items.0.target_title', 'Topics: Laravel'));
});

it('edits items on their own page', function () {
    addItem(['type' => 'term', 'target_id' => $this->term->id, 'labels' => []]);
    $item = MenuItem::query()->latest('id')->firstOrFail();

    get("/cms/menus/{$this->menu->id}/items/create?parent={$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Menus/ItemEdit')
            ->where('item', null)
            ->where('parent.id', $item->id)
            ->has('collections', 1)
            ->has('taxonomies', 1));

    get("/cms/menus/{$this->menu->id}/items/{$item->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Menus/ItemEdit')
            ->where('item.target_title', 'Topics: Laravel')
            ->where('item.target_taxonomy', 'topics'));

    put("/cms/menu-items/{$item->id}", ['labels' => ['id' => 'Topik']])->assertRedirect("/cms/menus/{$this->menu->id}/edit");
});

it('lets menu editors search terms without term permissions', function () {
    app(SyncPermissions::class)->handle();
    $user = User::query()->create(['name' => 'M', 'email' => 'm@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo(['sunrice.access-admin', 'sunrice.menus.view', 'sunrice.menus.edit']);
    actingAs($user);

    get('/cms/api/terms?taxonomy=topics')->assertOk()->assertJsonPath('data.0.title', 'Laravel');
});
