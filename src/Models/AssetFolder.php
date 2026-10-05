<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssetFolder extends Model
{
    protected $table = 'sunrice_asset_folders';

    protected $guarded = [];

    /** @return BelongsTo<AssetFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'parent_id');
    }

    /** @return HasMany<AssetFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(AssetFolder::class, 'parent_id');
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'folder_id');
    }
}
