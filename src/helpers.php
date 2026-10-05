<?php

declare(strict_types=1);

// Helpers are added by later tasks, each wrapped in function_exists guards.

if (! function_exists('sunrice_entries')) {
    /**
     * Fluent public query over a collection's published entries.
     */
    function sunrice_entries(string $collection): \Sunrice\Query\EntryQuery
    {
        return \Sunrice\Facades\Sunrice::entries($collection);
    }
}

if (! function_exists('sunrice_menu')) {
    /**
     * Build a navigation menu by handle for the active (or given) locale.
     *
     * @return \Illuminate\Support\Collection<int, \Sunrice\Frontend\MenuNode>
     */
    function sunrice_menu(string $handle, ?string $locale = null): \Illuminate\Support\Collection
    {
        return \Sunrice\Facades\Sunrice::menu($handle, $locale);
    }
}

if (! function_exists('sunrice_global')) {
    /**
     * Fetch a global set's hydrated values by handle.
     */
    function sunrice_global(string $handle, ?string $locale = null): mixed
    {
        return \Sunrice\Facades\Sunrice::global($handle, $locale);
    }
}

if (! function_exists('sunrice_locale_urls')) {
    /**
     * Locale => URL map for an entry, used by language switchers.
     *
     * @return array<string, string>
     */
    function sunrice_locale_urls(?\Sunrice\Models\Entry $entry): array
    {
        return app(\Sunrice\Frontend\UrlGenerator::class)->localeUrls($entry);
    }
}
