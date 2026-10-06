<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;

/**
 * Fieldset include (type 'fieldset'). BlueprintSchema expands it
 * inline at build time, so instances reaching this type only occur
 * when the referenced fieldset is missing — the value then passes
 * through untouched to preserve stored data.
 */
class FieldsetInclude extends FieldType
{
    public static function type(): string
    {
        return 'fieldset';
    }

    public function settingsSchema(): array
    {
        return [
            ['handle' => 'fieldset', 'type' => 'fieldset', 'label' => 'Fieldset to include'],
        ];
    }
}
