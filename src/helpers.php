<?php

declare(strict_types=1);
use Illuminate\Support\Collection;
use Sunrice\Facades\Sunrice;
use Sunrice\Frontend\MenuNode;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Entry;
use Sunrice\Query\EntryQuery;

// Helpers are added by later tasks, each wrapped in function_exists guards.

if (! function_exists('sunrice_entries')) {
    /**
     * Fluent public query over a collection's published entries.
     */
    function sunrice_entries(string $collection): EntryQuery
    {
        return Sunrice::entries($collection);
    }
}

if (! function_exists('sunrice_menu')) {
    /**
     * Build a navigation menu by handle for the active (or given) locale.
     *
     * @return Collection<int, MenuNode>
     */
    function sunrice_menu(string $handle, ?string $locale = null): Collection
    {
        return Sunrice::menu($handle, $locale);
    }
}

if (! function_exists('sunrice_global')) {
    /**
     * Fetch a global set's hydrated values by handle.
     */
    function sunrice_global(string $handle, ?string $locale = null): mixed
    {
        return Sunrice::global($handle, $locale);
    }
}

if (! function_exists('sunrice_locale_urls')) {
    /**
     * Locale => URL map for an entry, used by language switchers.
     *
     * @return array<string, string>
     */
    function sunrice_locale_urls(?Entry $entry): array
    {
        return app(UrlGenerator::class)->localeUrls($entry);
    }
}
