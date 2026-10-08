<?php

declare(strict_types=1);

namespace Sunrice;

use Illuminate\Support\Collection;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Frontend\GlobalsRepository;
use Sunrice\Frontend\MenuBuilder;
use Sunrice\Frontend\MenuNode;
use Sunrice\Query\EntryQuery;

/**
 * The Sunrice registration API. Developers use this facade from their
 * service providers to register custom fields, model resources and
 * template selection hooks, and from Blade/templates to fetch content.
 */
class Sunrice
{
    /** @var array<int, callable> */
    protected array $templateHooks = [];

    /** @var array<int, callable> */
    protected array $bodyClassHooks = [];

    /** @var array<string, class-string> */
    protected array $resources = [];

    /** @var array<int, string> */
    protected array $adminScripts = [];

    /** @var array<int, string> */
    protected array $adminStyles = [];

    public function version(): string
    {
        return '1.0.0';
    }

    public function fields(): FieldRegistry
    {
        return app(FieldRegistry::class);
    }

    /**
     * Register a custom field type. The class must extend
     * Sunrice\Fields\FieldType (built-in types) or
     * Sunrice\Fields\CustomField (developer compositions).
     *
     * @param  class-string  $class
     */
    public function registerField(string $class): void
    {
        $this->fields()->register($class);
    }

    /**
     * Load a JavaScript file in the admin, after its own bundle: the place
     * to register React components for custom field types with
     * `window.Sunrice.registerField()`. A path ("js/fields.js") is served
     * through asset(); full URLs are used as they are.
     */
    public function registerAdminScript(string $url): void
    {
        $this->adminScripts[] = $url;
    }

    /** Load a stylesheet in the admin (e.g. for custom field components). */
    public function registerAdminStyle(string $url): void
    {
        $this->adminStyles[] = $url;
    }

    /**
     * Admin scripts from config `sunrice.admin.scripts` and
     * registerAdminScript(), as URLs.
     *
     * @return array<int, string>
     */
    public function adminScripts(): array
    {
        return static::assetUrls([...(array) config('sunrice.admin.scripts', []), ...$this->adminScripts]);
    }

    /**
     * @return array<int, string>
     */
    public function adminStyles(): array
    {
        return static::assetUrls([...(array) config('sunrice.admin.styles', []), ...$this->adminStyles]);
    }

    /**
     * @param  array<mixed>  $paths
     * @return array<int, string>
     */
    protected static function assetUrls(array $paths): array
    {
        $urls = [];
        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }
            $urls[] = preg_match('#^(https?:)?//#i', $path) === 1 ? $path : asset(ltrim($path, '/'));
        }

        return array_values(array_unique($urls));
    }

    /**
     * Register an existing Eloquent model for CMS management.
     *
     * @param  class-string<Resources\Resource>  $class
     */
    public function registerResource(string $class): void
    {
        $this->resources[$class::key()] = $class;
    }

    /**
     * @return array<string, class-string>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    /**
     * @param  class-string|null  $key
     * @return class-string|null
     */
    public function resource(?string $key): ?string
    {
        return $key === null ? null : ($this->resources[$key] ?? null);
    }

    /**
     * Register a template selection hook. Hooks run in registration order
     * after normal template resolution; each receives
     * (string $view, TemplateContext $context) and returns a view name or null.
     */
    public function resolveTemplateUsing(callable $hook): void
    {
        $this->templateHooks[] = $hook;
    }

    /**
     * @return array<int, callable>
     */
    public function templateHooks(): array
    {
        return $this->templateHooks;
    }

    /**
     * Change the page's body classes. Hooks run in registration order;
     * each receives (array $classes, ?TemplateContext $page) and returns
     * the classes to use.
     */
    public function bodyClassUsing(callable $hook): void
    {
        $this->bodyClassHooks[] = $hook;
    }

    /**
     * @return array<int, callable>
     */
    public function bodyClassHooks(): array
    {
        return $this->bodyClassHooks;
    }

    /**
     * Fluent public query over a collection's published entries.
     */
    public function entries(string $collection): EntryQuery
    {
        return EntryQuery::collection($collection);
    }

    /**
     * Build a navigation menu for the active (or given) locale.
     *
     * @return Collection<int, MenuNode>
     */
    public function menu(string $handle, ?string $locale = null): Collection
    {
        return app(MenuBuilder::class)->build($handle, $locale);
    }

    /**
     * Fetch a global set's hydrated values for the active (or given) locale.
     */
    public function global(string $handle, ?string $locale = null): mixed
    {
        return app(GlobalsRepository::class)->get($handle, $locale);
    }
}
