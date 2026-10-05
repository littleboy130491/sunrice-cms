<?php

declare(strict_types=1);

namespace Sunrice;

use Sunrice\Fields\FieldRegistry;
use Sunrice\Frontend\GlobalsRepository;
use Sunrice\Frontend\MenuBuilder;
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

    /** @var array<string, class-string> */
    protected array $resources = [];

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
     * Register an existing Eloquent model for CMS management.
     *
     * @param  class-string<\Sunrice\Resources\Resource>  $class
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
     * @param  class-string|null  $class
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
     * Fluent public query over a collection's published entries.
     */
    public function entries(string $collection): EntryQuery
    {
        return EntryQuery::collection($collection);
    }

    /**
     * Build a navigation menu for the active (or given) locale.
     *
     * @return \Illuminate\Support\Collection<int, \Sunrice\Frontend\MenuNode>
     */
    public function menu(string $handle, ?string $locale = null): \Illuminate\Support\Collection
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
