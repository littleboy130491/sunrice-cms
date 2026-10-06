<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Setting;
use Sunrice\Models\Term;
use Sunrice\Support\Locales;

/**
 * Builds public URLs for entries, collection archives and terms.
 * Main-language URLs have no prefix; other languages use /{locale}.
 * A language URL uses the translated slug when that translation is
 * Ready, else the main-language slug.
 */
class UrlGenerator
{
    public function entry(Entry $entry, ?string $locale = null): string
    {
        $locale ??= Locales::current();
        $collection = $entry->collection;

        // Homepage entry renders at the site root.
        if ((int) Setting::get('homepage_entry_id') === (int) $entry->id) {
            return Locales::prefix($locale) ?: '/';
        }

        $route = $collection->entryRoute();
        $slug = $this->entrySlug($entry, $locale);

        return Locales::prefix($locale).str_replace('{slug}', $slug, $route);
    }

    public function entrySlug(Entry $entry, string $locale): string
    {
        if (! Locales::isMain($locale)) {
            $translation = $entry->translation($locale);
            if ($translation?->is_ready) {
                return $translation->slug;
            }
        }

        $main = $entry->mainTranslation();

        return $main === null ? '' : $main->slug;
    }

    public function archive(Collection $collection, ?string $locale = null): string
    {
        $locale ??= Locales::current();

        return Locales::prefix($locale).$collection->setting('archive_route', '/'.$collection->handle);
    }

    /**
     * Term archive URL; with a collection, the archive listing that
     * collection's entries (when the taxonomy has per-collection pages).
     */
    public function term(Term $term, ?string $locale = null, ?Collection $collection = null): string
    {
        $locale ??= Locales::current();
        $route = $term->taxonomy->termRoute($collection);

        $main = $term->mainTranslation();
        $slug = $main === null ? '' : $main->slug;
        if (! Locales::isMain($locale)) {
            $translation = $term->translation($locale);
            if ($translation !== null) {
                $slug = $translation->slug;
            }
        }

        return Locales::prefix($locale).str_replace('{slug}', $slug, $route);
    }

    /**
     * Locale => URL map for the current page (language switchers): an
     * entry, a term (with the collection of a per-collection term page),
     * or, with neither, the current frontend path (archives) in each
     * language.
     *
     * @return array<string, string>
     */
    public function localeUrls(Entry|Term|null $page = null, ?Collection $collection = null): array
    {
        $urls = [];

        if ($page instanceof Entry) {
            foreach (Locales::available() as $locale) {
                $urls[$locale] = $this->entry($page, $locale);
            }

            return $urls;
        }

        if ($page instanceof Term) {
            foreach (Locales::available() as $locale) {
                $urls[$locale] = $this->term($page, $locale, $collection);
            }

            return $urls;
        }

        $request = request();
        if (! $request->attributes->has('sunrice.path')) {
            return [];
        }
        $path = trim((string) $request->attributes->get('sunrice.path'), '/');
        foreach (Locales::available() as $locale) {
            $url = Locales::prefix($locale).($path === '' ? '' : '/'.$path);
            $urls[$locale] = $url === '' ? '/' : $url;
        }

        return $urls;
    }
}
