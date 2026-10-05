<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalValue extends Model
{
    protected $table = 'sunrice_global_values';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    /** @return BelongsTo<GlobalSet, $this> */
    public function globalSet(): BelongsTo
    {
        return $this->belongsTo(GlobalSet::class, 'global_id');
    }
}
