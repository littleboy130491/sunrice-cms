<?php

declare(strict_types=1);

namespace Sunrice\Actions\Entries;

use Sunrice\Events\EntryPublished;
use Sunrice\Frontend\RedirectRecorder;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Revision;
use Sunrice\References\ReferenceSync;
use Sunrice\Support\Locales;

/**
 * Copies a translation's `draft` into its live columns, clears the
 * draft, stores a revision, syncs references and records redirects.
 * For the main locale it also publishes the entry itself (subject to
 * the optional scheduled publish date). For other locales it marks
 * the translation Ready.
 */
class PublishTranslation
{
    /**
     * @param  \DateTimeInterface|string|null  $publishedAt  scheduled date for the main locale
     */
    public function handle(EntryTranslation $translation, \DateTimeInterface|string|null $publishedAt = null): EntryTranslation
    {
        $entry = $translation->entry;
        $draft = $translation->draft ?? [
            'title' => $translation->title,
            'slug' => $translation->slug,
            'data' => $translation->data ?? [],
            'seo' => $translation->seo ?? [],
        ];

        // Record redirect paths before the slug/URL changes.
        $oldPaths = [];
        if ($translation->content_published_at !== null) {
            $entry->resolveFor($translation->locale);
            $oldPaths[$translation->locale] = app(UrlGenerator::class)->entry($entry, $translation->locale);
        }

        $translation->fill([
            'title' => $draft['title'] ?? $translation->title,
            'slug' => $draft['slug'] ?? $translation->slug,
            'data' => $draft['data'] ?? $translation->data ?? [],
            'seo' => $draft['seo'] ?? $translation->seo ?? [],
        ]);
        $translation->draft = null;
        $translation->content_published_at = now();

        if (Locales::isMain($translation->locale)) {
            $translation->is_ready = true;
            $entry->status = 'published';
            $entry->published_at = $publishedAt !== null
                ? \Illuminate\Support\Carbon::parse($publishedAt)
                : ($entry->published_at ?? now());
            $entry->save();
        } else {
            $translation->is_ready = true;
        }

        $translation->save();

        Revision::create([
            'entry_translation_id' => $translation->id,
            'user_id' => auth(config('sunrice.auth.guard'))->id(),
            'content' => [
                'title' => $translation->title,
                'slug' => $translation->slug,
                'data' => $translation->data,
                'seo' => $translation->seo,
            ],
        ]);

        $this->pruneRevisions($translation);
        $this->syncReferences($entry);

        if ($oldPaths !== []) {
            app(RedirectRecorder::class)->record($entry, $oldPaths);
        }

        EntryPublished::dispatch($entry);

        return $translation->refresh();
    }

    protected function pruneRevisions(EntryTranslation $translation): void
    {
        $keep = (int) config('sunrice.revisions.keep', 50);

        $ids = $translation->revisions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($keep);

        if ($ids->isNotEmpty()) {
            Revision::query()->whereIn('id', $ids)->delete();
        }
    }

    protected function syncReferences(Entry $entry): void
    {
        $schema = $entry->activeBlueprint()?->schema();

        foreach ($entry->translations()->get() as $translation) {
            $refs = $schema ? $schema->references($translation->data ?? []) : [];
            app(ReferenceSync::class)->sync($translation, $refs);
        }
    }
}
