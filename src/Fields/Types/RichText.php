<?php

declare(strict_types=1);

namespace Sunrice\Fields\Types;

use Sunrice\Fields\FieldType;
use Sunrice\Support\HtmlSanitizer;

class RichText extends FieldType
{
    public static function type(): string
    {
        return 'rich_text';
    }

    public function rules(array $field): array
    {
        return ['string'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return HtmlSanitizer::sanitize($value === null ? null : (string) $value);
    }

    public function references(mixed $value, array $field): array
    {
        return array_map(
            fn (int $id) => ['target_type' => 'asset', 'target_id' => $id],
            HtmlSanitizer::extractAssetIds(is_string($value) ? $value : null),
        );
    }
}
