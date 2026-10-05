<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Fields\BlueprintSchema;

/**
 * @property array<string, mixed> $settings
 * @property array<string, mixed>|null $archive_data
 *
 * settings keys: route, has_single, has_archive, archive_route,
 * translatable, template, archive_template, archive_blueprint_id,
 * default_sort ('manual'|'published_at_desc'|'title_asc'), per_page,
 * icon, table_columns (array of field handles).
 */
class Collection extends Model
{
    use HasFactory;

    protected $table = 'sunrice_collections';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'archive_data' => 'array',
    ];

    protected static function newFactory(): \Sunrice\Database\Factories\CollectionFactory
    {
        return \Sunrice\Database\Factories\CollectionFactory::new();
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

    public function archiveSchema(): ?BlueprintSchema
    {
        $id = $this->setting('archive_blueprint_id');
        $blueprint = $id ? Blueprint::find($id) : null;

        return $blueprint?->schema();
    }
}
