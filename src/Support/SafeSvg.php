<?php

declare(strict_types=1);

namespace Sunrice\Support;

use DOMDocument;
use DOMXPath;

/**
 * Checks that an uploaded SVG can't run script. Assets are served from
 * the site's own domain, so an SVG opened directly in the browser would
 * run any script inside it with the site's (and the admin's) origin.
 *
 * Rejects rather than cleans: a file that fails is refused with a message,
 * so nothing is silently changed. Logos and icons exported from design
 * tools pass.
 */
class SafeSvg
{
    /** Elements that run script or embed other documents. */
    protected const BLOCKED_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener'];

    /** Attributes holding a URL (or a value animated into one). */
    protected const URL_ATTRIBUTES = ['href', 'xlink:href', 'src', 'from', 'to', 'values', 'by', 'action', 'formaction'];

    public static function isSafe(string $svg): bool
    {
        // Entities can smuggle content past the checks below.
        if (preg_match('/<!ENTITY/i', $svg) === 1) {
            return false;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return false; // not well-formed: can't vouch for it
        }

        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//*') ?: [] as $element) {
            if (in_array(strtolower((string) $element->localName), static::BLOCKED_ELEMENTS, true)) {
                return false;
            }
        }

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;
            if (str_starts_with(strtolower((string) $attribute->localName), 'on')) {
                return false; // event handlers: onload, onclick…
            }
            if (in_array($name, static::URL_ATTRIBUTES, true) && static::isScriptUrl($value)) {
                return false;
            }
            if ($name === 'style' && preg_match('/javascript:|expression\s*\(/i', $value) === 1) {
                return false;
            }
        }

        return true;
    }

    protected static function isScriptUrl(string $value): bool
    {
        // Browsers ignore whitespace and control characters in a scheme.
        $clean = strtolower((string) preg_replace('/[\x00-\x20\x7F]+/', '', $value));

        return str_contains($clean, 'javascript:')
            || str_contains($clean, 'vbscript:')
            || str_starts_with($clean, 'data:text/html')
            || str_starts_with($clean, 'data:image/svg');
    }
}
