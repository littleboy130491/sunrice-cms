<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAsSuperAdmin();
    $this->blog = createCollection('blog');
    $this->topics = Taxonomy::factory()->create(['handle' => 'topics', 'title' => 'Topics']);
    $this->format = Taxonomy::factory()->create(['handle' => 'format', 'title' => 'Format']);
    $this->other = Taxonomy::factory()->create(['handle' => 'other', 'title' => 'Other']);
    $this->blog->taxonomies()->sync([$this->topics->id, $this->format->id]);

    $this->news = Term::factory()->create(['taxonomy_id' => $this->topics->id]);
    $this->guides = Term::factory()->create(['taxonomy_id' => $this->topics->id]);
    $this->video = Term::factory()->create(['taxonomy_id' => $this->format->id]);
    $this->audio = Term::factory()->create(['taxonomy_id' => $this->format->id]);
    $this->stray = Term::factory()->create(['taxonomy_id' => $this->other->id]);
});

function saveEntry($entry, array $termIds)
{
    return put("/cms/entries/{$entry->id}", [
        'locale' => Locales::main(), 'title' => 'Post', 'slug' => 'post', 'data' => [], 'seo' => [], 'term_ids' => $termIds,
    ]);
}

it('tags an entry with terms of the attached taxonomies when saved', function () {
    $entry = createEntry($this->blog, 'Post');

    saveEntry($entry, [$this->news->id, $this->guides->id, $this->video->id])->assertSessionHasNoErrors();

    expect($entry->terms()->pluck('sunrice_terms.id')->sort()->values()->all())
        ->toBe(collect([$this->news->id, $this->guides->id, $this->video->id])->sort()->values()->all());

    saveEntry($entry, [$this->guides->id])->assertSessionHasNoErrors();
    expect($entry->terms()->pluck('sunrice_terms.id')->all())->toBe([$this->guides->id]);
});

it('ignores terms of taxonomies the collection does not use, and keeps existing ones', function () {
    $entry = createEntry($this->blog, 'Post');
    $entry->terms()->attach($this->stray->id);

    saveEntry($entry, [$this->news->id, $this->stray->id + 1000])->assertSessionHasNoErrors();

    expect($entry->terms()->pluck('sunrice_terms.id')->sort()->values()->all())
        ->toBe(collect([$this->news->id, $this->stray->id])->sort()->values()->all());
});

it('allows one term for taxonomies limited to one in this collection', function () {
    $this->blog->update(['settings' => array_merge($this->blog->settings, ['single_term_taxonomies' => [$this->format->id]])]);
    $entry = createEntry($this->blog, 'Post');

    saveEntry($entry, [$this->video->id, $this->audio->id])->assertSessionHasErrors('term_ids');
    saveEntry($entry, [$this->news->id, $this->guides->id, $this->audio->id])->assertSessionHasNoErrors();

    expect($entry->terms()->count())->toBe(3);
});

it('sets terms when creating an entry', function () {
    post('/cms/collections/blog/entries', ['title' => 'Fresh', 'data' => [], 'seo' => [], 'term_ids' => [$this->news->id]])
        ->assertSessionHasNoErrors();

    expect(Entry::query()->latest('id')->first()->terms()->pluck('sunrice_terms.id')->all())->toBe([$this->news->id]);
});

it('gives the editor the attached taxonomies and picked terms', function () {
    $this->blog->update(['settings' => array_merge($this->blog->settings, ['single_term_taxonomies' => [$this->format->id]])]);
    $entry = createEntry($this->blog, 'Post');
    $entry->terms()->attach([$this->news->id, $this->video->id]);

    get("/cms/entries/{$entry->id}")->assertInertia(fn (Assert $page) => $page
        ->where('taxonomies', fn ($taxonomies) => collect($taxonomies)->firstWhere('handle', 'format')['single'] === true)
        ->where("entry.terms_by_taxonomy.{$this->topics->id}", [$this->news->id])
        ->where("entry.terms_by_taxonomy.{$this->format->id}", [$this->video->id]));
});
