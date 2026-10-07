<?php

declare(strict_types=1);

namespace Sunrice\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Sunrice\Support\SlugValidator;

class ValidSlug implements ValidationRule
{
    /**
     * @param  array<mixed>  $current  Slugs the record already has: saving
     *                                 one unchanged is always allowed, so
     *                                 older slugs don't block other edits.
     */
    public function __construct(protected array $current = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && in_array($value, $this->current, true)) {
            return;
        }

        if (! is_string($value) || ! SlugValidator::isValid($value)) {
            $fail('The :attribute may only use lowercase letters, numbers and dashes (like "my-page"), and can\'t be a language code or the admin path.');
        }
    }
}
