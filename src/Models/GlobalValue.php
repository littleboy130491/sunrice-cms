<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $global_id
 * @property string|null $locale
 * @property array<string,mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GlobalValue extends Model
{
    protected $table = 'sunrice_global_values';

    protected $guarded = [];

    protected $attributes = ['data' => '{}'];

    protected $casts = ['data' => 'array'];

    /** @return BelongsTo<GlobalSet, $this> */
    public function globalSet(): BelongsTo
    {
        return $this->belongsTo(GlobalSet::class, 'global_id');
    }
}
