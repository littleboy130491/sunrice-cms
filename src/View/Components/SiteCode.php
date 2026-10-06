<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Outputs a code snippet from Settings (analytics, tag managers…):
 *
 *     <x-sunrice::code position="head" />        before </head>
 *     <x-sunrice::code position="body_start" />  right after <body>
 *     <x-sunrice::code position="body_end" />    before </body>
 *
 * The snippet is printed as-is (it is meant to contain <script> tags), so
 * only people with the settings permission can change it.
 */
class SiteCode extends Component
{
    public const POSITIONS = ['head', 'body_start', 'body_end'];

    public function __construct(public string $position = 'head')
    {
        if (! in_array($position, self::POSITIONS, true)) {
            throw new InvalidArgumentException("Unknown code position \"{$position}\"; use head, body_start or body_end.");
        }
    }

    public function render(): Htmlable
    {
        return new HtmlString((string) config("sunrice.code.{$this->position}", ''));
    }
}
