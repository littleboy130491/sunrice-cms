<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Fields\BlueprintSchema;

class Blueprint extends Model
{
    use HasFactory;

    protected $table = 'sunrice_blueprints';

    protected $guarded = [];

    protected $casts = ['fields' => 'array'];

    protected static function newFactory(): \Sunrice\Database\Factories\BlueprintFactory
    {
        return \Sunrice\Database\Factories\BlueprintFactory::new();
    }

    public function schema(): BlueprintSchema
    {
        return BlueprintSchema::make($this->fields ?? []);
    }

    /** @return HasMany<Collection, $this> */
    public function collections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return HasMany<Taxonomy, $this> */
    public function taxonomies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Taxonomy::class);
    }

    /** @return HasMany<GlobalSet, $this> */
    public function globalSets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GlobalSet::class, 'blueprint_id');
    }
}
