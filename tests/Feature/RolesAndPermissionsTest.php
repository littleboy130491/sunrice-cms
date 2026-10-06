<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Database\Seeders\RolesSeeder;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Entry;
use Sunrice\Permissions\PermissionRegistry;
use Sunrice\Permissions\SyncPermissions;
use Sunrice\Support\Locales;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/** A user holding exactly these permissions. */
function userWith(array $permissions): User
{
    app(SyncPermissions::class)->handle();
    $user = User::query()->create(['name' => 'U', 'email' => uniqid().'@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo(['sunrice.access-admin', ...$permissions]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

it('defines view/create/edit/delete permissions per area instead of manage-*', function () {
    $names = app(PermissionRegistry::class)->names();

    foreach (['collections', 'blueprints', 'fieldsets', 'taxonomies', 'menus', 'globals', 'users', 'roles'] as $area) {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            expect($names)->toContain("sunrice.{$area}.{$action}");
        }
    }

    expect($names)->toContain('sunrice.assets.edit')
        ->and(collect($names)->filter(fn ($n) => str_contains($n, 'manage-'))->all())->toBe([]);
});

it('adds a translate permission per collection', function () {
    $collection = createCollection();

    expect(app(PermissionRegistry::class)->names())->toContain("sunrice.entries.{$collection->id}.translate");
});

it('hands legacy manage-* permissions on to the roles that held them', function () {
    Permission::query()->delete();
    $guard = config('sunrice.auth.guard', 'web');
    Permission::create(['name' => 'sunrice.manage-structure', 'guard_name' => $guard]);
    Permission::create(['name' => 'sunrice.manage-users', 'guard_name' => $guard]);
    Permission::create(['name' => 'sunrice.assets.upload', 'guard_name' => $guard]);
    $role = Role::findOrCreate('Old editor', $guard);
    $role->givePermissionTo(['sunrice.manage-structure', 'sunrice.manage-users', 'sunrice.assets.upload']);

    app(SyncPermissions::class)->handle();

    $names = $role->fresh()->permissions->pluck('name')->all();
    expect($names)->toContain('sunrice.blueprints.edit', 'sunrice.collections.delete', 'sunrice.forms.create', 'sunrice.users.delete', 'sunrice.assets.edit')
        ->not->toContain('sunrice.menus.view', 'sunrice.manage-structure')
        ->and(Permission::query()->where('name', 'like', 'sunrice.manage-%')->exists())->toBeFalse();
});

it('checks each structure area separately', function () {
    userWith(['sunrice.blueprints.view']);

    get('/cms/structure/blueprints')->assertOk();
    get('/cms/structure/blueprints/create')->assertForbidden();
    post('/cms/structure/blueprints', ['handle' => 'x', 'title' => 'X'])->assertForbidden();
    get('/cms/structure/fieldsets')->assertForbidden();
    post('/cms/structure/collections/reorder', ['items' => [1]])->assertForbidden();
});

it('lets a translator edit other languages but not the main language or publish', function () {
    $collection = createCollection('pages', ['translatable' => true]);
    $entry = createEntry($collection, 'Beranda', ['body' => 'Halo']);
    userWith(["sunrice.entries.{$collection->id}.view", "sunrice.entries.{$collection->id}.translate"]);

    get("/cms/entries/{$entry->id}")->assertOk();

    put("/cms/entries/{$entry->id}", ['locale' => 'en', 'title' => 'Home', 'data' => ['body' => 'Hello'], 'is_ready' => true])
        ->assertRedirect();
    $en = Entry::query()->find($entry->id)->translation('en');
    expect($en->draft['title'])->toBe('Home')
        ->and($en->is_ready)->toBeFalse(); // making it live needs edit rights

    put("/cms/entries/{$entry->id}", ['locale' => Locales::main(), 'title' => 'Diubah', 'data' => []])->assertForbidden();
    post("/cms/entries/{$entry->id}/publish", ['locale' => 'en'])->assertForbidden();
    post("/cms/collections/{$collection->handle}/entries/reorder", ['items' => [$entry->id]])->assertForbidden();
});

it('treats edit rights as including translate', function () {
    $collection = createCollection('pages', ['translatable' => true]);
    $entry = createEntry($collection, 'Beranda');
    userWith(["sunrice.entries.{$collection->id}.view", "sunrice.entries.{$collection->id}.edit"]);

    put("/cms/entries/{$entry->id}", ['locale' => 'en', 'title' => 'Home', 'data' => []])->assertRedirect();
    expect(Entry::query()->find($entry->id)->translation('en')->draft['title'])->toBe('Home');
});

it('keeps user managers away from super admins', function () {
    $superRole = Role::findOrCreate(config('sunrice.super_admin_role'), 'web');
    $super = User::query()->create(['name' => 'S', 'email' => 's@x.com', 'password' => bcrypt('password')]);
    $super->assignRole($superRole);
    userWith(['sunrice.users.view', 'sunrice.users.create', 'sunrice.users.edit', 'sunrice.users.delete']);

    put("/cms/users/{$super->id}", ['name' => 'Taken over'])->assertForbidden();
    $this->delete("/cms/users/{$super->id}")->assertForbidden();

    post('/cms/users', [
        'name' => 'Ed', 'email' => 'ed@x.com',
        'password' => 'secret-pw-123', 'password_confirmation' => 'secret-pw-123',
        'roles' => [config('sunrice.super_admin_role')],
    ])->assertSessionHasErrors('roles');
    expect(User::query()->where('email', 'ed@x.com')->exists())->toBeFalse();
});

it('saves role permissions that have not been synced yet and flashes', function () {
    $collection = createCollection('news');
    $role = Role::findOrCreate('writer', 'web');
    userWith(['sunrice.roles.view', 'sunrice.roles.edit']);
    // A collection created without a permission sync still shows up in the editor.
    Permission::query()->where('name', "sunrice.entries.{$collection->id}.translate")->delete();

    put("/cms/roles/{$role->id}", ['name' => 'writer', 'permissions' => ["sunrice.entries.{$collection->id}.translate"]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Role saved.');
    expect($role->fresh()->hasPermissionTo("sunrice.entries.{$collection->id}.translate"))->toBeTrue();

    put("/cms/roles/{$role->id}", ['permissions' => ['sunrice.made-up']])->assertSessionHasErrors('permissions.0');
});

it('refuses to delete an assigned role or yourself with a message', function () {
    $role = Role::findOrCreate('writer', 'web');
    $me = userWith(['sunrice.roles.view', 'sunrice.roles.delete', 'sunrice.users.view', 'sunrice.users.delete']);
    $me->assignRole($role);

    $this->delete("/cms/roles/{$role->id}")->assertRedirect()->assertSessionHas('error');
    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();

    $this->delete("/cms/users/{$me->id}")->assertRedirect()->assertSessionHas('error', 'You cannot delete yourself.');
});

it('shares a fresh flash id so repeated saves each toast', function () {
    $role = Role::findOrCreate('writer', 'web');
    userWith(['sunrice.roles.view', 'sunrice.roles.edit']);

    $ids = [];
    foreach ([1, 2] as $_) {
        put("/cms/roles/{$role->id}", ['name' => 'writer'])->assertSessionHas('success');
        get("/cms/roles/{$role->id}/edit")->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids[] = $page->toArray()['props']['flash']['id'];
        });
    }
    expect($ids[0])->not->toBeNull()->and($ids[1])->not->toBe($ids[0]);
});

it('does not let role managers edit the super admin role', function () {
    $superRole = Role::findOrCreate(config('sunrice.super_admin_role'), 'web');
    userWith(['sunrice.roles.view', 'sunrice.roles.edit', 'sunrice.roles.delete']);

    put("/cms/roles/{$superRole->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->delete("/cms/roles/{$superRole->id}")->assertForbidden();
    expect($superRole->fresh()->name)->toBe(config('sunrice.super_admin_role'));
});

it('seeds the default roles and tops them up for new collections', function () {
    $pages = createCollection('pages');
    artisan('sunrice:seed-roles')->assertSuccessful();

    $perms = fn (string $role) => Role::findByName($role, 'web')->permissions->pluck('name')->all();

    expect($perms('Translator'))->toContain("sunrice.entries.{$pages->id}.translate", "sunrice.entries.{$pages->id}.view")
        ->not->toContain("sunrice.entries.{$pages->id}.edit", "sunrice.entries.{$pages->id}.publish")
        ->and($perms('Author'))->toContain("sunrice.entries.{$pages->id}.edit-own")
        ->not->toContain("sunrice.entries.{$pages->id}.edit", "sunrice.entries.{$pages->id}.publish")
        ->and($perms('Editor'))->toContain("sunrice.entries.{$pages->id}.publish", 'sunrice.menus.edit')
        ->not->toContain('sunrice.users.view', 'sunrice.blueprints.edit')
        ->and($perms('Administrator'))->toContain('sunrice.users.delete', 'sunrice.blueprints.edit');

    // An admin change survives, and a new collection's permissions are added.
    Role::findByName('Editor', 'web')->givePermissionTo('sunrice.users.view');
    $news = createCollection('news', [], Blueprint::create(['handle' => 'news', 'title' => 'News', 'fields' => []]));
    app(SyncPermissions::class)->handle();
    (new RolesSeeder)->run();

    expect($perms('Editor'))->toContain('sunrice.users.view', "sunrice.entries.{$news->id}.publish")
        ->and($perms('Translator'))->toContain("sunrice.entries.{$news->id}.translate");
});

it('can seed the default roles while installing', function () {
    artisan('sunrice:install', ['--no-user' => true, '--roles' => true])->assertSuccessful();

    expect(Role::query()->whereIn('name', ['Administrator', 'Editor', 'Author', 'Translator'])->count())->toBe(4);
});
