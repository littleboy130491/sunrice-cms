<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Actions\Assets\UploadAsset;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;

/**
 * Forms-only upload field. Files are stored on sunrice.forms.upload_disk
 * under form-uploads/{form} — separate from the asset library. The
 * stored value is the storage path.
 *
 * Config: `mimes` (accepted extensions; empty = any safe type) and
 * `max_kb` (empty = sunrice.forms.upload_max_kb). The server's own PHP
 * limit always applies on top.
 */
class File extends FieldType
{
    /** Extension groups offered in the form builder. */
    public const TYPE_GROUPS = [
        'images' => ['Images', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic']],
        'pdf' => ['PDF', ['pdf']],
        'documents' => ['Documents (Word, ODT, RTF)', ['doc', 'docx', 'odt', 'rtf']],
        'spreadsheets' => ['Spreadsheets (Excel, CSV)', ['xls', 'xlsx', 'ods', 'csv']],
        'presentations' => ['Presentations', ['ppt', 'pptx', 'odp']],
        'text' => ['Plain text', ['txt']],
        'archives' => ['ZIP archives', ['zip']],
        'audio' => ['Audio', ['mp3', 'wav', 'm4a', 'ogg']],
        'video' => ['Video', ['mp4', 'mov', 'webm']],
    ];

    public static function type(): string
    {
        return 'file';
    }

    public function rules(array $field): array
    {
        $rules = ['file'];
        $types = static::acceptedTypes($field);
        if ($types !== []) {
            $rules[] = 'mimes:'.implode(',', $types);
        }
        $rules[] = 'max:'.static::maxKilobytes($field);
        // Whatever the field accepts, never files that could run as code.
        $rules[] = function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_object($value) && method_exists($value, 'getClientOriginalExtension')
                && in_array(strtolower((string) $value->getClientOriginalExtension()), UploadAsset::BLOCKED_EXTENSIONS, true)) {
                $fail(__('sunrice::frontend.upload_type_blocked'));
            }
        };

        return $rules;
    }

    /**
     * Messages for this field's upload rules, from the package's own
     * translations, so sites without (complete) lang/validation files still
     * show readable text.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    public static function messages(string $attribute, array $field): array
    {
        $size = static::formatKilobytes(min(static::maxKilobytes($field), static::serverLimitKilobytes() ?? PHP_INT_MAX));
        $types = static::acceptedTypes($field);

        return [
            "{$attribute}.file" => __('sunrice::frontend.upload_failed', ['size' => $size]),
            "{$attribute}.uploaded" => __('sunrice::frontend.upload_failed', ['size' => $size]),
            "{$attribute}.mimes" => __('sunrice::frontend.upload_type', ['types' => static::describeTypes($types)]),
            "{$attribute}.max" => __('sunrice::frontend.upload_size', ['size' => $size]),
        ];
    }

    /**
     * Accepted extensions (lowercase, without dots); empty means any type
     * except the blocked ones.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    public static function acceptedTypes(array $field): array
    {
        $mimes = $field['config']['mimes'] ?? [];
        $list = is_array($mimes) ? $mimes : explode(',', (string) $mimes);

        return array_values(array_unique(array_filter(array_map(
            fn ($ext) => strtolower(trim(ltrim((string) $ext, '.'))),
            $list,
        ))));
    }

    /** @param array<string, mixed> $field */
    public static function maxKilobytes(array $field): int
    {
        // A public form shouldn't take files of any size: fields without
        // their own limit get the configured default.
        return (int) (($field['config']['max_kb'] ?? null) ?: config('sunrice.forms.upload_max_kb', 10240));
    }

    /**
     * The largest upload PHP accepts here (upload_max_filesize and
     * post_max_size), in KB; null when unlimited.
     */
    public static function serverLimitKilobytes(): ?int
    {
        $limits = array_filter(array_map(
            fn (string $key) => static::iniBytes((string) ini_get($key)),
            ['upload_max_filesize', 'post_max_size'],
        ), fn (int $bytes) => $bytes > 0);

        return $limits === [] ? null : intdiv(min($limits), 1024);
    }

    /**
     * `accept` attribute for the file input, e.g. ".pdf,.jpg".
     *
     * @param  array<string, mixed>  $field
     */
    public static function acceptAttribute(array $field): string
    {
        return implode(',', array_map(fn (string $ext) => '.'.$ext, static::acceptedTypes($field)));
    }

    /**
     * Short hint for the public form, e.g. "PDF, JPG · up to 5 MB".
     *
     * @param  array<string, mixed>  $field
     */
    public static function hint(array $field): string
    {
        $size = static::formatKilobytes(min(static::maxKilobytes($field), static::serverLimitKilobytes() ?? PHP_INT_MAX));
        $types = static::acceptedTypes($field);

        return $types === []
            ? __('sunrice::frontend.upload_hint_any', ['size' => $size])
            : __('sunrice::frontend.upload_hint', ['types' => static::describeTypes($types), 'size' => $size]);
    }

    public static function formatKilobytes(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : $kb.' KB';
    }

    /** @param array<int, string> $types */
    protected static function describeTypes(array $types): string
    {
        return implode(', ', array_map('strtoupper', $types));
    }

    protected static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }
        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
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

    public function settingsSchema(): array
    {
        return [
            [
                'handle' => 'mimes', 'type' => 'file_types', 'label' => 'Accepted file types',
                'options' => [
                    'groups' => array_map(
                        fn (string $key, array $group) => ['key' => $key, 'label' => $group[0], 'extensions' => $group[1]],
                        array_keys(static::TYPE_GROUPS),
                        static::TYPE_GROUPS,
                    ),
                    'blocked' => UploadAsset::BLOCKED_EXTENSIONS,
                ],
            ],
            [
                'handle' => 'max_kb', 'type' => 'file_size', 'label' => 'Maximum file size',
                'options' => [
                    'default_kb' => (int) config('sunrice.forms.upload_max_kb', 10240),
                    'server_kb' => static::serverLimitKilobytes(),
                    'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                    'post_max_size' => (string) ini_get('post_max_size'),
                ],
            ],
        ];
    }
}
