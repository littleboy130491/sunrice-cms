<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Setting;
use Sunrice\Permissions\SyncPermissions;
use Sunrice\Support\Branding;
use Sunrice\Support\Locales;
use Sunrice\Support\SiteSettings;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
});

/** A full settings payload with overrides. */
function siteSettings(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Sunrice Demo',
        'description' => 'A calm CMS.',
        'timezone' => 'Asia/Jakarta',
        'homepage_entry_id' => null,
        'locales' => ['main' => 'id', 'available' => ['id', 'en'], 'names' => ['id' => 'Indonesia', 'en' => 'English']],
        'seo' => ['noindex' => false, 'twitter_site' => '@sunrice', 'image' => null],
        'code' => ['head' => '<script>window.track = 1;</script>', 'body_start' => '<noscript>gtm</noscript>', 'body_end' => '<script src="/chat.js"></script>'],
    ], $overrides);
}

it('shows the current settings', function () {
    config(['app.name' => 'From config']);

    get('/cms/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Edit')
        ->where('settings.name', 'From config')
        ->where('settings.locales.main', 'id')
        ->has('timezones'));
});

it('saves settings and applies them over the config', function () {
    put('/cms/settings', siteSettings(['locales' => ['available' => ['id', 'en', 'fr'], 'names' => ['fr' => 'Français']]]))
        ->assertSessionHasNoErrors();

    expect(config('app.name'))->toBe('Sunrice Demo')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(date_default_timezone_get())->toBe('Asia/Jakarta')
        ->and(Locales::available())->toBe(['id', 'en', 'fr'])
        ->and(Locales::name('fr'))->toBe('Français')
        ->and(config('sunrice.seo.twitter_site'))->toBe('@sunrice');

    // A fresh boot reads them back from storage.
    config(['app.name' => 'Laravel', 'sunrice.locales.available' => ['id']]);
    Cache::flush();
    SiteSettings::apply();
    expect(config('app.name'))->toBe('Sunrice Demo')->and(Locales::available())->toBe(['id', 'en', 'fr']);
});

it('prints the code snippets and SEO defaults on public pages', function () {
    put('/cms/settings', siteSettings())->assertSessionHasNoErrors();
    $entry = createEntry(createCollection('pages'), 'Tentang');
    RouteMatcher::flush();

    get('/pages/tentang')->assertOk()
        ->assertSee('<script>window.track = 1;</script>', false)
        ->assertSee('<noscript>gtm</noscript>', false)
        ->assertSee('<script src="/chat.js"></script>', false)
        ->assertSee('<meta name="description" content="A calm CMS.">', false)
        ->assertSee('Sunrice Demo');
});

it('hides the whole site from search engines when asked', function () {
    createEntry(createCollection('pages'), 'Tentang');
    put('/cms/settings', siteSettings(['seo' => ['noindex' => true]]))->assertSessionHasNoErrors();

    get('/sitemap.xml')->assertOk()->assertDontSee('/pages/tentang');
});

it('sets the homepage', function () {
    $entry = createEntry(createCollection('pages'), 'Beranda');
    put('/cms/settings', siteSettings(['homepage_entry_id' => $entry->id]))->assertSessionHasNoErrors();

    expect((int) Setting::get('homepage_entry_id'))->toBe($entry->id);
    get('/')->assertOk()->assertSee('Beranda');
});

it('keeps the main language once content exists', function () {
    put('/cms/settings', siteSettings(['locales' => ['main' => 'en']]))->assertSessionHasNoErrors();
    expect(Locales::main())->toBe('en');

    createEntry(createCollection('pages'), 'Hello');
    put('/cms/settings', siteSettings(['locales' => ['main' => 'id']]))->assertSessionHasErrors('locales.main');
    expect(Locales::main())->toBe('en');
});

it('validates languages, timezone and handle', function () {
    put('/cms/settings', siteSettings([
        'timezone' => 'Mars/Olympus',
        'locales' => ['available' => ['id', 'English']],
        'seo' => ['twitter_site' => 'not a handle'],
    ]))->assertSessionHasErrors(['timezone', 'locales.available.1', 'seo.twitter_site']);
});

