<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\GlobalSetFactory;
use Sunrice\Support\Locales;

/**
 * Named, blueprint-driven group of site-wide content ("global" is a
 * reserved word in PHP). Template parts are globals with group
 * 'template_part'.
 *
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property int|null $blueprint_id
 * @property string $group
 * @property bool $translatable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<GlobalSetFactory>
 */
class GlobalSet extends Model
{
    /** @use HasFactory<GlobalSetFactory> */
    use HasFactory, \Sunrice\References\HasReferences;

    protected $table = 'sunrice_globals';

    protected $guarded = [];

    protected $casts = ['translatable' => 'boolean'];

    protected static function newFactory(): GlobalSetFactory
    {
        return GlobalSetFactory::new();
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
        $locale = $this->translatable ? ($locale ?? Locales::main()) : null;

        return $this->values->firstWhere('locale', $locale)
            ?? $this->values()->where('locale', $locale)->first();
    }

    /**
     * Persist data for one locale (or the shared row when not
     * translatable) and return the row.
     */
    /** @param array<string, mixed> $data */
    public function setValuesFor(?string $locale, array $data): GlobalValue
    {
        $locale = $this->translatable ? ($locale ?? Locales::main()) : null;

        $row = $this->values()->firstOrNew(['locale' => $locale]);
        $row->data = $data;
        $row->save();

        return $row;
    }

    /**
     * Resolved values for a locale, falling back to the main locale
     * (whole-set fallback, never field mixing).
     */
    /** @return array<string, mixed> */
    public function valuesFor(string $locale): array
    {
        $row = $this->valueFor($locale) ?? $this->valueFor(Locales::main());

        return $row === null ? [] : $row->data;
    }
}
