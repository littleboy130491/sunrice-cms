<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Models\ActivityLog;

/**
 * sunrice:prune-activity — delete activity log entries older than a number
 * of days (default sunrice.activity.prune_days, 180). Schedule it to keep
 * the log small: $schedule->command('sunrice:prune-activity')->daily().
 */
class PruneActivityCommand extends Command
{
    protected $signature = 'sunrice:prune-activity
        {--days= : Delete entries older than this many days (default: sunrice.activity.prune_days, 180)}';

    protected $description = 'Delete old activity log entries';

    public function handle(ActivityLogger $logger): int
    {
        $days = $this->option('days') ?? config('sunrice.activity.prune_days', 180);
        if (! is_numeric($days) || (int) $days < 0) {
            $this->components->error('--days must be a number of days, 0 or more.');

            return self::FAILURE;
        }
        $days = (int) $days;

        $deleted = ActivityLog::prune($days);
        $logger->record('pruned', 'Activity log', ['days' => $days, 'deleted' => $deleted]);
        $this->components->info("Deleted {$deleted} activity log ".($deleted === 1 ? 'entry' : 'entries')." older than {$days} days.");

        return self::SUCCESS;
    }
}
