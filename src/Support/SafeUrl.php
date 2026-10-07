<?php

declare(strict_types=1);

namespace Sunrice\Support;

/**
 * Links editors type in (menu items, Link fields) end up in <a href> on
 * the site. Relative URLs, #anchors and http(s)/mailto/tel are allowed;
 * other schemes (javascript:, data:, vbscript:…) are refused, since they
 * would run code when a visitor clicks.
 */
class SafeUrl
{
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function isSafe(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        // Browsers ignore whitespace and control characters inside a scheme.
        $clean = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $clean, $m) === 1) {
            return in_array(strtolower($m[1]), static::SCHEMES, true);
        }

        return true; // relative: /path, page, ?query, #anchor, //host
    }

    /** The URL when safe, otherwise null (for values saved before the check). */
    public static function orNull(?string $url): ?string
    {
        return static::isSafe($url) ? $url : null;
    }

    /** Validation rule for URL inputs. */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && ! static::isSafe($value)) {
                $fail('Use a web address (https://…), a path on this site (/about), mailto: or tel:.');
            }
        };
    }
}
