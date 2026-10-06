<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $old_path
 * @property string $locale
 * @property int $entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Redirect extends Model
{
    protected $table = 'sunrice_redirects';

    protected $guarded = [];

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
