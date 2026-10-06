<?php

declare(strict_types=1);

namespace Sunrice\Translation;

/** Running totals for one `sunrice:translate` run. */
class TranslationStats
{
    public int $translated = 0;

    /** Strings left alone because a translation already exists. */
    public int $skipped = 0;

    /** Strings that would be sent to the model (dry run). */
    public int $pending = 0;

    public int $failed = 0;

    /** @var array<int, string> */
    public array $errors = [];
}
