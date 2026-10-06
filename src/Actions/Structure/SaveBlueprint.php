<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Illuminate\Validation\Rule;
use Sunrice\Models\Blueprint;

class SaveBlueprint
{
    /**
     * @param  array{handle?: string, title: string, fields?: array<int, array<string, mixed>>}  $attributes
     */
    public function handle(array $attributes, ?Blueprint $blueprint = null): Blueprint
    {
        $validated = validator($attributes, [
            'handle' => [
                $blueprint === null ? 'required' : 'sometimes',
                'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sunrice_blueprints', 'handle')->ignore($blueprint),
            ],
            'title' => ['required', 'string', 'max:255'],
            'fields' => ['array'],
        ])->validate();

        // Two fields with one handle would share (and overwrite) one value.
        // Checked on its own: as part of the rules above it would reduce
        // each field to just its handle in $validated.
        validator(['fields' => $attributes['fields'] ?? []], [
            'fields.*.handle' => ['required', 'string', 'max:100', 'distinct'],
        ], [], ['fields.*.handle' => 'field handle'])->validate();

        $blueprint ??= new Blueprint;
        $blueprint->fill([
            'handle' => $validated['handle'] ?? $blueprint->handle,
            'title' => $validated['title'],
            'fields' => $validated['fields'] ?? $blueprint->fields ?? [],
        ]);
        $blueprint->save();

        return $blueprint;
    }
}
