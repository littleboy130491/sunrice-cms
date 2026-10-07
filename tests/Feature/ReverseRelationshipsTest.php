<?php

declare(strict_types=1);

use Sunrice\Models\Blueprint;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

beforeEach(function () {
    $this->industries = Taxonomy::factory()->create(['handle' => 'industries', 'hierarchical' => true]);
    $this->tech = Term::factory()->create(['taxonomy_id' => $this->industries->id]);
    $this->tech->translations()->first()->update(['slug' => 'tech']);
    $this->ai = Term::factory()->create(['taxonomy_id' => $this->industries->id, 'parent_id' => $this->tech->id]);
    $this->ai->translations()->first()->update(['slug' => 'ai']);
    $this->retail = Term::factory()->create(['taxonomy_id' => $this->industries->id]);

    $blueprint = Blueprint::create(['handle' => 'project', 'title' => 'Project', 'fields' => [
        ['handle' => 'related_articles', 'type' => 'entries'],
        ['handle' => 'industries', 'type' => 'terms', 'config' => ['taxonomy' => 'industries']],
    ]]);
    $this->articles = createCollection('articles');
    $this->projects = createCollection('projects', [], $blueprint);

    $this->a1 = createEntry($this->articles, 'First article');
    $this->a2 = createEntry($this->articles, 'Second article');

    $this->p1 = createEntry($this->projects, 'Shop', ['related_articles' => [$this->a1->id], 'industries' => [$this->retail->id]]);
    $this->p2 = createEntry($this->projects, 'Robot', ['related_articles' => [$this->a1->id, $this->a2->id], 'industries' => [$this->ai->id]]);
    $this->p3 = createEntry($this->projects, 'Cloud', ['related_articles' => [], 'industries' => [$this->tech->id]]);
});

function reverseTitles($entries): array
{
    return collect($entries)->pluck('title')->sort()->values()->all();
}

it('finds entries that link to an entry through an entries field', function () {
    expect(reverseTitles(sunrice_entries('projects')->whereEntry('related_articles', $this->a1)->get()))->toBe(['Robot', 'Shop'])
        ->and(reverseTitles(sunrice_entries('projects')->whereEntry('related_articles', $this->a2->id)->get()))->toBe(['Robot'])
        ->and(reverseTitles(sunrice_entries('projects')->whereEntry('related_articles', [$this->a2, 999])->get()))->toBe(['Robot'])
        ->and(sunrice_entries('projects')->whereEntry('related_articles', [])->get())->toBeEmpty();
});

it('finds entries through a terms field by model, id or slug', function () {
    expect(reverseTitles(sunrice_entries('projects')->whereFieldTerm('industries', $this->retail)->get()))->toBe(['Shop'])
        ->and(reverseTitles(sunrice_entries('projects')->whereFieldTerm('industries', $this->ai->id)->get()))->toBe(['Robot'])
        ->and(reverseTitles(sunrice_entries('projects')->whereFieldTerm('industries', 'tech')->get()))->toBe(['Cloud'])
        // Child terms too.
        ->and(reverseTitles(sunrice_entries('projects')->whereFieldTerm('industries', 'tech', includeChildren: true)->get()))->toBe(['Cloud', 'Robot'])
        ->and(reverseTitles(sunrice_entries('projects')->whereFieldTerm('industries', ['ai', $this->retail])->get()))->toBe(['Robot', 'Shop']);
});

it('combines with other filters', function () {
    expect(reverseTitles(sunrice_entries('projects')
        ->whereEntry('related_articles', $this->a1)
        ->whereFieldTerm('industries', 'retail-does-not-exist')
        ->get()))->toBe([])
        ->and(reverseTitles(sunrice_entries('projects')
            ->whereEntry('related_articles', $this->a1)
            ->whereFieldTerm('industries', $this->ai)
            ->get()))->toBe(['Robot']);
});

it('refuses field names that could reach raw SQL', function () {
    expect(fn () => sunrice_entries('projects')->whereEntry("x') or 1=1 --", 1))->toThrow(InvalidArgumentException::class);
});

it('filters on entry columns: id, parent, title and publish date', function () {
    expect(reverseTitles(sunrice_entries('projects')->where('id', '!=', $this->p1->id)->get()))->toBe(['Cloud', 'Robot'])
        ->and(reverseTitles(sunrice_entries('projects')->where('id', 'in', [$this->p1->id, $this->p2->id])->get()))->toBe(['Robot', 'Shop'])
        ->and(reverseTitles(sunrice_entries('projects')->where('title', 'Robot')->get()))->toBe(['Robot'])
        ->and(reverseTitles(sunrice_entries('projects')->where('parent_id', null)->get()))->toBe(['Cloud', 'Robot', 'Shop']);

    $this->p3->update(['published_at' => now()->subYear()]);
    expect(reverseTitles(sunrice_entries('projects')->where('published_at', '<', now()->subMonth())->get()))->toBe(['Cloud'])
        ->and(sunrice_entries('projects')->orderBy('id', 'desc')->get()->first()->id)->toBe($this->p3->id);
});
