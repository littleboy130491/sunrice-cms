<?php

declare(strict_types=1);

namespace Sunrice\Mcp;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * The site's Blade templates for AI agents: where they live, which one
 * each page uses, and safe reading and writing.
 */
class Templates
{
    /** Where the site's templates live (view names "sunrice.…"). */
    public static function appDirectory(): string
    {
        return resource_path('views/sunrice');
    }

    /** Theme files (CSS, JS, SVG…) agents may write, served from /sunrice-theme. */
    public static function themeDirectory(): string
    {
        return public_path('sunrice-theme');
    }

    public static function starterDirectory(): string
    {
        return dirname(__DIR__, 2).'/stubs/templates';
    }

    public static function defaultsDirectory(): string
    {
        return dirname(__DIR__, 2).'/resources/views/defaults';
    }

    /**
     * Files in a directory, relative paths.
     *
     * @return array<int, array{path: string, size: int, modified: string}>
     */
    public static function files(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }
        $files = [];
        foreach ((new Finder)->files()->in($directory)->sortByName() as $file) {
            $files[] = [
                'path' => str_replace('\\', '/', $file->getRelativePathname()),
                'size' => (int) $file->getSize(),
                'modified' => date(DATE_ATOM, (int) $file->getMTime()),
            ];
        }

        return $files;
    }

    /**
     * A safe absolute path under $root for a relative path, or an error.
     *
     * @param  array<int, string>  $extensions
     */
    public static function resolve(string $root, string $relative, array $extensions): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $pattern = '#^([a-z0-9][a-z0-9_-]*/)*[a-z0-9][a-z0-9_.-]*$#i';
        if ($relative === '' || preg_match($pattern, $relative) !== 1 || str_contains($relative, '..')) {
            throw new InvalidArgumentException('Use a relative path of letters, numbers, "-", "_" and "/", e.g. "pages/about.blade.php".');
        }
        $ok = false;
        foreach ($extensions as $extension) {
            $ok = $ok || str_ends_with(strtolower($relative), $extension);
        }
        if (! $ok) {
            throw new InvalidArgumentException('Allowed file types here: '.implode(', ', $extensions).'.');
        }

        return rtrim($root, '/').'/'.$relative;
    }

    /** View name of an app template path: "pages/about.blade.php" → "sunrice.pages.about". */
    public static function viewName(string $relative): string
    {
        return 'sunrice.'.str_replace('/', '.', preg_replace('/\.blade\.php$/', '', $relative) ?? $relative);
    }

    /**
     * Compiles Blade and checks the PHP it produces; null when fine.
     */
    public static function syntaxError(string $blade): ?string
    {
        try {
            $php = Blade::compileString($blade);
            token_get_all($php, TOKEN_PARSE);
        } catch (Throwable $e) {
            return $e->getMessage().' (line '.$e->getLine().' of the compiled template)';
        }

        return null;
    }

    /**
     * Which view each page type uses now, in order of priority: the first
     * that exists wins.
     *
     * @return array<string, mixed>
     */
    public static function map(): array
    {
        $chain = fn (array $candidates) => array_map(fn (string $view) => [
            'view' => $view,
            'exists' => View::exists($view),
        ], array_values(array_filter($candidates)));
        $used = fn (array $candidates) => collect($candidates)->first(fn (string $view) => View::exists($view)) ?? 'sunrice::defaults.show';

        $collections = [];
        foreach (Collection::query()->orderBy('title')->get() as $c) {
            $entry = [$c->setting('template'), "sunrice.{$c->handle}.show", 'sunrice.show', 'sunrice::defaults.show'];
            $archive = [$c->setting('archive_template'), "sunrice.{$c->handle}.index", 'sunrice.index', 'sunrice::defaults.index'];
            $collections[$c->handle] = array_filter([
                'entry_page' => $c->hasSinglePages() ? [
                    'note' => 'An entry\'s own template (entry Template setting) comes before this list.',
                    'candidates' => $chain(array_filter($entry)),
                    'uses' => $used(array_filter($entry)),
                ] : null,
                'listing_page' => $c->setting('has_archive') ? [
                    'url' => $c->setting('archive_route', '/'.$c->handle),
                    'candidates' => $chain(array_filter($archive)),
                    'uses' => $used(array_filter($archive)),
                ] : null,
            ]);
        }

        $taxonomies = [];
        foreach (Taxonomy::query()->orderBy('title')->get() as $t) {
            if (! $t->setting('has_archive')) {
                continue;
            }
            $term = [$t->setting('template'), "sunrice.taxonomies.{$t->handle}.show", 'sunrice.taxonomies.show', 'sunrice::defaults.term'];
            $taxonomies[$t->handle] = [
                'note' => 'A term\'s own template (term editor) comes before this list.',
                'candidates' => $chain(array_filter($term)),
                'uses' => $used(array_filter($term)),
            ];
        }

        return ['collections' => $collections, 'taxonomies' => $taxonomies];
    }
}
