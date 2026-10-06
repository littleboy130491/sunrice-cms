<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Permissions\SyncPermissions;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    RouteMatcher::flush();
    $this->pages = createCollection('pages', ['route' => '/pages/{slug}', 'translatable' => true]);
});

/** A signed-in user holding exactly these permissions. */
function signedInWith(array $permissions): User
{
    app(SyncPermissions::class)->handle();
    $user = User::query()->create(['name' => 'Editor', 'email' => uniqid().'@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

it('shows a draft entry with a banner to users who may view drafts', function () {
    $entry = createEntry($this->pages, 'Coming soon', status: 'draft');
    signedInWith(['sunrice.access-admin', 'sunrice.view-drafts']);

    get('/pages/coming-soon')
        ->assertOk()
        ->assertSee('Coming soon')
        ->assertSee('data-sunrice-draft-banner', false)
        ->assertSee('is a draft and isn&#039;t visible to the public', false)
        ->assertSee(route('sunrice.admin.entries.edit', $entry), false)
        ->assertHeader('X-Robots-Tag', 'noindex');
});

it('keeps drafts hidden from guests and users without the permission', function () {
    createEntry($this->pages, 'Coming soon', status: 'draft');

    get('/pages/coming-soon')->assertNotFound();

    signedInWith(['sunrice.access-admin']);
    get('/pages/coming-soon')->assertNotFound();
});

it('explains scheduled entries in the banner', function () {
    $entry = createEntry($this->pages, 'Launch');
    $entry->update(['published_at' => now()->addWeek()]);
    signedInWith(['sunrice.view-drafts']);

    get('/pages/launch')->assertOk()->assertSee('is scheduled for', false);
});

it('renders the draft content, not the live content', function () {
    $entry = createEntry($this->pages, 'Coming soon', status: 'draft');
    $entry->mainTranslation()->update(['draft' => ['title' => 'Coming very soon', 'slug' => 'coming-soon', 'data' => [], 'seo' => []]]);
    signedInWith(['sunrice.view-drafts']);

    get('/pages/coming-soon')->assertOk()->assertSee('Coming very soon');
});

it('shows published pages without a banner', function () {
    createEntry($this->pages, 'About');
    signedInWith(['sunrice.view-drafts']);

    get('/pages/about')->assertOk()->assertDontSee('data-sunrice-draft-banner', false);
});

it('gives the entry editor a page link that says whether it is live', function () {
    actingAsSuperAdmin();
    $draft = createEntry($this->pages, 'Coming soon', status: 'draft');
    $live = createEntry($this->pages, 'About');

    get("/cms/entries/{$draft->id}")->assertInertia(fn (Assert $page) => $page
        ->where('entry.translations.id.url', '/pages/coming-soon')
        ->where('entry.translations.id.is_live', false)
        ->where('can.view_drafts', true));
    get("/cms/entries/{$live->id}")->assertInertia(fn (Assert $page) => $page
        ->where('entry.translations.id.is_live', true));
});

it('gives terms a page link when the taxonomy has term pages', function () {
    actingAsSuperAdmin();
    $taxonomy = Taxonomy::factory()->create(['handle' => 'topics', 'settings' => ['has_archive' => true, 'route' => 'topics']]);
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term->translations()->first()->update(['slug' => 'news']);

    get('/cms/taxonomies/topics')->assertInertia(fn (Assert $page) => $page->where('terms.0.url', '/topics/news'));

    $taxonomy->update(['settings' => ['has_archive' => false]]);
    get('/cms/taxonomies/topics')->assertInertia(fn (Assert $page) => $page->where('terms.0.url', null));
});
