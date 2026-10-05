<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\View\Component;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;

/**
 * `<x-sunrice::seo :entry="$entry" />` — title, description, canonical,
 * Open Graph and hreflang alternates. Fallback pages get a canonical
 * pointing at the main-language URL and no hreflang output.
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

    public function __construct(?Entry $entry = null, ?string $title = null, ?string $description = null)
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
    }

    /** @param array<string, mixed> $seo */
    protected function canonical(array $seo): string
    {
        $urls = app(UrlGenerator::class);

        // Fallback pages canonicalize to the main-language URL.
        if ($this->isFallback && $this->entry !== null) {
            return $urls->entry($this->entry, Locales::main());
        }

        if (! empty($seo['canonical'])) {
            return (string) $seo['canonical'];
        }

        if ($this->entry !== null) {
            return $urls->entry($this->entry, $this->locale);
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
            $alternates[$locale] = $urls->entry($this->entry, $locale);
        }

        $alternates['x-default'] = $urls->entry($this->entry, Locales::main());

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

        return $asset?->url('large');
    }

    public function render(): string
    {
        return 'sunrice::components.seo';
    }
}
