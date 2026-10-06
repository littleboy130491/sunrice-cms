<?php

declare(strict_types=1);

namespace Sunrice\Translation;

interface Translator
{
    /**
     * Translate a batch of strings. Keys are preserved; a missing key in the
     * result means that string could not be translated.
     *
     * @param  array<string, string>  $strings
     * @return array<string, string>
     */
    public function translate(array $strings, string $from, string $to, ?string $context = null): array;
}
