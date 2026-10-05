<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sunrice\Support\Locales;

class Term extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sunrice_terms';

    protected $guarded = [];

    /** The translation resolved for rendering (set by resolveFor). */
    public ?TermTranslation $resolved = null;

    public ?string $resolvedLocale = null;

    public bool $isFallback = false;

    protected static function newFactory(): \Sunrice\Database\Factories\TermFactory
    {
        return \Sunrice\Database\Factories\TermFactory::new();
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
        return $this->resolved?->name ?? $this->mainTranslation()?->name;
    }

    public function getSlugAttribute(): ?string
    {
        return $this->resolved?->slug ?? $this->mainTranslation()?->slug;
    }

    public function getUrlAttribute(): ?string
    {
        return app(\Sunrice\Frontend\UrlGenerator::class)->term($this, $this->resolvedLocale);
    }

    /**
     * Nested tree of terms for a taxonomy, ordered by sort_order.
     *
     * @return \Illuminate\Support\Collection<int, Term>
     */
    public static function tree(int $taxonomyId): \Illuminate\Support\Collection
    {
        $terms = static::query()
            ->where('taxonomy_id', $taxonomyId)
            ->orderBy('sort_order')
            ->get();

        return static::buildTree($terms);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Term>  $terms
     * @return \Illuminate\Support\Collection<int, Term>
     */
    public static function buildTree(\Illuminate\Support\Collection $terms, ?int $parentId = null): \Illuminate\Support\Collection
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
