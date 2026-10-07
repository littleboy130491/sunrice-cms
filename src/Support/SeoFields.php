<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Sunrice\Fields\CustomField;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Models\Blueprint;

/**
 * Which blueprint fields fill a page's meta title, description and share
 * image when its own SEO fields are empty. Chosen per collection or
 * taxonomy (Settings → SEO, stored as settings.seo.{title,description,
 * image}_field: a field handle, or "none"); without a choice, a fitting
 * field is picked automatically.
 */
class SeoFields
{
    public const ROLES = ['title', 'description', 'image'];

    /** Field types that can serve each role. */
    public const TYPES = [
        'title' => ['text'],
        'description' => ['textarea', 'rich_text', 'text'],
        'image' => ['asset'],
    ];

    /** Handles tried first by automatic picking, in order. */
    protected const PREFERRED = [
        'title' => ['meta_title', 'seo_title', 'headline'],
        'description' => ['meta_description', 'seo_description', 'excerpt', 'summary', 'description', 'intro', 'lead', 'subtitle'],
        'image' => ['og_image', 'share_image', 'featured_image', 'image', 'cover', 'thumbnail', 'photo', 'hero_image'],
    ];

    /**
     * The field handle for each role, or null for none.
     *
     * @param  array<string, mixed>  $seoSettings  the collection's or taxonomy's settings.seo
     * @return array{title: string|null, description: string|null, image: string|null}
     */
    public static function resolve(?Blueprint $blueprint, array $seoSettings): array
    {
        $fields = static::candidates($blueprint);
        $out = ['title' => null, 'description' => null, 'image' => null];

        foreach (self::ROLES as $role) {
            $chosen = $seoSettings[$role.'_field'] ?? null;
            if ($chosen === 'none') {
                continue;
            }
            if (is_string($chosen) && $chosen !== '' && in_array($chosen, array_column($fields[$role], 'handle'), true)) {
                $out[$role] = $chosen;

                continue;
            }
            $out[$role] = static::guess($role, $fields[$role]);
        }

        return $out;
    }

    /**
     * Top-level fields that fit each role.
     *
     * @return array<string, array<int, array{handle: string, label: string, type: string}>>
     */
    public static function candidates(?Blueprint $blueprint): array
    {
        $out = ['title' => [], 'description' => [], 'image' => []];
        if ($blueprint === null) {
            return $out;
        }
        $registry = app(FieldRegistry::class);

        foreach ($blueprint->schema()->fields() as $field) {
            $handle = $field['handle'] ?? null;
            $type = (string) ($field['type'] ?? '');
            if (! is_string($handle) || ! $registry->has($type)) {
                continue;
            }
            $fieldType = $registry->get($type);
            $base = $fieldType instanceof CustomField ? $fieldType::baseType() : $type;
            // A multiple-asset field still gives its first image.
            foreach (self::TYPES as $role => $types) {
                if (in_array($base, $types, true)) {
                    $out[$role][] = ['handle' => $handle, 'label' => (string) (($field['label'] ?? null) ?: $handle), 'type' => $base];
                }
            }
        }

        return $out;
    }

    /**
     * A field named for the role, else (description, image) the first of
     * a fitting type. Titles aren't guessed: the entry's own title is the
     * fallback already. Plain text fields are only used by name, so a
     * "phone" field never becomes a description.
     *
     * @param  array<int, array{handle: string, label: string, type: string}>  $fields
     */
    protected static function guess(string $role, array $fields): ?string
    {
        $handles = array_column($fields, 'handle');
        foreach (self::PREFERRED[$role] as $preferred) {
            if (in_array($preferred, $handles, true)) {
                return $preferred;
            }
        }
        if ($role === 'title') {
            return null;
        }
        foreach ($fields as $field) {
            if ($field['type'] !== 'text') {
                return $field['handle'];
            }
        }

        return null;
    }

    /** A field value as plain text for a meta tag (tags stripped, one line, cut at $limit). */
    public static function plainText(mixed $value, int $limit = 160): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', $value)), ENT_QUOTES | ENT_HTML5);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
    }
}
