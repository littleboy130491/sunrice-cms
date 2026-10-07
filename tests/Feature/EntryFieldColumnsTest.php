<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Setting;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->user = actingAsSuperAdmin();
    $this->tags = Taxonomy::factory()->create(['handle' => 'tags']);
    $this->term = Term::factory()->create(['taxonomy_id' => $this->tags->id]);
    $this->term->translations()->first()->update(['name' => 'Gardening']);

    $blueprint = Blueprint::create(['handle' => 'product', 'title' => 'Product', 'fields' => [
        ['handle' => 'price', 'type' => 'number', 'label' => 'Price'],
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
        ['handle' => 'featured', 'type' => 'toggle', 'label' => 'Featured'],
        ['handle' => 'size', 'type' => 'select', 'label' => 'Size', 'config' => ['options' => [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']]]],
        ['handle' => 'tags', 'type' => 'terms', 'label' => 'Tags', 'config' => ['taxonomy' => 'tags']],
        ['handle' => 'related', 'type' => 'entries', 'label' => 'Related'],
        ['handle' => 'specs', 'type' => 'group', 'label' => 'Specs', 'config' => ['fields' => [['handle' => 'weight', 'type' => 'text']]]],
    ]]);
    $this->collection = createCollection('products', [], $blueprint);
    $this->cheap = createEntry($this->collection, 'Cheap', ['price' => 5, 'body' => '<p>'.str_repeat('Long words ', 20).'</p>', 'featured' => true, 'size' => 's', 'tags' => [$this->term->id]]);
    $this->dear = createEntry($this->collection, 'Dear', ['price' => 50, 'featured' => false, 'size' => 'l', 'related' => [$this->cheap->id]]);
});

it('offers the blueprint fields as optional columns', function () {
    get('/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('columns.5.key', 'field.price')
        ->where('columns.5.label', 'Price')
        ->where('columns.5.sortable', true)
        ->where('columns.9.key', 'field.tags')
        ->where('columns.9.sortable', false)
        // No container columns.
        ->has('columns', 11)
        // Hidden until picked, and not computed then.
        ->where('visibleColumns', ['title', 'status', 'author', 'created_at', 'updated_at'])
        ->where('columnsKey', 'entries-products')
        ->where('rows.data.0', fn ($row) => ! collect($row)->has('field.price')));
});

it('shows picked field columns as short readable text', function () {
    put('/cms/table-preferences/entries-products', ['columns' => ['title', 'field.price', 'field.body', 'field.featured', 'field.size', 'field.tags', 'field.related']])->assertOk();

    get('/cms/collections/products/entries?sort=title')->assertInertia(function (Assert $page) {
        $page->where('visibleColumns', ['title', 'field.price', 'field.body', 'field.featured', 'field.size', 'field.tags', 'field.related']);
        $rows = $page->toArray()['props']['rows']['data'];
        expect($rows[0]['title'])->toBe('Cheap')
            ->and($rows[0]['field.price'])->toBe('5')
            ->and($rows[0]['field.body'])->toEndWith('…')
            ->and(mb_strlen($rows[0]['field.body']))->toBeLessThanOrEqual(81)
            ->and($rows[0]['field.body'])->not->toContain('<p>')
            ->and($rows[0]['field.featured'])->toBe('Yes')
            ->and($rows[0]['field.size'])->toBe('Small')
            ->and($rows[0]['field.tags'])->toBe('Gardening')
            ->and($rows[0]['field.related'])->toBeNull()
            ->and($rows[1]['field.featured'])->toBe('No')
            ->and($rows[1]['field.related'])->toBe('Cheap');
    });
});

it('sorts by a field column', function () {
    get('/cms/collections/products/entries?sort=-field.price')->assertInertia(fn (Assert $page) => $page
        ->where('meta.sort', '-field.price')
        ->where('rows.data.0.title', 'Dear'));
    get('/cms/collections/products/entries?sort=field.price')->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.0.title', 'Cheap'));
    get('/cms/collections/products/entries?sort=-field.featured')->assertInertia(fn (Assert $page) => $page
        ->where('meta.sort', '-field.featured'));
});

it('keeps an earlier shared column choice and drops unknown field columns', function () {
    Setting::set("table_columns.{$this->user->id}.entries", ['title', 'status', 'field.nope']);

    get('/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('visibleColumns', ['title', 'status']));
});
