<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Unsaved changes kept when someone took over editing (see EditLocks).
 *
 * @property int $id
 * @property string $type entry | term | global
 * @property int $model_id
 * @property string $locale
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $taken_by
 * @property array<string, mixed> $content
 * @property Carbon|null $created_at
 */
class KeptEdit extends Model
{
    protected $table = 'sunrice_kept_edits';

    protected $guarded = [];

    protected $casts = ['content' => 'array'];
}
