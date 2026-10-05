<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Sunrice\Fields\HydrationContext;
use Sunrice\Models\GlobalSet;
use Sunrice\Support\Locales;

/**
 * Resolves global set values for templates. Non-translatable sets
 * read their shared row; translatable sets read the locale row and
 * fall back to the main-locale row as a whole (never field mixing).
 */
class GlobalsRepository
{
    public function get(string $handle, ?string $locale = null): GlobalData
    {
        $locale ??= Locales::current();
        $set = GlobalSet::query()
            ->where('handle', $handle)
            ->with(['blueprint', 'values'])
            ->first();

        if ($set === null) {
            return new GlobalData($handle, $locale);
        }

        $resolvedLocale = $set->translatable ? $locale : Locales::main();
        $values = $set->translatable
            ? $set->valuesFor($locale)
            : ($set->valuesFor(Locales::main()));

        if ($set->blueprint !== null) {
            $values = $set->blueprint->schema()->hydrate($values, new HydrationContext($resolvedLocale));
        }

        return new GlobalData($handle, $resolvedLocale, $values);
    }
}
