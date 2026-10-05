<?php

declare(strict_types=1);

namespace Sunrice\Fields;

use Illuminate\Support\Collection;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;
use Sunrice\Models\Term;

/**
 * Per-request/per-render hydration state: the active locale, a memo for
 * batched asset/entry/term lookups (avoids N+1 inside repeated fields),
 * and the preview flag.
 */
class HydrationContext
{
    /** @var array<int, Asset|null> */
    protected array $assets = [];

    /** @var array<int, Entry|null> */
    protected array $entries = [];

    /** @var array<int, Term|null> */
    protected array $terms = [];

    public function __construct(
        public readonly string $locale,
        public readonly bool $preview = false,
    ) {}

    /**
     * Preload records referenced by a set of results (used by
     * EntryQuery::with('assets')).
     *
     * @param  array<int, int>  $ids
     */
    public function preloadAssets(array $ids): void
    {
        $missing = array_diff(array_unique($ids), array_keys($this->assets));
        if ($missing === []) {
            return;
        }

        $found = Asset::query()->whereIn('id', $missing)->get()->keyBy('id');
        foreach ($missing as $id) {
            $this->assets[$id] = $found->get($id);
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function preloadEntries(array $ids): void
    {
        $missing = array_diff(array_unique($ids), array_keys($this->entries));
        if ($missing === []) {
            return;
        }

        $found = Entry::query()->with('translations')->whereIn('id', $missing)->get()->keyBy('id');
        foreach ($missing as $id) {
            $this->entries[$id] = $found->get($id);
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function preloadTerms(array $ids): void
    {
        $missing = array_diff(array_unique($ids), array_keys($this->terms));
        if ($missing === []) {
            return;
        }

        $found = Term::query()->with('translations')->whereIn('id', $missing)->get()->keyBy('id');
        foreach ($missing as $id) {
            $this->terms[$id] = $found->get($id);
        }
    }

    public function asset(int $id): ?Asset
    {
        if (! array_key_exists($id, $this->assets)) {
            $this->assets[$id] = Asset::find($id);
        }

        return $this->assets[$id];
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Asset>
     */
    public function assetsByIds(array $ids): Collection
    {
        $this->preloadAssets($ids);

        return collect($ids)
            ->map(fn (int $id) => $this->assets[$id] ?? null)
            ->filter()
            ->values();
    }

    public function entry(int $id): ?Entry
    {
        if (! array_key_exists($id, $this->entries)) {
            $this->entries[$id] = Entry::with('translations')->find($id);
        }

        return $this->entries[$id];
    }

    /**
     * Entries resolved for the context locale (whole-entry fallback),
     * skipping unpublished entries unless previewing.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Entry>
     */
    public function entriesByIds(array $ids): Collection
    {
        $this->preloadEntries($ids);

        return collect($ids)
            ->map(fn (int $id) => $this->entries[$id] ?? null)
            ->filter()
            ->filter(fn (Entry $e) => $this->preview || ($e->status === 'published' && ($e->published_at === null || $e->published_at->lte(now()))))
            ->each(fn (Entry $e) => $e->resolveFor($this->locale))
            ->values();
    }

    public function term(int $id): ?Term
    {
        if (! array_key_exists($id, $this->terms)) {
            $this->terms[$id] = Term::with('translations')->find($id);
        }

        return $this->terms[$id];
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Term>
     */
    public function termsByIds(array $ids): Collection
    {
        $this->preloadTerms($ids);

        return collect($ids)
            ->map(fn (int $id) => $this->terms[$id] ?? null)
            ->filter()
            ->each(fn (Term $t) => $t->resolveFor($this->locale))
            ->values();
    }
}
