<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\BlueprintFactory;
use Sunrice\Fields\BlueprintSchema;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array<int,array<string,mixed>> $fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<BlueprintFactory>
 */
class Blueprint extends Model
{
    /** @use HasFactory<BlueprintFactory> */
    use HasFactory;

    protected $table = 'sunrice_blueprints';

    protected $guarded = [];

    protected $attributes = ['fields' => '[]'];

    protected $casts = ['fields' => 'array'];

    protected static function newFactory(): BlueprintFactory
    {
        return BlueprintFactory::new();
    }

    public function schema(): BlueprintSchema
    {
        return BlueprintSchema::make($this->fields ?? []);
    }

    /** @return HasMany<Collection, $this> */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return HasMany<Taxonomy, $this> */
    public function taxonomies(): HasMany
    {
        return $this->hasMany(Taxonomy::class);
    }

    /** @return HasMany<GlobalSet, $this> */
    public function globalSets(): HasMany
    {
        return $this->hasMany(GlobalSet::class, 'blueprint_id');
    }
}
