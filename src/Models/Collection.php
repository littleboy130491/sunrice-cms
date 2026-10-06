<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\CollectionFactory;
use Sunrice\Fields\BlueprintSchema;
use Sunrice\Support\Locales;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property int|null $blueprint_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property array<string, mixed> $settings
 * @property array<string, mixed>|null $archive_data
 *
 * settings keys: route, has_single, has_archive, archive_route,
 * translatable, template, archive_template, archive_blueprint_id,
 * default_sort ('manual'|'published_at_desc'|'title_asc'), per_page,
 * icon, table_columns (array of field handles).
 *
 * @use HasFactory<CollectionFactory>
 */
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory;

    protected $table = 'sunrice_collections';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'archive_data' => 'array',
    ];

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return BelongsToMany<Taxonomy, $this> */
    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'sunrice_collection_taxonomy');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * URL pattern of single entries, e.g. '/blog/{slug}'. Without a
     * custom route it follows the handle: '/{handle}/{slug}'.
     */
    public function entryRoute(): string
    {
        $route = static::normalizeRoute($this->setting('route'));

        return $route ?? '/'.$this->handle.'/{slug}';
    }

    /**
     * Turns what an editor typed into a URL pattern: 'blog' and '/blog/'
     * become '/blog/{slug}', '/' becomes '/{slug}' (site root), patterns
     * with {slug} are kept. Empty means "follow the handle" (null).
     */
    public static function normalizeRoute(mixed $route): ?string
    {
        if (! is_string($route) || trim($route) === '') {
            return null;
        }

        $route = '/'.trim(preg_replace('#/+#', '/', trim($route)) ?? '', '/');
        if (! str_contains($route, '{slug}')) {
            $route = rtrim($route, '/').'/{slug}';
        }

        return $route;
    }

    /**
     * The listing page's heading and intro in a language, falling back
     * to the main language: ['title' => ?string, 'intro' => ?string].
     *
     * @return array{title: string|null, intro: string|null}
     */
    public function archiveText(?string $locale = null): array
    {
        $byLocale = static::archiveByLocale($this->archive_data);
        $locale ??= Locales::current();
        $own = $byLocale[$locale] ?? [];
        $main = $byLocale[Locales::main()] ?? [];

        return [
            'title' => ($own['title'] ?? null) ?: ($main['title'] ?? null),
            'intro' => ($own['intro'] ?? null) ?: ($main['intro'] ?? null),
        ];
    }

    /**
     * archive_data as {locale: {title, intro}}. Older data stored one
     * {title, intro} for all languages: it counts as the main language.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, array<string, string>>
     */
    public static function archiveByLocale(?array $data): array
    {
        $data ??= [];
        if (array_key_exists('title', $data) || array_key_exists('intro', $data)) {
            $legacy = array_filter(['title' => $data['title'] ?? null, 'intro' => $data['intro'] ?? null], 'is_string');
            $data = array_diff_key($data, ['title' => 1, 'intro' => 1]);
            $data[Locales::main()] = ($data[Locales::main()] ?? []) + $legacy;
        }

        return array_filter($data, 'is_array');
    }

    public function archiveSchema(): ?BlueprintSchema
    {
        $id = $this->setting('archive_blueprint_id');
        $blueprint = $id ? Blueprint::find($id) : null;

        return $blueprint?->schema();
    }
}
