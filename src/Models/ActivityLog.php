<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One line of the activity log: a user (or the system) created, changed
 * or deleted something. Written by Sunrice\Activity\ActivityLogger.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string $action
 * @property string $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
class ActivityLog extends Model
{
    protected $table = 'sunrice_activity_log';

    protected $guarded = [];

    public const UPDATED_AT = null;

    protected $casts = ['properties' => 'array'];

    /** Delete entries older than this many days; how many were deleted. */
    public static function prune(int $days): int
    {
        $deleted = 0;
        $before = now()->subDays(max(0, $days));
        // In chunks, so a large log doesn't lock the table for long.
        do {
            $ids = static::query()->where('created_at', '<', $before)->orderBy('id')->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : static::query()->whereKey($ids)->delete();
        } while ($ids->count() === 1000);

        return $deleted;
    }
}
