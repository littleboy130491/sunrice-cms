<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\View\Component;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;

/**
 * `<x-sunrice::seo :entry="$entry" />` — title, description, robots,
 * canonical, Open Graph, Twitter card and hreflang alternates. Fallback
 * pages get a canonical pointing at the main-language URL and no hreflang
 * output. All URLs are absolute, as search engines require.
 */
class Seo extends Component
{
    public ?Entry $entry;

    public string $title;

    public ?string $description;

    public string $canonical;

    public ?string $image;

    public string $locale;

    public bool $isFallback;

    /** @var array<string, string> */
    public array $alternates;

    /** `noindex, follow` when the page should stay out of search results, else null. */
    public ?string $robots;

    public string $siteName;

    public ?string $twitterSite;

    public function __construct(?Entry $entry = null, ?string $title = null, ?string $description = null, ?bool $noindex = null)
    {
        $this->entry = $entry;
        $seo = $entry === null ? [] : ($entry->seo ?? []);
        $this->locale = $entry === null ? Locales::current() : ($entry->resolvedLocale ?? Locales::current());
        $this->isFallback = $entry !== null && (bool) $entry->isFallback;

        $this->title = $title ?? $seo['title'] ?? ($entry === null ? null : $entry->title) ?? (string) config('app.name');
        $this->description = $description ?? $seo['description'] ?? null;
        $this->image = $this->image($seo);
        $this->canonical = $this->canonical($seo);
        $this->alternates = $this->alternates();
        $this->robots = $this->robots($seo, $noindex);
        $this->siteName = (string) config('app.name');
        $this->twitterSite = config('sunrice.seo.twitter_site') ?: null;
    }

    /** @param array<string, mixed> $seo */
    protected function robots(array $seo, ?bool $noindex): ?string
    {
        // The site-wide switch (e.g. on staging) wins over per-page settings.
        $hidden = (bool) config('sunrice.seo.noindex', false)
            || ($noindex ?? (bool) ($seo['noindex'] ?? false));

        return $hidden ? 'noindex, follow' : null;
    }

    /** @param array<string, mixed> $seo */
    protected function canonical(array $seo): string
    {
        $urls = app(UrlGenerator::class);

        // Fallback pages canonicalize to the main-language URL.
        if ($this->isFallback && $this->entry !== null) {
            return url($urls->entry($this->entry, Locales::main()));
        }

        if (! empty($seo['canonical'])) {
            return url((string) $seo['canonical']);
        }

        if ($this->entry !== null) {
            return url($urls->entry($this->entry, $this->locale));
        }

        return url()->current();
    }

    /** @return array<string, string> */
    protected function alternates(): array
    {
        // No hreflang on fallback pages.
        if ($this->entry === null || $this->isFallback) {
            return [];
        }

        $urls = app(UrlGenerator::class);
        $alternates = [];

        foreach (Locales::available() as $locale) {
            if (! Locales::isMain($locale)) {
                $translation = $this->entry->translation($locale);
                if (! $translation?->is_ready) {
                    continue;
                }
            }
            $alternates[$locale] = url($urls->entry($this->entry, $locale));
        }

        $alternates['x-default'] = url($urls->entry($this->entry, Locales::main()));

        return $alternates;
    }

    /** @param array<string, mixed> $seo */
    protected function image(array $seo): ?string
    {
        $id = $seo['image'] ?? null;
        if (! is_numeric($id)) {
            return null;
        }

        $asset = Asset::query()->find((int) $id);

        return $asset === null ? null : url($asset->url('large'));
    }

    public function render(): string
    {
        return 'sunrice::components.seo';
    }
}
