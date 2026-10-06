<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entry_translation_id
 * @property int|null $user_id
 * @property array<string,mixed> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
        /** @var class-string<Model> $model */
        $model = config('sunrice.auth.user_model');

        return $this->belongsTo($model, 'user_id');
    }
}
