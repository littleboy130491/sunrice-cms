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
