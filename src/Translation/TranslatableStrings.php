<?php

declare(strict_types=1);

namespace Sunrice\Translation;

use Sunrice\Fields\TranslationOverlay;
use Sunrice\Models\Fieldset;

/**
 * Finds the translatable text inside field data: text, textarea and rich
 * text fields, including those nested in groups, repeaters and flexible
 * content blocks. Fields that aren't translatable (`translatable: false`,
 * or a type that isn't translatable by default) are skipped.
 */
class TranslatableStrings
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, array{value: string, rich: bool}> dot path => string
     */
    public static function collect(array $fields, array $data, string $prefix = ''): array
    {
        $strings = [];

        foreach ($fields as $field) {
            $handle = $field['handle'] ?? null;
            if (! is_string($handle) || ! TranslationOverlay::isTranslatable($field)) {
                continue;
            }

            $value = $data[$handle] ?? null;
            $path = $prefix.$handle;
            $children = $field['config']['fields'] ?? [];

            switch ($field['type'] ?? null) {
                case 'text':
                case 'textarea':
                case 'rich_text':
                    if (is_string($value) && trim(strip_tags($value)) !== '') {
                        $strings[$path] = ['value' => $value, 'rich' => $field['type'] === 'rich_text'];
                    }
                    break;
                case 'group':
                    if (is_array($value)) {
                        $strings += static::collect($children, $value, $path.'.');
                    }
                    break;
                case 'repeater':
                    foreach (is_array($value) ? $value : [] as $i => $row) {
                        if (is_array($row)) {
                            $strings += static::collect($children, $row, "{$path}.{$i}.");
                        }
                    }
                    break;
                case 'flexible':
                    foreach (is_array($value) ? $value : [] as $i => $block) {
                        $schema = is_array($block) ? Fieldset::schemaForHandle((string) ($block['type'] ?? '')) : null;
                        if ($schema !== null && is_array($block['values'] ?? null)) {
                            $strings += static::collect($schema->fields(), $block['values'], "{$path}.{$i}.values.");
                        }
                    }
                    break;
            }
        }

        return $strings;
    }
}
