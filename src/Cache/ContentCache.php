<?php

declare(strict_types=1);

namespace Sunrice\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Sunrice\Support\Locales;

/**
 * Content/query cache. Keys are namespaced by the global content
 * version and the locale: sunrice:{version}:{locale}:{key}.
 * No-op when caching is disabled or in preview.
 */
class ContentCache
{
    public static function enabled(): bool
    {
        return (bool) config('sunrice.cache.enabled', true);
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public static function remember(string $key, Closure $callback, ?string $locale = null): mixed
    {
        if (! static::enabled()) {
            return $callback();
        }

        $locale ??= Locales::current();
        $fullKey = 'sunrice:'.ContentVersion::current().':'.$locale.':'.$key;

        $store = Cache::store(config('sunrice.cache.store'));

        /** @var mixed */
        return $store->remember($fullKey, (int) config('sunrice.cache.ttl', 3600), $callback);
    }

    /**
     * Cache bypassed for previews regardless of config.
     */
    public static function rememberUnlessPreview(string $key, Closure $callback, ?string $locale, bool $preview): mixed
    {
        return $preview ? $callback() : static::remember($key, $callback, $locale);
    }
}
