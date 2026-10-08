<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Sunrice\Frontend\RouteMatcher;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Entry;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\View\Components\EntryFilter;

/** A term with a name and slug in the main language. */
function namedTerm(Taxonomy $taxonomy, string $name, ?Term $parent = null, int $order = 0): Term
{
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id, 'parent_id' => $parent?->id, 'sort_order' => $order]);
    $term->translations()->first()->update(['name' => $name, 'slug' => Str::slug($name)]);

    return $term->fresh();
}

beforeEach(function () {
    RouteMatcher::flush();
    $blueprint = Blueprint::create(['handle' => 'product', 'title' => 'Product', 'fields' => [
        ['handle' => 'price', 'type' => 'number', 'label' => 'Price'],
        ['handle' => 'brand', 'type' => 'select', 'label' => 'Brand', 'config' => ['options' => [['value' => 'acme', 'label' => 'Acme'], ['value' => 'zen', 'label' => 'Zen']]]],
        ['handle' => 'colors', 'type' => 'select', 'label' => 'Colors', 'config' => ['multiple' => true, 'options' => ['red', 'blue']]],
        ['handle' => 'in_stock', 'type' => 'toggle', 'label' => 'In stock'],
        ['handle' => 'summary', 'type' => 'textarea', 'label' => 'Summary'],
    ]]);
    $this->products = createCollection('products', ['route' => '/products/{slug}'], $blueprint);
    $this->categories = Taxonomy::factory()->create(['handle' => 'categories', 'hierarchical' => true]);
    $this->products->taxonomies()->attach($this->categories);

    $this->shoes = namedTerm($this->categories, 'Shoes', order: 1);
    $this->boots = namedTerm($this->categories, 'Boots', $this->shoes);
    $this->hats = namedTerm($this->categories, 'Hats', order: 0);
    $this->empty = namedTerm($this->categories, 'Gloves', order: 2);

    $make = function (string $title, array $data, array $terms) {
        $entry = createEntry($this->products, $title, $data);
        $entry->terms()->attach(array_map(fn (Term $t) => $t->id, $terms));

        return $entry;
    };
    $make('Linen boot', ['price' => 120, 'brand' => 'acme', 'colors' => ['red'], 'in_stock' => true, 'summary' => 'Soft linen'], [$this->boots]);
    $make('Rain shoe', ['price' => 60, 'brand' => 'zen', 'colors' => ['blue'], 'in_stock' => false, 'summary' => 'Waterproof'], [$this->shoes]);
    $make('Sun hat', ['price' => 25, 'brand' => 'acme', 'colors' => ['red', 'blue'], 'in_stock' => true, 'summary' => 'Straw'], [$this->hats]);
    createEntry($this->products, 'Draft hat', ['price' => 10], status: 'draft')->terms()->attach($this->hats->id);
});

it('lists terms in manual order with published entry counts', function () {
    $html = Blade::render(<<<'BLADE'
        <x-sunrice::terms taxonomy="categories" collection="products">@foreach ($component->terms as $t){{ $t->name }}:{{ $t->entries_count }};@endforeach</x-sunrice::terms>
        BLADE);

    // Flat list, by sort order then name; drafts aren't counted.
    expect(trim($html))->toBe('Boots:1;Hats:1;Shoes:1;Gloves:0;');
});

it('nests terms, hides empty ones, filters by parent and orders', function () {
    $tree = Blade::render(<<<'BLADE'
        <x-sunrice::terms taxonomy="categories" :tree="true" :hide-empty="true">@foreach ($component->terms as $t){{ $t->name }}[@foreach ($t->children as $c){{ $c->name }}@endforeach]@endforeach</x-sunrice::terms>
        BLADE);
    expect(trim($tree))->toBe('Hats[]Shoes[Boots]');

    expect(trim(Blade::render('<x-sunrice::terms taxonomy="categories" parent="shoes">@foreach ($component->terms as $t){{ $t->name }}@endforeach</x-sunrice::terms>')))->toBe('Boots')
        ->and(trim(Blade::render('<x-sunrice::terms taxonomy="categories" parent="root" order-by="name" :limit="2">@foreach ($component->terms as $t){{ $t->name }},@endforeach</x-sunrice::terms>')))->toBe('Gloves,Hats,')
        ->and(trim(Blade::render('<x-sunrice::terms taxonomy="categories" order-by="-entries" parent="root">@foreach ($component->terms as $t){{ $t->name }},@endforeach</x-sunrice::terms>')))->toEndWith('Gloves,');

    $entry = Entry::query()->whereHas('translations', fn ($q) => $q->where('title', 'Sun hat'))->first();
    expect(trim(Blade::render('<x-sunrice::terms taxonomy="categories" :entry="$entry">@foreach ($component->terms as $t){{ $t->name }}@endforeach</x-sunrice::terms>', ['entry' => $entry])))->toBe('Hats');
});

