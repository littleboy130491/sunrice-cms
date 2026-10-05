<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Illuminate\Validation\Rule;
use Sunrice\Models\Fieldset;

class SaveFieldset
{
    /**
     * @param  array{handle?: string, title: string, fields?: array}  $attributes
     */
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, ?Fieldset $fieldset = null): Fieldset
    {
        $validated = validator($attributes, [
            'handle' => [
                $fieldset === null ? 'required' : 'sometimes',
                'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sunrice_fieldsets', 'handle')->ignore($fieldset),
            ],
            'title' => ['required', 'string', 'max:255'],
            'fields' => ['array'],
        ])->validate();

        $fieldset ??= new Fieldset;
        $fieldset->fill([
            'handle' => $validated['handle'] ?? $fieldset->handle,
            'title' => $validated['title'],
            'fields' => $validated['fields'] ?? $fieldset->fields ?? [],
        ]);
        $fieldset->save();

        return $fieldset;
    }
}
