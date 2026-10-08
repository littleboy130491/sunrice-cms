<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Actions\Entries\ForceDeleteEntry;
use Sunrice\Admin\AdminUrls;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Entry;
use Sunrice\Query\EntryQuery;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->pages = createCollection('pages', ['route' => '/{slug}', 'hierarchical' => true]);
    $this->about = createEntry($this->pages, 'About');
    $this->team = createEntry($this->pages, 'Team');
    $this->leaders = createEntry($this->pages, 'Leaders');
    $this->team->update(['parent_id' => $this->about->id]);
    $this->leaders->update(['parent_id' => $this->team->id]);
    RouteMatcher::flush();
});

function slugOf(Entry $entry): string
{
    return $entry->translations()->first()->slug;
}

it('nests URLs under the parent entries', function () {
    $urls = app(UrlGenerator::class);
    $path = slugOf($this->about).'/'.slugOf($this->team).'/'.slugOf($this->leaders);

    expect($urls->entryUrl($this->leaders->fresh(), 'id'))->toBe('/'.$path)
        ->and($urls->entryUrl($this->leaders->fresh(), 'en'))->toBe('/en/'.$path)
        ->and($urls->entryUrl($this->about->fresh(), 'id'))->toBe('/'.slugOf($this->about))
        ->and($this->leaders->fresh()->ancestors())->toHaveCount(2);

    get('/'.$path)->assertOk()->assertSee('Leaders');
    get('/'.slugOf($this->about).'/'.slugOf($this->team))->assertOk()->assertSee('Team');
});

it('redirects a page found under the wrong parents to its address', function () {
    $path = '/'.slugOf($this->about).'/'.slugOf($this->team);

    get('/'.slugOf($this->team))->assertRedirect($path)->assertStatus(301);
    get('/'.slugOf($this->leaders).'/'.slugOf($this->team).'?ref=x')->assertRedirect($path.'?ref=x');
    get('/nope/'.slugOf($this->team).'/missing')->assertNotFound();
});

it('keeps single-segment URLs in collections that are not hierarchical', function () {
    $this->pages->update(['settings' => array_merge($this->pages->settings, ['hierarchical' => false])]);
    RouteMatcher::flush();

    expect(app(UrlGenerator::class)->entryUrl($this->team->fresh(), 'id'))->toBe('/'.slugOf($this->team));
    get('/'.slugOf($this->about).'/'.slugOf($this->team))->assertNotFound();
    get('/'.slugOf($this->team))->assertOk();
});

it('sets the parent from the editor and refuses loops and other collections', function () {
    $other = createEntry(createCollection('news'), 'Elsewhere');
    $save = fn (Entry $entry, mixed $parent) => put("/cms/entries/{$entry->id}", [
        'locale' => 'id', 'title' => $entry->translations()->first()->title, 'parent_id' => $parent,
    ]);

    $save($this->about, $this->leaders->id)->assertSessionHasErrors('parent_id');
    $save($this->about, $this->about->id)->assertSessionHasErrors('parent_id');
    $save($this->team, $other->id)->assertSessionHasErrors('parent_id');

    $save($this->leaders, $this->about->id)->assertSessionHasNoErrors();
    expect($this->leaders->fresh()->parent_id)->toBe($this->about->id);
    $save($this->leaders, null)->assertSessionHasNoErrors();
    expect($this->leaders->fresh()->parent_id)->toBeNull();

    // The picker leaves out the entry itself and its children.
    get(AdminUrls::entry($this->team))->assertInertia(fn (Assert $page) => $page
        ->where('entry.parent_id', $this->about->id)
        ->where('parentOptions', fn ($options) => collect($options)->pluck('id')->sort()->values()->all() === collect([$this->about->id, $this->leaders->id])->sort()->values()->all()));
});

it('lists children and moves them up when their parent is deleted', function () {
    expect(EntryQuery::forCollection($this->pages)->childrenOf($this->about)->get()->pluck('id')->all())->toBe([$this->team->id])
        ->and(EntryQuery::forCollection($this->pages)->childrenOf(null)->get()->pluck('id')->all())->toBe([$this->about->id]);

    get('/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page
        ->where('visibleColumns', fn ($c) => collect($c)->contains('parent'))
        ->where('rows.data', fn ($rows) => collect($rows)->firstWhere('id', $this->team->id)['parent'] === 'About'));

    app(ForceDeleteEntry::class)->handle($this->team);
    expect($this->leaders->fresh()->parent_id)->toBeNull();
});
