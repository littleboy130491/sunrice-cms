<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Sunrice\Database\Factories\TermFactory;
use Sunrice\Fields\HydrationContext;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Collection as ContentCollection;
use Sunrice\Support\Locales;

/**
 * @property int $id
 * @property int $taxonomy_id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @use HasFactory<TermFactory>
 */
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use HasFactory, SoftDeletes, \Sunrice\References\HasReferences;

    protected $table = 'sunrice_terms';

    protected $guarded = [];

    /** The translation resolved for rendering (set by resolveFor). */
    public ?TermTranslation $resolved = null;

    public ?string $resolvedLocale = null;

    public bool $isFallback = false;

    protected static function newFactory(): TermFactory
    {
        return TermFactory::new();
    }

    /** @return BelongsTo<Taxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    /** @return HasMany<Term, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Term::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<TermTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(TermTranslation::class);
    }

    /** @return BelongsToMany<Entry, $this> */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'sunrice_entry_term');
    }

    public function translation(string $locale): ?TermTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale);
    }

    public function mainTranslation(): ?TermTranslation
    {
        return $this->translation(Locales::main());
    }

    /**
     * Whole-term fallback mirroring Entry::resolveFor.
     */
    public function resolveFor(string $locale): TermTranslation
    {
        $resolved = $this->mainTranslation();

        if (! Locales::isMain($locale)) {
            $translation = $this->translation($locale);
            if ($translation !== null) {
                $resolved = $translation;
            }
        }

        $this->resolved = $resolved ?? new TermTranslation([
            'locale' => $locale,
            'name' => '',
            'slug' => '',
            'data' => [],
        ]);
        $this->resolvedLocale = $locale;
        $this->isFallback = $resolved !== null && $resolved->locale !== $locale;

        return $this->resolved;
    }

    public function getNameAttribute(): ?string
    {
        $t = $this->resolved ?? $this->mainTranslation();

        return $t === null ? null : $t->name;
    }

    public function getSlugAttribute(): ?string
    {
        $t = $this->resolved ?? $this->mainTranslation();

        return $t === null ? null : $t->slug;
    }

    public function getUrlAttribute(): ?string
    {
        return app(UrlGenerator::class)->term($this, $this->resolvedLocale);
    }

    /**
     * This term's archive page for one collection's entries, e.g. on a
     * blog post: $term->urlIn($entry->collection) → /blog/category/news.
     */
    public function urlIn(ContentCollection $collection): string
    {
        return app(UrlGenerator::class)->term($this, $this->resolvedLocale, $collection);
    }

    /**
     * Hydrated value of a field from the taxonomy's blueprint, in the
     * resolved language (e.g. a category description or image).
     */
    public function get(string $handle): mixed
    {
        $data = ($this->resolved ?? $this->mainTranslation())->data ?? [];
        $blueprint = $this->taxonomy?->blueprint;

        if ($blueprint === null) {
            return $data[$handle] ?? null;
        }

        return $blueprint->schema()->hydrateField(
            $handle,
            $data[$handle] ?? null,
            new HydrationContext($this->resolvedLocale ?? Locales::main()),
        );
    }

    /**
     * Nested tree of terms for a taxonomy, ordered by sort_order.
     *
     * @return Collection<int, Term>
     */
    public static function tree(int $taxonomyId): Collection
    {
        $terms = static::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('sort_order')
            ->get();

        /** @var Collection<int, Term> $list */
        $list = $terms->values()->toBase();

        return static::buildTree($list);
    }

    /**
     * @param  Collection<int, Term>  $terms
     * @return Collection<int, Term>
     */
    public static function buildTree(Collection $terms, ?int $parentId = null): Collection
    {
        return $terms
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Term $term) use ($terms): Term {
                $term->setRelation('children', static::buildTree($terms, $term->id));

                return $term;
            });
    }

    /**
     * @return array<int, int> ids of this term and all descendants
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children()->get() as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
