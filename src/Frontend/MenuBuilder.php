<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Support\Collection;
use Sunrice\Cache\ContentCache;
use Sunrice\Models\Collection as ContentCollection;
use Sunrice\Models\Entry;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

/**
 * Resolves a menu's items into a tree of MenuNodes for the active
 * (or given) locale. Item targets are resolved through UrlGenerator
 * so links always follow slug changes and the rendered locale.
 * Items whose target is unpublished or trashed are skipped.
 */
class MenuBuilder
{
    public function __construct(protected UrlGenerator $urls) {}

    /**
     * @return Collection<int, MenuNode>
     */
    public function build(string $handle, ?string $locale = null): Collection
    {
        return ContentCache::remember('menu:'.$handle, fn () => $this->buildMenu($handle, $locale), $locale);
    }

    /** @return Collection<int, MenuNode> */
    protected function buildMenu(string $handle, ?string $locale): Collection
    {
        $locale ??= Locales::current();
        $menu = Menu::query()
            ->where('handle', $handle)
            ->with('items')
            ->first();

        if ($menu === null) {
            return collect();
        }

        $targets = $this->resolveTargets($menu->items->all(), $locale);

        return $this->tree($menu->items->all(), $targets, $locale);
    }

    /**
     * Eager-load translated titles/urls per item id. Unpublished or
     * trashed targets map to null and are skipped.
     *
     * @param  array<int, MenuItem>  $items
     * @return array<int, array{title: ?string, url: ?string}|null>
     */
    protected function resolveTargets(array $items, string $locale): array
    {
        $entryIds = [];
        $collectionIds = [];
        $termIds = [];
        foreach ($items as $item) {
            match ($item->type) {
                'entry' => $item->target_id !== null && $entryIds[] = $item->target_id,
                'collection' => $item->target_id !== null && $collectionIds[] = $item->target_id,
                'term' => $item->target_id !== null && $termIds[] = $item->target_id,
                default => null,
            };
        }

        $entries = $entryIds === [] ? collect() : Entry::query()
            ->with('translations')
            ->whereIn('id', array_unique($entryIds))
            ->get()
            ->keyBy('id');
        $collections = $collectionIds === [] ? collect() : ContentCollection::query()
            ->whereIn('id', array_unique($collectionIds))
            ->get()
            ->keyBy('id');
        $terms = $termIds === [] ? collect() : Term::query()
            ->with(['translations', 'taxonomy'])
            ->whereIn('id', array_unique($termIds))
            ->get()
            ->keyBy('id');

        $resolved = [];
        foreach ($items as $item) {
            $resolved[$item->id] = match ($item->type) {
                'url' => ['title' => null, 'url' => (string) $item->url],
                'entry' => $this->entryTarget($entries->get($item->target_id), $locale),
                'collection' => $this->collectionTarget($collections->get($item->target_id), $locale),
                'term' => $this->termTarget($terms->get($item->target_id), $locale),
                default => null,
            };
        }

        return $resolved;
    }

    /** @return array{title: ?string, url: string}|null */
    protected function entryTarget(?Entry $entry, string $locale): ?array
    {
        if ($entry === null || $entry->trashed() || $entry->status !== 'published') {
            return null;
        }

        $entry->resolveFor($locale);

        return [
            'title' => $entry->renderedTranslation()?->title,
            'url' => $this->urls->entry($entry, $locale),
        ];
    }

    /** @return array{title: ?string, url: string}|null */
    protected function collectionTarget(?ContentCollection $collection, string $locale): ?array
    {
        if ($collection === null || ! $collection->setting('has_archive', false)) {
            return null;
        }

        return ['title' => $collection->title, 'url' => $this->urls->archive($collection, $locale)];
    }

    /** @return array{title: ?string, url: string}|null */
    protected function termTarget(?Term $term, string $locale): ?array
    {
        if ($term === null || $term->trashed()) {
            return null;
        }

        return [
            'title' => ($term->translation($locale) ?? $term->mainTranslation())?->name,
            'url' => $this->urls->term($term, $locale),
        ];
    }

    /**
     * @param  array<int, MenuItem>  $items
     * @param  array<int, array{title: ?string, url: ?string}|null>  $targets
     * @return Collection<int, MenuNode>
     */
    protected function tree(array $items, array $targets, string $locale, ?int $parentId = null): Collection
    {
        $currentPath = '/'.trim((string) request()->path(), '/');

        return collect($items)
            ->filter(fn (MenuItem $item) => $item->parent_id === $parentId)
            ->sortBy('sort_order')
            ->map(function (MenuItem $item) use ($items, $targets, $locale, $currentPath): ?MenuNode {
                $target = $targets[$item->id] ?? null;
                if ($target === null || $target['url'] === null) {
                    return null;
                }

                $label = $item->labels[$locale] ?? $item->labels[Locales::main()] ?? $target['title'] ?? '';
                $url = $target['url'];
                $isActive = $url !== '/' && str_starts_with($currentPath.'/', rtrim($url, '/').'/')
                    || $currentPath === $url;

                return new MenuNode(
                    label: $label,
                    url: $url,
                    newTab: (bool) $item->new_tab,
                    isActive: $isActive,
                    children: $this->tree($items, $targets, $locale, $item->id),
                );
            })
            ->filter()
            ->values();
    }
}
