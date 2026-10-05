<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * Internal (entry) or external link. Stored:
 * {type: 'url'|'entry', url, entry_id, label, new_tab}.
 * Hydrates to {url, label, new_tab} — entry links resolve through the
 * UrlGenerator so they follow slug changes and the active locale.
 */
class Link extends FieldType
{
    public static function type(): string
    {
        return 'link';
    }

    public function rules(array $field): array
    {
        return ['array'];
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
            'entry_id' => $type === 'entry' ? ($value['entry_id'] ?? null) : null,
            'label' => $value['label'] ?? null,
            'new_tab' => (bool) ($value['new_tab'] ?? false),
        ];
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $url = $value['url'] ?? null;
        if (($value['type'] ?? null) === 'entry' && ($value['entry_id'] ?? null)) {
            $entry = $ctx->entry((int) $value['entry_id']);
            if ($entry !== null) {
                $entry->resolveFor($ctx->locale);
                $url = $entry->url;
            } else {
                $url = null;
            }
        }

        return [
            'url' => $url,
            'label' => $value['label'] ?? null,
            'new_tab' => (bool) ($value['new_tab'] ?? false),
        ];
    }

    public function references(mixed $value, array $field): array
    {
        if (is_array($value) && ($value['type'] ?? null) === 'entry' && ($value['entry_id'] ?? null)) {
            return [['target_type' => 'entry', 'target_id' => (int) $value['entry_id']]];
        }

        return [];
    }
}
