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

        // Two fields with one handle would share (and overwrite) one value.
        // Checked on its own: as part of the rules above it would reduce
        // each field to just its handle in $validated.
        validator(['fields' => $attributes['fields'] ?? []], [
            'fields.*.handle' => ['required', 'string', 'max:100', 'distinct', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], ['fields.*.handle.regex' => 'Field handles may only use letters, numbers, dashes and underscores.'], ['fields.*.handle' => 'field handle'])->validate();

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
