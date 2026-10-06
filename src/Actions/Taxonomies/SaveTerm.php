<?php

declare(strict_types=1);

namespace Sunrice\Actions\Taxonomies;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Models\TermTranslation;
use Sunrice\Support\Locales;
use Sunrice\Support\SlugValidator;

class SaveTerm
{
    /**
     * @param  array{parent_id?: int|null, translations?: array<string, array{title?: string, name?: string, slug?: string, data?: array<string, mixed>}>}  $attributes
     */
    public function handle(Taxonomy $taxonomy, array $attributes, ?Term $term = null): Term
    {
        // The admin UI posts `title`; `name` is also accepted and maps to the
        // term_translations.name column.
        foreach ($attributes['translations'] ?? [] as $locale => $t) {
            if (! isset($t['title']) && isset($t['name'])) {
                $attributes['translations'][$locale]['title'] = $t['name'];
            }
        }

        // Languages left blank in the form are skipped rather than failing
        // validation, unless the term already has that translation.
        $existing = $term?->translations()->pluck('locale')->all() ?? [];
        foreach ($attributes['translations'] ?? [] as $locale => $t) {
            $blank = trim((string) ($t['title'] ?? '')) === '' && trim((string) ($t['slug'] ?? '')) === '';
            if ($blank && $locale !== Locales::main() && ! in_array($locale, $existing, true)) {
                unset($attributes['translations'][$locale]);
            }
        }

        $validated = validator($attributes, [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('sunrice_terms', 'id')->where('taxonomy_id', $taxonomy->id),
            ],
            'translations' => ['required', 'array'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['nullable', 'string', 'max:255'],
            'translations.*.data' => ['array'],
        ], [], ['translations.*.title' => 'title', 'translations.*.slug' => 'slug'])->after(function ($v) {
            foreach ($v->safe()?->toArray()['translations'] ?? [] as $locale => $t) {
                if (! Locales::isAvailable($locale)) {
                    $v->errors()->add("translations.{$locale}", 'Unknown locale.');
                }
            }
        })->validate();

        return DB::transaction(fn () => $this->persist($taxonomy, $validated, $term));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function persist(Taxonomy $taxonomy, array $validated, ?Term $term): Term
    {
        $term ??= new Term;
        $term->taxonomy_id = $taxonomy->id;
        $term->parent_id = Arr::get($validated, 'parent_id');
        if (! $term->exists) {
            $term->sort_order = (int) $taxonomy->terms()->max('sort_order') + 1;
        }
        $term->save();

        foreach ($validated['translations'] as $locale => $t) {
            $translation = TermTranslation::firstOrNew([
                'term_id' => $term->id,
                'locale' => $locale,
            ]);
            $translation->taxonomy_id = $taxonomy->id;
            $translation->name = $t['title'];
            $slug = $t['slug'] ?? null;
            $translation->slug = $slug !== null && $slug !== ''
                ? $slug
                : SlugValidator::uniqueForTerm(SlugValidator::fromTitle($t['title']), $taxonomy->id, $locale, $term->id);
            $translation->data = $t['data'] ?? $translation->data ?? [];

            if ($translation->slug !== $translation->getOriginal('slug')
                && ! SlugValidator::isUniqueForTerm($translation->slug, $taxonomy->id, $locale, $term->id)) {
                throw ValidationException::withMessages([
                    "translations.{$locale}.slug" => 'This slug is already taken.',
                ]);
            }

            $translation->save();
        }

        ContentChanged::dispatch('term_saved');

        return $term->refresh();
    }
}
