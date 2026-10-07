<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Collection;
use Sunrice\Support\SafeUrl;

/**
 * Internal (entry) or external link. Stored:
 * {type: 'url'|'entry', url, entry_id, label, new_tab}.
 * Hydrates to {url, label, new_tab} — entry links resolve through the
 * UrlGenerator so they follow slug changes and the active locale, and
 * use the entry's title when no label is set.
 */
class Link extends FieldType
{
    public static function type(): string
    {
        return 'link';
    }

    public function translatableByDefault(): bool
    {
        return true;
    }

    public function rules(array $field): array
    {
        return ['array', function (string $attribute, mixed $value, \Closure $fail): void {
            $url = is_array($value) ? ($value['url'] ?? null) : null;
            if (is_string($url) && ! SafeUrl::isSafe($url)) {
                $fail('Use a web address (https://…), a path on this site (/about), mailto: or tel:.');
            }
        }];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $type = in_array($value['type'] ?? null, ['url', 'entry'], true) ? $value['type'] : 'url';

        return [
            'type' => $type,
            'url' => $type === 'url' ? ($value['url'] ?? null) : null,
            'entry_id' => $type === 'entry' ? ($value['entry_id'] ?? $value['entry'] ?? null) : null,
            'label' => $value['label'] ?? null,
            'new_tab' => (bool) ($value['new_tab'] ?? false),
        ];
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        // Saved before URLs were checked: never print an unsafe scheme.
        $url = is_string($value['url'] ?? null) ? SafeUrl::orNull($value['url']) : null;
        $label = ($value['label'] ?? null) ?: null;
        $entryId = $value['entry_id'] ?? $value['entry'] ?? null;
        if (($value['type'] ?? null) === 'entry' && $entryId) {
            $entry = $ctx->entry((int) $entryId);
            if ($entry !== null) {
                $entry->resolveFor($ctx->locale);
                $url = $entry->url;
                // No link text given: use the entry's title.
                $label ??= $entry->title;
            } else {
                $url = null;
            }
        }

        return [
            'url' => $url,
            'label' => $label,
            'new_tab' => (bool) ($value['new_tab'] ?? false),
        ];
    }

    public function references(mixed $value, array $field): array
    {
        $entryId = is_array($value) ? ($value['entry_id'] ?? $value['entry'] ?? null) : null;
        if (is_array($value) && ($value['type'] ?? null) === 'entry' && $entryId) {
            return [['target_type' => 'entry', 'target_id' => (int) $entryId]];
        }

        return [];
    }

    public function settingsSchema(): array
    {
        return [
            [
                'handle' => 'collections', 'type' => 'multiselect', 'label' => 'Entry links: collections (none = any)',
                'options' => Collection::query()->orderBy('title')->get(['handle', 'title'])
                    ->map(fn (Collection $c) => ['value' => $c->handle, 'label' => $c->title])->all(),
            ],
        ];
    }
}
