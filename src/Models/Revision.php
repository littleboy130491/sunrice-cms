<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Revision extends Model
{
    const UPDATED_AT = null;

    protected $table = 'sunrice_revisions';

    protected $guarded = [];

    protected $casts = [
        'content' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<EntryTranslation, $this> */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(EntryTranslation::class, 'entry_translation_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('sunrice.auth.user_model'), 'user_id');
    }
}
