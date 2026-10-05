<?php

declare(strict_types=1);

namespace Sunrice\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Sunrice\Support\SlugValidator;

class ValidSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SlugValidator::isValid($value)) {
            $fail('The :attribute must be a lowercase kebab-case slug that does not collide with a locale or the admin path.');
        }
    }
}
