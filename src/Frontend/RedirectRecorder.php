<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Sunrice\Models\Entry;
use Sunrice\Models\Redirect;
use Sunrice\Support\Locales;

/**
 * When a published translation's URL changes, records a redirect from
 * the old path to the entry so the old URL returns a 301.
 */
class RedirectRecorder
{
    /**
     * @param  array<string, string>  $oldPaths  locale => previous URL path
     */
    public function record(Entry $entry, array $oldPaths): void
    {
        foreach ($oldPaths as $locale => $oldPath) {
            if ($oldPath === '') {
                continue;
            }

            Redirect::query()->updateOrCreate(
                ['old_path' => $oldPath, 'locale' => $locale],
                ['entry_id' => $entry->id],
            );
        }

        // Avoid loops: drop redirects pointing at the entry's new URLs.
        $newPaths = collect(Locales::available())
            ->map(fn (string $locale) => app(UrlGenerator::class)->entry($entry, $locale))
            ->all();

        Redirect::query()
            ->where('entry_id', $entry->id)
            ->whereIn('old_path', $newPaths)
            ->delete();
    }
}
