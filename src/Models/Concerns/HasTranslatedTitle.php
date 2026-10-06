<?php

declare(strict_types=1);

namespace Sunrice\Models\Concerns;

use Sunrice\Support\Locales;

/**
 * A title per language for collections and taxonomies. The `title`
 * column is the main-language title; other languages are stored in
 * settings.titles ({locale: title}) and fall back to it.
 */
trait HasTranslatedTitle
{
    public function titleIn(?string $locale = null): string
    {
        $locale ??= Locales::current();
        $titles = (array) ($this->settings['titles'] ?? []);
        $own = $titles[$locale] ?? null;

        return is_string($own) && $own !== '' && ! Locales::isMain($locale) ? $own : (string) $this->title;
    }

    /**
     * Clean posted per-language titles: known non-main languages only,
     * blanks dropped (they fall back to the main title).
     *
     * @return array<string, string>
     */
    public static function cleanTitles(mixed $titles): array
    {
        $clean = [];
        foreach ((array) $titles as $locale => $title) {
            $locale = (string) $locale;
            if (Locales::isAvailable($locale) && ! Locales::isMain($locale) && is_string($title) && trim($title) !== '') {
                $clean[$locale] = trim($title);
            }
        }

        return $clean;
    }
}
