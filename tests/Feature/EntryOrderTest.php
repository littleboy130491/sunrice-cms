<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Entry;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Revision;
use Sunrice\Query\EntryQuery;
use Sunrice\View\Components\Entries;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->team = createCollection('team');
    $this->a = createEntry($this->team, 'Ana');
    $this->b = createEntry($this->team, 'Budi');
    $this->c = createEntry($this->team, 'Citra');
    // Created and updated in different orders.
    $this->a->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDay(), 'published_at' => now()->subDays(3)])->save();
    $this->b->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(5), 'published_at' => now()->subDays(2)])->save();
    $this->c->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDays(4), 'published_at' => now()->subDay()])->save();
});

function titles(string $collection, ?string $orderBy = null): array
{
    return (new Entries($collection, orderBy: $orderBy))->entries->pluck('title')->all();
}

it('appends new entries to the manual order and reorders across pages', function () {
    expect([$this->a->fresh()->sort_order, $this->b->fresh()->sort_order, $this->c->fresh()->sort_order])->toBe([1, 2, 3]);

    // A page showing only Ana and Citra (Budi elsewhere): they swap places, Budi stays put.
    post('/cms/collections/team/entries/reorder', ['items' => [$this->c->id, $this->a->id]])
        ->assertSessionHas('success', 'Order saved.');
    expect(titles('team', 'manual'))->toBe(['Citra', 'Budi', 'Ana']);
});

it('orders templates by the collection default unless order-by is given', function () {
    $this->team->update(['settings' => ['sort' => 'manual']]);
    expect(titles('team'))->toBe(['Ana', 'Budi', 'Citra']);

    $this->team->update(['settings' => ['sort' => 'created_at']]);
    expect(titles('team'))->toBe(['Citra', 'Budi', 'Ana']);

    $this->team->update(['settings' => ['sort' => 'updated_at', 'sort_direction' => 'asc']]);
    expect(titles('team'))->toBe(['Budi', 'Citra', 'Ana']);

    expect(titles('team', 'manual'))->toBe(['Ana', 'Budi', 'Citra'])
        ->and(titles('team', '-created_at'))->toBe(['Citra', 'Budi', 'Ana'])
        ->and(titles('team', 'updated_at desc'))->toBe(['Ana', 'Citra', 'Budi'])
        ->and(EntryQuery::forCollection($this->team->fresh())->get()->pluck('title')->all())->toBe(['Budi', 'Citra', 'Ana']);
});

it('saves the order setting and lets the admin list drag manually ordered entries', function () {
    $this->put("/cms/structure/collections/{$this->team->id}", [
        'title' => 'Team', 'settings' => ['sort' => 'manual'],
    ])->assertSessionHasNoErrors();
    $this->put("/cms/structure/collections/{$this->team->id}", ['title' => 'Team', 'settings' => ['sort' => 'random']])
        ->assertSessionHasErrors('settings.sort');

    get('/cms/collections/team/entries')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can.reorder', true)
        ->where('rows.data.0.title', 'Ana')
        ->where('rows.data.2.title', 'Citra'));

    // A search hides the manual order: no dragging, and a hint says why.
    get('/cms/collections/team/entries?search=a')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can.reorder', false)
        ->where('reorderPaused', true));
});

it('gives entries no page when the collection has no single pages', function () {
    $this->team->update(['settings' => ['has_single' => false]]);
    RouteMatcher::flush();
    $menu = Menu::factory()->create(['handle' => 'main']);
    MenuItem::query()->create(['menu_id' => $menu->id, 'sort_order' => 0, 'type' => 'entry', 'target_id' => $this->a->id, 'labels' => [], 'new_tab' => false]);

    $ana = Entry::query()->find($this->a->id);
    $ana->resolveFor('id');
    expect($ana->url)->toBeNull()
        ->and(sunrice_menu('main'))->toHaveCount(0);

    get('/team/ana')->assertNotFound();

    $this->getJson('/cms/api/entries?linkable=1')->assertJsonCount(0, 'data');
    $this->getJson('/cms/api/entries')->assertJsonCount(3, 'data');
});

it('restores a revision into the draft and can undo it', function () {
    $entry = $this->a;
    $t = $entry->mainTranslation();
    $this->post("/cms/entries/{$entry->id}/publish", ['locale' => 'id'])->assertSessionHasNoErrors();
    $revision = Revision::query()->where('entry_translation_id', $t->id)->latest('id')->firstOrFail();

    $this->put("/cms/entries/{$entry->id}", ['locale' => 'id', 'title' => 'Ana (draft)', 'slug' => 'ana', 'data' => []])->assertSessionHasNoErrors();
    expect($t->fresh()->draft['title'])->toBe('Ana (draft)');

    post("/cms/revisions/{$revision->id}/restore")->assertSessionHas('success');
    expect($t->fresh()->draft['title'])->toBe('Ana');
    get("/cms/entries/{$entry->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('entry.translations.id.can_undo_restore', true)
        ->where('entry.translations.id.revisions.0.created_at_iso', $revision->created_at->toIso8601String()));

    post("/cms/entry-translations/{$t->id}/undo-restore")->assertSessionHas('success');
    expect($t->fresh()->draft['title'])->toBe('Ana (draft)');
    post("/cms/entry-translations/{$t->id}/undo-restore")->assertSessionHas('error');
});

it('changes the publish date of a published entry without publishing the draft', function () {
    $entry = $this->a;
    $this->put("/cms/entries/{$entry->id}", ['locale' => 'id', 'title' => 'Unpublished edit', 'slug' => 'ana', 'data' => []]);

    $this->put("/cms/entries/{$entry->id}/publish-date", ['published_at' => now()->addDays(2)->toIso8601String()])
        ->assertSessionHas('success', 'Entry scheduled.');
    $fresh = $entry->fresh();
    expect($fresh->published_at->isFuture())->toBeTrue()
        ->and($fresh->mainTranslation()->title)->toBe('Ana')
        ->and(Entry::query()->scheduled()->whereKey($entry->id)->exists())->toBeTrue();

    $this->post("/cms/entries/{$entry->id}/unpublish");
    $this->put("/cms/entries/{$entry->id}/publish-date", ['published_at' => now()->toIso8601String()])
        ->assertSessionHas('error');
});
