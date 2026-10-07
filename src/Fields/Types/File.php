<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * Forms-only upload field. Files are stored on sunrice.forms.upload_disk
 * under form-uploads/{form} — separate from the asset library. The
 * stored value is the storage path.
 */
class File extends FieldType
{
    public static function type(): string
    {
        return 'file';
    }

    public function rules(array $field): array
    {
        $rules = ['file'];
        if ($mimes = $field['config']['mimes'] ?? null) {
            $rules[] = 'mimes:'.(is_array($mimes) ? implode(',', $mimes) : $mimes);
        }
        // A public form shouldn't take files of any size: fields without
        // their own limit get the configured default.
        $max = ($field['config']['max_kb'] ?? null) ?: config('sunrice.forms.upload_max_kb', 10240);
        if ($max) {
            $rules[] = 'max:'.(int) $max;
        }

        return $rules;
    }

    public function normalize(mixed $value, array $field): mixed
    {
        // The actual file is stored by SubmitForm; normalize keeps the path.
        return $value;
    }

    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        return $value; // path string; download is authorized via the admin.
    }
}
