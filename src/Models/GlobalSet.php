<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named, blueprint-driven group of site-wide content ("global" is a
 * reserved word in PHP). Template parts are globals with group
 * 'template_part'.
 */
class GlobalSet extends Model
{
    use HasFactory;

    protected $table = 'sunrice_globals';

    protected $guarded = [];

    protected $casts = ['translatable' => 'boolean'];

    protected static function newFactory(): \Sunrice\Database\Factories\GlobalSetFactory
    {
        return \Sunrice\Database\Factories\GlobalSetFactory::new();
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** @return HasMany<GlobalValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(GlobalValue::class, 'global_id');
    }

    public function valueFor(?string $locale): ?GlobalValue
    {
        return $this->values->firstWhere('locale', $locale)
            ?? $this->values()->where('locale', $locale)->first();
    }
}
