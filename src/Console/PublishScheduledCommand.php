<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Events\EntryPublished;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\Locales;

/**
 * sunrice:publish-scheduled — publishes entries whose scheduled
 * published_at has passed while the entry is still a draft.
 * Runs every minute (registered on the scheduler).
 */
class PublishScheduledCommand extends Command
{
    protected $signature = 'sunrice:publish-scheduled';

    protected $description = 'Publish entries whose scheduled publish time has passed';

    public function handle(PublishTranslation $publish): int
    {
        $count = 0;

        Entry::query()
            ->where('status', 'draft')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('translations')
            ->chunkById(100, function ($entries) use (&$count): void {
                foreach ($entries as $entry) {
                    $translation = $entry->translations->firstWhere('locale', Locales::main());
                    if ($translation === null) {
                        continue;
                    }
                    $entry->status = 'published';
                    $entry->save();
                    EntryPublished::dispatch($entry);
                    $count++;
                }
            });

        $this->info("Published {$count} scheduled entr(y/ies).");

        return self::SUCCESS;
    }
}
