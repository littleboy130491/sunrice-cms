<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Navigation;
use Sunrice\Http\Controllers\Admin\DocsController;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/** A signed-in admin user with these permissions on top of admin access. */
function docsUser(array $permissions = []): User
{
    app(SyncPermissions::class)->handle();
    $user = User::query()->create(['name' => 'Dev', 'email' => uniqid().'@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo(['sunrice.access-admin', ...$permissions]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

it('lists a guide file for every docs page', function () {
    foreach (DocsController::SECTIONS as $pages) {
        foreach ($pages as $page) {
            expect(DocsController::path($page))->toBeFile();
        }
    }
});

it('needs the docs permission', function () {
    $user = docsUser();

    get('/cms/docs')->assertForbidden();
    expect(collect(app(Navigation::class)->for($user))->pluck('items')->flatten(1)->pluck('href'))->not->toContain('docs');
});

it('shows the docs to users with the permission', function () {
    $user = docsUser(['sunrice.docs.view']);

    expect(collect(app(Navigation::class)->for($user))->pluck('items')->flatten(1)->pluck('href'))->toContain('docs');

    get('/cms/docs')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Docs/Show')
        ->where('page', 'installation')
        ->where('title', 'Installation')
        ->has('sections', count(DocsController::SECTIONS)));
});

it('renders a guide with heading anchors and admin links between guides', function () {
    docsUser(['sunrice.docs.view']);

    get('/cms/docs/content-modeling')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('title', 'Content modeling')
        ->where('html', fn (string $html) => str_contains($html, 'href="/cms/docs/multilingual#')
            && ! str_contains($html, '.md"')));

    get('/cms/docs/commands')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<table>')));

    get('/cms/docs/multilingual')->assertOk()->assertInertia(fn (Assert $page) => $page
        // Anchors other guides link to resolve.
        ->where('headings', fn ($headings) => collect($headings)->pluck('id')->contains('storage-format'))
        ->where('html', fn (string $html) => str_contains($html, 'id="storage-format"')));
});

it('404s for unknown guides', function () {
    docsUser(['sunrice.docs.view']);

    get('/cms/docs/nope')->assertNotFound();
});
