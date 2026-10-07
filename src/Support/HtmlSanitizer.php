<?php

declare(strict_types=1);

namespace Sunrice\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes rich text on save. The allow-list matches TipTap's
 * StarterKit + Link + Image output. `data-asset-id` is allowed on
 * <img> for asset usage tracking.
 */
class HtmlSanitizer
{
    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('del')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('blockquote')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'data-asset-id'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->allowAttribute('class', ['p', 'figure', 'figcaption', 'blockquote', 'pre', 'code', 'table', 'img'])
            ->withMaxInputLength(500_000);

        return (new SymfonyHtmlSanitizer($config))->sanitize($html);
    }

    /**
     * Asset ids referenced via data-asset-id inside rich text.
     *
     * @return array<int, int>
     */
    public static function extractAssetIds(?string $html): array
    {
        if ($html === null || $html === '') {
            return [];
        }

        preg_match_all('/data-asset-id=["\'](\d+)["\']/', $html, $matches);

        return array_values(array_map('intval', array_unique($matches[1])));
    }
}