it('needs the settings permission', function () {
    app(SyncPermissions::class)->handle();
    $user = User::query()->create(['name' => 'E', 'email' => 'e@x.com', 'password' => bcrypt('password')]);
    $user->givePermissionTo('sunrice.access-admin');
    actingAs($user);

    get('/cms/settings')->assertForbidden();
    put('/cms/settings', siteSettings())->assertForbidden();
});

it('white-labels the admin with the branding settings', function () {
    put('/cms/settings', siteSettings(['branding' => ['name' => 'Acme Studio', 'tagline' => 'Newsroom', 'font' => 'inter', 'color' => '#1D4ED8']]))
        ->assertSessionHasNoErrors();

    get('/cms')->assertOk()
        ->assertSee('<title inertia>Acme Studio</title>', false)
        ->assertSee('family=inter:', false)
        ->assertSee('--sunrice-font:"Inter"', false)
        ->assertSee('--primary:#1d4ed8;--primary-foreground:#ffffff', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.name', 'Acme Studio')
            ->where('branding.tagline', 'Newsroom')
            ->where('branding.color', '#1d4ed8')
            ->where('branding.is_default', false));
});

it('picks dark text on a light accent color and rejects bad branding values', function () {
    expect(Branding::readableOn('#fde047'))->toBe('#111111')
        ->and(Branding::readableOn('#1d4ed8'))->toBe('#ffffff');

    put('/cms/settings', siteSettings(['branding' => ['font' => 'comic-sans', 'color' => 'blue']]))
        ->assertSessionHasErrors(['branding.font', 'branding.color']);
});

it('keeps the Sunrice defaults when branding is left empty', function () {
    put('/cms/settings', siteSettings(['branding' => ['name' => '', 'color' => null]]))->assertSessionHasNoErrors();

    get('/cms')->assertInertia(fn (Assert $page) => $page
        ->where('branding.name', 'Sunrice')
        ->where('branding.is_default', true)
        ->where('branding.color', null));
});

it('adds the site name to page titles when turned on', function () {
    $pages = createCollection('pages');
    createEntry($pages, 'Tentang');
    createEntry($pages, 'Sunrice Demo');
    RouteMatcher::flush();

    put('/cms/settings', siteSettings())->assertSessionHasNoErrors();
    get('/pages/tentang')->assertSee('<title>Tentang</title>', false);

    put('/cms/settings', siteSettings(['seo' => ['title_suffix' => true, 'title_separator' => ' – ']]))->assertSessionHasNoErrors();
    expect(Setting::get('site')['seo'])->toMatchArray(['title_suffix' => true, 'title_separator' => '–']);

    get('/pages/tentang')->assertOk()
        ->assertSee('<title>Tentang – Sunrice Demo</title>', false)
        ->assertSee('<meta property="og:title" content="Tentang">', false);
    // A title that already ends with the site name isn't doubled.
    get('/pages/sunrice-demo')->assertSee('<title>Sunrice Demo</title>', false);

    put('/cms/settings', siteSettings(['seo' => ['title_suffix' => true, 'title_separator' => '']]))->assertSessionHasNoErrors();
    get('/pages/tentang')->assertSee('<title>Tentang | Sunrice Demo</title>', false);
});

it('edits search engine and sharing settings on the SEO tab, and General keeps them', function () {
    get('/cms/settings/seo')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Seo')
        ->has('site')
        ->has('siteName'));

    put('/cms/settings/seo', ['seo' => ['noindex' => true, 'twitter_site' => '@gm', 'image' => null, 'title_suffix' => true, 'title_separator' => '-'], 'items' => []])
        ->assertSessionHasNoErrors();
    expect(config('sunrice.seo.noindex'))->toBeTrue()->and(config('sunrice.seo.title_separator'))->toBe('-');

    $general = siteSettings();
    unset($general['seo']);
    put('/cms/settings', $general)->assertSessionHasNoErrors();

    expect(Setting::get('site')['seo'])->toMatchArray(['noindex' => true, 'twitter_site' => '@gm', 'title_suffix' => true, 'title_separator' => '-'])
        ->and(Setting::get('site')['name'])->toBe('Sunrice Demo');

    put('/cms/settings/seo', ['seo' => ['twitter_site' => 'not valid!'], 'items' => []])->assertSessionHasErrors('seo.twitter_site');
});
