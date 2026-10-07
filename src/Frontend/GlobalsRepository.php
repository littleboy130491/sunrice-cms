<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Sunrice\Cache\ContentCache;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Blueprint;
use Sunrice\Models\GlobalSet;
use Sunrice\Support\Locales;

/**
 * Resolves global set values for templates. Non-translatable sets
 * read their shared row; translatable sets read the locale row and
 * fall back to the main-locale row as a whole (never field mixing).
 */
class GlobalsRepository
{
    /** @var array<string, GlobalData> hydrated sets for this request */
    protected array $resolved = [];

    public function get(string $handle, ?string $locale = null): GlobalData
    {
        $locale ??= Locales::current();
        $stored = ContentCache::remember('global:'.$handle, fn () => $this->stored($handle, $locale), $locale);
        // Hydrating can query (entries, assets…): once per request for the same values.
        $key = $handle.':'.md5((string) json_encode($stored));

        return $this->resolved[$key] ??= $this->hydrate($handle, $stored);
    }

    /**
     * The stored values (plain arrays, so the cache never holds objects:
     * sites may refuse to unserialize them).
     *
     * @return array{locale: string, values: array<string, mixed>, blueprint: int|null}|null
     */
    protected function stored(string $handle, string $locale): ?array
    {
        $set = GlobalSet::query()
            ->where('handle', $handle)
            ->with('values')
            ->first();

        if ($set === null) {
            return null;
        }

        $resolvedLocale = $set->translatable ? $locale : Locales::main();

        return [
            'locale' => $resolvedLocale,
            'values' => $set->valuesFor($resolvedLocale),
            'blueprint' => $set->blueprint_id,
        ];
    }

    /** @param array{locale: string, values: array<string, mixed>, blueprint: int|null}|null $stored */
    protected function hydrate(string $handle, ?array $stored): GlobalData
    {
        if ($stored === null) {
            return new GlobalData($handle, Locales::current());
        }

        $values = $stored['values'];
        $blueprint = $stored['blueprint'] === null ? null : Blueprint::query()->find($stored['blueprint']);
        if ($blueprint !== null) {
            $values = $blueprint->schema()->hydrate($values, new HydrationContext($stored['locale']));
        }

        return new GlobalData($handle, $stored['locale'], $values);
    }
}
