<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;

/**
 * Site-wide text search over published entries that have pages of their
 * own: their title and field text in the visitor's language (or the main
 * language, which untranslated pages show). Used by the search page and
 * <x-sunrice::search>.
 */
class SiteSearch
{
    /**
     * @param  array<int, string>|null  $collections  handles; null = every collection with entry pages (sunrice.search.collections)
     * @return LengthAwarePaginator<int, Entry>
     */
    public function search(string $query, ?array $collections = null, ?int $perPage = null, string $pageName = 'page', ?string $locale = null): LengthAwarePaginator
    {
        $locale ??= Locales::current();
        $query = trim(mb_substr($query, 0, 200));
        $collectionIds = $this->collectionIds($collections ?? config('sunrice.search.collections'));
        $perPage ??= (int) config('sunrice.search.per_page', 10);

        $builder = Entry::query()->published()->whereIn('collection_id', $collectionIds);
        if ($query === '' || $collectionIds === []) {
            $builder->whereRaw('1 = 0');
        } else {
            $like = '%'.$query.'%';
            $data = $this->dataAsText($builder);
            $builder->whereHas('translations', fn (Builder $t) => $t
                // The main language, or this language once it's Ready (what visitors see).
                ->where(fn (Builder $q) => $q->where('locale', Locales::main())
                    ->orWhere(fn (Builder $q) => $q->where('locale', $locale)->where('is_ready', true)))
                ->where(fn (Builder $q) => $q->whereLike('title', $like)->orWhereRaw($data.' '.$this->likeOperator($builder).' ?', [$like])));
        }

        $results = $builder->with(['translations', 'collection'])
            ->orderByDesc('published_at')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();

        $ctx = new HydrationContext($locale, false);
        $results->getCollection()->each(function (Entry $entry) use ($locale, $ctx): void {
            $entry->resolveFor($locale);
            $entry->hydrationContext = $ctx;
        });

        return $results;
    }

    /** The search page's address in a language, e.g. /search or /en/search. */
    public static function url(?string $locale = null): string
    {
        $path = trim((string) config('sunrice.search.path', 'search'), '/');

        return url(Locales::prefix($locale ?? Locales::current()).'/'.$path);
    }

    /**
     * @param  array<int, string>|string|null  $handles
     * @return array<int, int>
     */
    protected function collectionIds(array|string|null $handles): array
    {
        $handles = is_string($handles) ? array_filter(array_map('trim', explode(',', $handles))) : $handles;

        return Collection::query()
            ->when($handles !== null && $handles !== [], fn ($q) => $q->whereIn('handle', $handles))
            ->get()
            ->filter(fn (Collection $c) => $c->hasSinglePages())
            ->map(fn (Collection $c) => (int) $c->id)
            ->values()
            ->all();
    }

    /** @param Builder<Entry> $builder */
    protected function dataAsText(Builder $builder): string
    {
        return match ($builder->getModel()->getConnection()->getDriverName()) {
            'pgsql' => '"data"::text',
            'mysql', 'mariadb' => 'CAST(`data` AS CHAR)',
            default => '"data"',
        };
    }

    /** @param Builder<Entry> $builder */
    protected function likeOperator(Builder $builder): string
    {
        return $builder->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }
}
