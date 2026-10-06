<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Support\Facades\Cache;
use Sunrice\Cache\ContentCache;
use Sunrice\Cache\ContentVersion;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

/**
 * Compiles every collection's `route` / `archive_route` and every
 * taxonomy's `route` setting into regexes (memoized per request) and
 * matches a stripped frontend path against them.
 */
class RouteMatcher
{
    /** @var array<int, array{regex: string, match: RouteMatch}>|null */
    protected static ?array $compiled = null;

    /**
     * RouteMatch a locale-stripped path (e.g. 'blog/hello') to a route.
     */
    public function match(string $path): ?RouteMatch
    {
        $path = '/'.trim($path, '/');

        foreach ($this->compiled() as ['regex' => $regex, 'match' => $match]) {
            if (preg_match($regex, $path, $m)) {
                return new RouteMatch(
                    type: $match->type,
                    collection: $match->collection,
                    taxonomy: $match->taxonomy,
                    slug: $m['slug'] ?? $match->slug,
                );
            }
        }

        return null;
    }

    /** Forget the per-request memoization (mainly for tests/long runs). */
    public static function flush(): void
    {
        static::$compiled = null;
        Cache::store(config('sunrice.cache.store'))->forget(
            'sunrice:'.ContentVersion::current().':shared:routematcher'
        );
    }

    /** @return array<int, array{regex: string, match: RouteMatch}> */
    protected function compiled(): array
    {
        return static::$compiled ??= $this->compile();
    }

    /** @return array<int, array{regex: string, match: RouteMatch}> */
    protected function compile(): array
    {
        return ContentCache::remember('routematcher', fn () => $this->buildRoutes(), 'shared');
    }

    /** @return array<int, array{regex: string, match: RouteMatch}> */
    protected function buildRoutes(): array
    {
        $routes = [];

        Collection::query()->get()->each(function (Collection $collection) use (&$routes): void {
            if ($collection->setting('has_single', true)) {
                $route = $collection->entryRoute();
                $routes[] = $this->route($route, new RouteMatch('entry', collection: $collection));
            }
            if ($collection->setting('has_archive')) {
                $route = (string) $collection->setting('archive_route', '/'.$collection->handle);
                $routes[] = $this->route($route, new RouteMatch('archive', collection: $collection));
            }
        });

        Taxonomy::query()->get()->each(function (Taxonomy $taxonomy) use (&$routes): void {
            if ($taxonomy->setting('has_archive')) {
                $route = Collection::normalizeRoute($taxonomy->setting('route')) ?? '/'.$taxonomy->handle.'/{slug}';
                $routes[] = $this->route($route, new RouteMatch('term', taxonomy: $taxonomy));
            }
        });

        return $routes;
    }

    /** @return array{regex: string, match: RouteMatch} */
    protected function route(string $pattern, RouteMatch $match): array
    {
        $pattern = '/'.trim($pattern, '/');
        $regex = '#^'.preg_quote($pattern, '#').'$#';
        $regex = str_replace(preg_quote('{slug}', '#'), '(?<slug>[^/]+)', $regex);

        return ['regex' => $regex, 'match' => $match];
    }
}
