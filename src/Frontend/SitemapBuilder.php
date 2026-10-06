<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Sunrice\Cache\ContentCache;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;
use Sunrice\Support\Locales;

/**
 * sitemap.xml: published entries of collections with `has_single`,
 * archive pages and term archive pages — each entry once per locale
 * where the URL is that locale's or the main fallback (fallback URLs
 * for non-Ready locales are excluded), with lastmod.
 */
class SitemapBuilder
{
    public function render(): string
    {
        return ContentCache::remember('sitemap', fn (): string => $this->build());
    }

    protected function build(): string
    {
        $sitemap = Sitemap::create();
        $urls = app(UrlGenerator::class);

        Collection::query()->with('entries.translations')->get()->each(function (Collection $collection) use ($sitemap, $urls): void {
            if ($collection->setting('has_archive')) {
                foreach (Locales::available() as $locale) {
                    $sitemap->add(Url::create(url($urls->archive($collection, $locale))));
                }
            }

            if (! $collection->setting('has_single', true)) {
                return;
            }

            $collection->entries()->published()->with('translations')->get()
                ->each(function ($entry) use ($sitemap, $urls): void {
                    foreach (Locales::available() as $locale) {
                        $resolved = $entry->translation($locale);
                        if (! Locales::isMain($locale) && ! $resolved?->is_ready) {
                            continue; // fallback URL — excluded from sitemap
                        }
                        if ((bool) ($resolved?->seo['noindex'] ?? false)) {
                            continue; // hidden from search engines
                        }
                        $sitemap->add(
                            Url::create(url($urls->entry($entry, $locale)))
                                ->setLastModificationDate($entry->updated_at ?? now())
                        );
                    }
                });
        });

        Taxonomy::query()->with('terms.translations')->get()->each(function (Taxonomy $taxonomy) use ($sitemap, $urls): void {
            if (! $taxonomy->setting('has_archive')) {
                return;
            }
            $taxonomy->terms()->with('translations')->get()->each(function ($term) use ($sitemap, $urls): void {
                foreach (Locales::available() as $locale) {
                    $resolved = $term->translation($locale);
                    if (! Locales::isMain($locale) && $resolved === null) {
                        continue;
                    }
                    $sitemap->add(
                        Url::create(url($urls->term($term, $locale)))
                            ->setLastModificationDate($term->updated_at ?? now())
                    );
                }
            });
        });

        return $sitemap->render();
    }
}
