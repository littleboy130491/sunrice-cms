<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\TaxonomyFactory;
use Sunrice\Models\Concerns\HasTranslatedTitle;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property int|null $blueprint_id
 * @property bool $hierarchical
 * @property array<string,mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<TaxonomyFactory>
 */
class Taxonomy extends Model
{
    /** @use HasFactory<TaxonomyFactory> */
    use HasFactory, SoftDeletes;

    use HasTranslatedTitle;

    protected $table = 'sunrice_taxonomies';

    protected $guarded = [];

    protected $attributes = ['settings' => '{}'];

    protected $casts = [
        'hierarchical' => 'boolean',
        'settings' => 'array',
    ];

    protected static function newFactory(): TaxonomyFactory
    {
        return TaxonomyFactory::new();
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** @return HasMany<Term, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'sunrice_collection_taxonomy');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * URL patterns of the term archive pages, each with the collection
     * whose entries it lists (null = entries of every collection). The
     * route setting is a pattern (default `{collection}/{taxonomy}/{slug}`):
     *
     * - with {collection}: one archive per attached collection, the
     *   placeholder replaced by the collection's handle, e.g.
     *   `{collection}/topik/{slug}` → /artikel/topik/{slug}, /proyek/topik/{slug};
     * - without it: one archive across all collections, e.g. `topik/{slug}`;
     * - {taxonomy} is the taxonomy's handle;
     * - attached to no collection, {collection} is dropped.
     *
     * @return array<int, array{route: string, collection: Collection|null}>
     */
    public function termRoutes(): array
    {
        $pattern = Collection::normalizeRoute($this->setting('route')) ?? '/{collection}/{taxonomy}/{slug}';
        $pattern = str_replace('{taxonomy}', $this->handle, $pattern);

        if (! str_contains($pattern, '{collection}')) {
            return [['route' => $pattern, 'collection' => null]];
        }

        $collections = $this->relationLoaded('collections')
            ? $this->collections->sortBy('sort_order')->values()
            : $this->collections()->orderBy('sort_order')->orderBy('title')->get();

        if ($collections->isEmpty()) {
            return [['route' => Collection::normalizeRoute(str_replace('{collection}', '', $pattern)) ?? '/{slug}', 'collection' => null]];
        }

        return $collections
            ->map(fn (Collection $c) => ['route' => str_replace('{collection}', $c->handle, $pattern), 'collection' => $c])
            ->all();
    }

    /**
     * The term archive pattern to link to: the given collection's when it
     * has one, else the first.
     */
    public function termRoute(?Collection $collection = null): string
    {
        $routes = $this->termRoutes();
        foreach ($routes as $route) {
            if ($collection !== null && $route['collection']?->is($collection)) {
                return $route['route'];
            }
        }

        return $routes[0]['route'];
    }
}
