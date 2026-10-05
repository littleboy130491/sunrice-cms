<?php

declare(strict_types=1);

namespace Sunrice\Cache;

use Illuminate\Support\Facades\Cache;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Single global version number included in every public cache key.
 * Publishing content or changing navigation, globals or assets bumps
 * it — works on every cache driver, no tags needed.
 */
class ContentVersion
{
    public const KEY = 'sunrice:content_version';

    public static function current(): int
    {
        $store = Cache::store(config('sunrice.cache.store'));

        $version = $store->get(static::KEY);
        if ($version === null) {
            $version = time();
            $store->forever(static::KEY, $version);
        }

        return (int) $version;
    }

    public static function bump(): void
    {
        $store = Cache::store(config('sunrice.cache.store'));
        $store->forever(static::KEY, time());

        // Optional full-page cache is fully cleared on every bump.
        if (config('sunrice.cache.full_page') && class_exists(ResponseCache::class)) {
            ResponseCache::clear();
        }
    }
}
