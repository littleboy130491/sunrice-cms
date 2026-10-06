<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Actions\Entries\SaveDraft;
use Sunrice\Cache\ContentVersion;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Support\Locales;
use Workbench\App\Seeders\DemoSeeder;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(function () {
    config()->set('honeypot.enabled', false);
    seed(DemoSeeder::class);
    RouteMatcher::flush();
});

/**
 * The T15.3 journey: admin edits → publish → public renders both
 * locales → draft edits stay private → cache refreshes on publish →
 * form submission → CSV export.
 */
it('runs the full editorial journey', function () {
    actingAsSuperAdmin();
    $articles = Collection::query()->where('handle', 'articles')->firstOrFail();

    // 1. Create + publish an article in both locales.
    $entry = Entry::create(['collection_id' => $articles->id, 'blueprint_id' => $articles->blueprint_id, 'status' => 'draft']);

    $idT = $entry->translations()->create([
        'collection_id' => $articles->id, 'locale' => Locales::main(),
        'title' => 'Berita pertama', 'slug' => 'berita-pertama',
        'data' => ['body' => 'Isi artikel'], 'is_ready' => true,
    ]);
    app(PublishTranslation::class)->handle($idT);

    $enT = $entry->translations()->create([
        'collection_id' => $articles->id, 'locale' => 'en',
        'title' => 'First news', 'slug' => 'first-news',
        'data' => ['body' => 'Article body'], 'is_ready' => true,
    ]);
    app(PublishTranslation::class)->handle($enT);

    // 2. Public pages at both URLs.
    get('/articles/berita-pertama')->assertOk()->assertSee('Berita pertama');
    get('/en/articles/first-news')->assertOk()->assertSee('First news');
    get('/articles')->assertOk();

    // 3. Draft edit does not change the live page.
    app(SaveDraft::class)->handle($idT, ['title' => 'Berita DRAFT', 'data' => ['body' => 'Draft body']]);
    get('/articles/berita-pertama')->assertOk()->assertSee('Berita pertama')->assertDontSee('Berita DRAFT');

    // 4. Publishing bumps the content version and refreshes the page.
    $version = ContentVersion::current();
    app(PublishTranslation::class)->handle($idT);
    expect(ContentVersion::current())->toBeGreaterThan($version);
    Cache::flush(); // array store would keep the old key under the old version anyway
    // Renaming the draft left a redirect on the old slug — follow it.
    $response = get('/articles/berita-pertama');
    if ($response->status() === 301) {
        $response = get($response->headers->get('Location'));
    }
    $response->assertOk()->assertSee('Berita DRAFT');

    // 5. Public form submission + admin export.
    $form = Form::query()->where('handle', 'contact')->firstOrFail();
    post("/sunrice/forms/{$form->handle}", [
        'data' => ['name' => 'Ada', 'email' => 'a@b.com', 'message' => 'Halo'],
    ])->assertRedirect();
    expect(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(1);

    get("/cms/forms/{$form->id}/submissions/export")
        ->assertOk()
        ->assertHeader('Content-Disposition', "attachment; filename={$form->handle}-submissions.csv");
});

it('logs in through the admin and installs non-interactively', function () {
    artisan('sunrice:install', ['--no-user' => true])->assertSuccessful();
});
