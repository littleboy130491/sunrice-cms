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
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\EntryFactory;
use Sunrice\Fields\HydrationContext;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Support\Locales;

/**
 * @property-read EntryTranslation|null $resolved
 * @property int $id
 * @property int $collection_id
 * @property int|null $blueprint_id
 * @property int|null $author_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @use HasFactory<EntryFactory>
 */
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory, SoftDeletes, \Sunrice\References\HasReferences;

    protected $table = 'sunrice_entries';

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    /** The translation resolved for rendering (set by resolveFor). */
    public ?EntryTranslation $resolved = null;

    public ?string $resolvedLocale = null;

    public bool $isFallback = false;

    /**
     * Shared hydration context (preload caches) — set by EntryQuery
     * when 'assets'/'terms' are eager preloaded.
     */
    public ?HydrationContext $hydrationContext = null;

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** @return HasMany<EntryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(EntryTranslation::class);
    }

    /** @return BelongsToMany<Term, $this> */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'sunrice_entry_term');
    }

    /** @return BelongsTo<Model, $this> */
    public function author(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('sunrice.auth.user_model');

        return $this->belongsTo($model, 'author_id');
    }

    /**
     * The entry's blueprint override, or the collection default.
     */
    public function activeBlueprint(): ?Blueprint
    {
        return $this->blueprint ?? $this->collection?->blueprint;
    }

    public function translation(string $locale): ?EntryTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale);
    }

    public function mainTranslation(): ?EntryTranslation
    {
        return $this->translation(Locales::main());
    }

    /**
     * Whole-entry fallback: the Ready translation for $locale, otherwise
     * the main-language translation. Never mixes fields across locales.
     */
    public function resolveFor(string $locale): EntryTranslation
    {
        $main = $this->mainTranslation();
        $resolved = $main;

        if (! Locales::isMain($locale)) {
            $translation = $this->translation($locale);
            if ($translation?->is_ready) {
                $resolved = $translation;
            }
        }

        $this->resolved = $resolved ?? new EntryTranslation([
            'locale' => $locale,
            'title' => '',
            'slug' => '',
            'data' => [],
            'seo' => [],
        ]);
        $this->resolvedLocale = $locale;
        $this->isFallback = $resolved !== null && $resolved->locale !== $locale;

        return $this->resolved;
    }

    /**
     * Translation used for public rendering: resolved if set, else main.
     */
    public function renderedTranslation(): ?EntryTranslation
    {
        return $this->resolved ?? $this->mainTranslation();
    }

    // ---- Template-facing accessors ----------------------------------------

    public function getTitleAttribute(): ?string
    {
        return $this->renderedTranslation()?->title;
    }

    public function getSlugAttribute(): ?string
    {
        return $this->renderedTranslation()?->slug;
    }

    /** @return array<string, mixed> */
    public function getDataAttribute(): array
    {
        return $this->renderedTranslation() instanceof EntryTranslation
            ? $this->renderedTranslation()->data
            : [];
    }

    /** @return array<string, mixed> */
    public function getSeoAttribute(): array
    {
        return $this->renderedTranslation() instanceof EntryTranslation
            ? $this->renderedTranslation()->seo
            : [];
    }

    public function getUrlAttribute(): ?string
    {
        return app(UrlGenerator::class)->entry($this, $this->resolvedLocale);
    }

    public function getLocaleAttribute(): ?string
    {
        return $this->resolvedLocale;
    }

    /**
     * Hydrated custom-field value for templates.
     */
    public function get(string $handle, ?HydrationContext $ctx = null): mixed
    {
        $blueprint = $this->activeBlueprint();
        if ($blueprint === null) {
            return $this->data[$handle] ?? null;
        }

        $ctx ??= $this->hydrationContext ?? new HydrationContext($this->resolvedLocale ?? Locales::main());

        return $blueprint->schema()->hydrateField($handle, $this->data[$handle] ?? null, $ctx);
    }

    // ---- Scopes -----------------------------------------------------------

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    public function scopeInCollection(Builder $query, string $handle): Builder
    {
        return $query->whereHas('collection', fn (Builder $q) => $q->where('handle', $handle));
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '>', now());
    }
}
