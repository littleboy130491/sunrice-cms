<?php

declare(strict_types=1);

namespace Sunrice\Locks;

use Illuminate\Validation\ValidationException;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\GlobalValue;
use Sunrice\Models\Term;

/**
 * A fingerprint of what an editor loaded. Saving sends it back; if the
 * stored content changed meanwhile (someone else saved, e.g. after an
 * edit lock expired), the save is refused instead of overwriting it,
 * unless the editor chose to overwrite.
 */
class Versions
{
    public static function entry(?EntryTranslation $translation): string
    {
        if ($translation === null) {
            return '';
        }

        return static::hash($translation->draft ?? [
            'title' => $translation->title,
            'slug' => $translation->slug,
            'data' => $translation->data ?? [],
            'seo' => $translation->seo ?? [],
        ]);
    }

    public static function term(Term $term): string
    {
        $term->loadMissing('translations');

        return static::hash([
            'parent' => $term->parent_id,
            'template' => $term->template,
            'translations' => $term->translations->sortBy('locale')->map(fn ($t) => [$t->locale, $t->name, $t->slug, $t->data, $t->seo])->values()->all(),
        ]);
    }

    public static function global(?GlobalValue $row): string
    {
        return $row === null ? '' : static::hash($row->data ?? []);
    }

    /**
     * @throws ValidationException when the content changed since $loaded
     */
    public static function ensureUnchanged(mixed $loaded, string $current, bool $overwrite): void
    {
        if ($overwrite || ! is_string($loaded) || $loaded === '' || $loaded === $current) {
            return;
        }

        throw ValidationException::withMessages([
            'version' => 'Someone else saved changes here since you opened it. Reload to see them, or overwrite them with yours.',
        ]);
    }

    protected static function hash(mixed $content): string
    {
        return md5((string) json_encode($content));
    }
}