/** Titles the filter component returns for a query string. */
function filtered(string $query, array $filters, array $sorts = []): array
{
    request()->merge([])->query->replace([]);
    parse_str($query, $params);
    $request = Request::create('/shop', 'GET', $params);
    app()->instance('request', $request);

    $component = new EntryFilter('products', $filters, $sorts);

    return [$component, $component->entries->getCollection()->map(fn ($e) => $e->title)->all()];
}

it('filters entries from the query string', function () {
    $filters = [
        'q' => ['type' => 'search', 'fields' => ['title', 'summary']],
        'category' => ['type' => 'terms', 'taxonomy' => 'categories'],
        'brand' => 'select',
        'colors' => ['type' => 'select', 'multiple' => true],
        'price' => 'range',
        'in_stock' => 'toggle',
    ];
    $sorts = ['cheap' => ['label' => 'Price: low to high', 'order' => 'price'], 'pricey' => '-price'];

    [, $all] = filtered('', $filters, $sorts);
    expect($all)->toBe(['Sun hat', 'Rain shoe', 'Linen boot']);

    expect(filtered('q=linen', $filters)[1])->toBe(['Linen boot'])
        ->and(filtered('q=waterPROOF', $filters)[1])->toBe(['Rain shoe'])
        // A parent term includes its children.
        ->and(filtered('category[]=shoes', $filters, $sorts)[1])->toBe(['Rain shoe', 'Linen boot'])
        ->and(filtered('brand=acme&sort=pricey', $filters, $sorts)[1])->toBe(['Linen boot', 'Sun hat'])
        ->and(filtered('colors[]=blue', $filters, $sorts)[1])->toBe(['Sun hat', 'Rain shoe'])
        ->and(filtered('price_min=30&price_max=130', $filters, $sorts)[1])->toBe(['Rain shoe', 'Linen boot'])
        ->and(filtered('in_stock=1', $filters, $sorts)[1])->toBe(['Sun hat', 'Linen boot'])
        // Unknown values and undeclared parameters are ignored.
        ->and(filtered('brand=nope&title=x', $filters, $sorts)[1])->toHaveCount(3);
});

it('describes filters, sorts and active filters for the form', function () {
    [$component] = filtered('category[]=shoes&category[]=hats&brand=acme&price_min=10&sort=pricey&ref=mail', [
        'category' => ['type' => 'terms', 'taxonomy' => 'categories', 'hide_empty' => true],
        'brand' => 'select',
        'price' => 'range',
    ], ['newest' => '-published_at', 'pricey' => '-price']);

    $category = $component->filter('category');
    expect($category->inputs['value'])->toBe('category[]')
        ->and(collect($category->options)->pluck('label')->all())->toBe(['Hats', 'Shoes', 'Boots'])
        ->and(collect($category->options)->firstWhere('value', 'boots')['depth'])->toBe(1)
        ->and(collect($category->options)->where('selected', true)->pluck('value')->all())->toBe(['hats', 'shoes'])
        ->and($component->filter('brand')->options[0])->toMatchArray(['value' => 'acme', 'label' => 'Acme', 'selected' => true])
        ->and($component->filter('price')->inputs)->toBe(['min' => 'price_min', 'max' => 'price_max'])
        ->and(collect($component->sorts)->firstWhere('selected', true)->value)->toBe('pricey');

    expect($component->filtered())->toBeTrue()
        ->and(collect($component->active)->pluck('value')->all())->toBe(['Shoes', 'Hats', 'Acme', 'min 10'])
        // Removing one value keeps the others, the sort and unrelated parameters.
        ->and(urldecode($component->active[0]->remove_url))->toBe('http://localhost/shop?category[0]=hats&brand=acme&price_min=10&sort=pricey&ref=mail')
        ->and($component->clearUrl)->toBe('http://localhost/shop?ref=mail');
});

it('serves a default search page and searches inside templates', function () {
    $this->get('/search?q=linen')->assertOk()
        ->assertSee('Linen boot')->assertDontSee('Rain shoe')
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    $this->app->instance('request', Request::create('/anywhere', 'GET', ['q' => 'hat']));
    $html = Blade::render('<x-sunrice::search :results="true" collections="products">{{ $component->action }}|{{ $component->query }}|@foreach ($component->results as $e){{ $e->title }};@endforeach</x-sunrice::search>');

    expect(trim($html))->toBe(url('/search').'|hat|Sun hat;');
});
