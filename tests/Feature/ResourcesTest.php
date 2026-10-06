<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Sunrice\Models\Permission;
use Sunrice\Permissions\SyncPermissions;
use Sunrice\Sunrice;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\App\Policies\ProductPolicy;
use Workbench\App\Sunrice\ProductResource;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    app(Sunrice::class)->registerResource(ProductResource::class);
    app(SyncPermissions::class)->handle();
    actingAsSuperAdmin();
});

it('exposes resource index, create, store, update and delete', function () {
    get('/cms/resources/products')->assertOk();

    post('/cms/resources/products', [
        'title' => 'Rice Cooker', 'sku' => 'RC-1', 'price' => 99.5, 'active' => true,
    ])->assertRedirect('/cms/resources/products');

    $product = Product::query()->firstWhere('sku', 'RC-1');
    expect($product)->not->toBeNull()->and($product->title)->toBe('Rice Cooker');

    get("/cms/resources/products/{$product->id}/edit")->assertOk();

    put("/cms/resources/products/{$product->id}", [
        'title' => 'Rice Cooker Pro', 'sku' => 'RC-1', 'price' => 129, 'active' => false,
    ])->assertRedirect();
    expect($product->refresh()->title)->toBe('Rice Cooker Pro')
        ->and($product->active)->toBeFalse();

    delete("/cms/resources/products/{$product->id}")->assertRedirect();
    expect(Product::query()->find($product->id))->toBeNull();
});

it('validates with the resource rules', function () {
    post('/cms/resources/products', ['title' => 'No SKU', 'price' => -1])
        ->assertSessionHasErrors(['sku', 'price']);
});

it('denies users lacking the resource permission', function () {
    $user = User::query()->create(['name' => 'Nope', 'email' => 'nope@x.com', 'password' => 'x']);
    $user->givePermissionTo('sunrice.access-admin');

    $this->actingAs($user);
    get('/cms/resources/products')->assertForbidden();
});

it('enforces the model policy when one is registered', function () {
    $product = Product::query()->create(['title' => 'P', 'sku' => 'P1', 'price' => 1]);

    Gate::policy(Product::class, ProductPolicy::class);

    // Sunrice resource permission held, model policy denies update.
    $user = User::query()->create(['name' => 'Editor', 'email' => 'ed@x.com', 'password' => 'x']);
    $user->givePermissionTo(['sunrice.access-admin', 'sunrice.resources.products.edit']);
    $this->actingAs($user);

    put("/cms/resources/products/{$product->id}", [
        'title' => 'New', 'sku' => 'P1', 'price' => 1,
    ])->assertForbidden();
});

it('syncs belongs_to relations and serves the options endpoint', function () {
    $owner = User::query()->create(['name' => 'Owner One', 'email' => 'o@x.com', 'password' => 'x']);
    User::query()->create(['name' => 'Owner Two', 'email' => 'o2@x.com', 'password' => 'x']);

    post('/cms/resources/products', [
        'title' => 'P', 'sku' => 'P2', 'price' => 10, 'owner' => $owner->id,
    ]);
    expect(Product::query()->firstWhere('sku', 'P2')->owner_id)->toBe($owner->id);

    get('/cms/api/resources/products/options/owner?q=Owner+Two')
        ->assertOk()
        ->assertJsonPath('options.0.label', 'Owner Two');
});

it('exports resource rows to CSV', function () {
    Product::query()->create(['title' => 'Export Me', 'sku' => 'EX-1', 'price' => 3]);

    get('/cms/resources/products/export')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=products.csv');
});

it('applies resource filters and sortable columns on the index', function () {
    Product::query()->create(['title' => 'B kettle', 'sku' => 'K-1', 'price' => 10, 'active' => true]);
    Product::query()->create(['title' => 'A fan', 'sku' => 'F-1', 'price' => 20, 'active' => false]);

    get('/cms/resources/products?filters[active]=1')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.title', 'B kettle')
        ->where('meta.filters.active', '1')
        ->where('filters.0.key', 'active')
        ->where('visibleColumns', ['title', 'sku', 'price', 'active']));

    get('/cms/resources/products?sort=title')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('rows.data.0.title', 'A fan')
        ->where('meta.sort', 'title'));
});

it('saves table column choices as JSON', function () {
    $this->putJson('/cms/table-preferences/resource.products', ['columns' => ['title', 'price']])
        ->assertOk()->assertJson(['columns' => ['title', 'price']]);

    get('/cms/resources/products')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('visibleColumns', ['title', 'price']));
});
