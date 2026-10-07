<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Structure\SaveBlueprint as SaveBlueprintAction;
use Sunrice\Actions\Structure\SaveFieldset;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Fieldset;

#[Description('Create or replace a blueprint (the fields of a collection, taxonomy or global set) or a fieldset (a reusable group of fields, also used as flexible-content blocks), found by handle. `fields` is the complete list of field definitions [{handle, type, label, required, instructions, translatable, validation, config}] and replaces the current one; call get_blueprint without a handle for the field types and their config options. Removing a field hides its values but never deletes them.')]
class SaveBlueprint extends SunriceTool
{
    protected string $name = 'save_blueprint';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'kind' => ['nullable', 'in:blueprint,fieldset'],
            'handle' => ['required', 'string'],
            'title' => ['nullable', 'string'],
            'fields' => ['nullable', 'array'],
        ]);
        $fieldset = ($args['kind'] ?? 'blueprint') === 'fieldset';
        $class = $fieldset ? Fieldset::class : Blueprint::class;
        $model = $class::query()->where('handle', $args['handle'])->first();
        $this->authorize($model === null ? 'create' : 'update', $model ?? $class);

        $attributes = [
            'handle' => $args['handle'],
            'title' => $args['title'] ?? $model?->getAttribute('title') ?? ucfirst(str_replace('_', ' ', $args['handle'])),
            'fields' => $args['fields'] ?? $model?->getAttribute('fields') ?? [],
        ];
        $saved = $fieldset
            ? app(SaveFieldset::class)->handle($attributes, $model instanceof Fieldset ? $model : null)
            : app(SaveBlueprintAction::class)->handle($attributes, $model instanceof Blueprint ? $model : null);

        return $this->json([
            'saved' => true,
            'created' => $model === null,
            'kind' => $fieldset ? 'fieldset' : 'blueprint',
            'handle' => $saved->getAttribute('handle'),
            'fields' => $saved->getAttribute('fields'),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['blueprint', 'fieldset'])->description('Default blueprint.'),
            'handle' => $schema->string()->description('Lowercase handle; an existing one is updated.')->required(),
            'title' => $schema->string(),
            'fields' => $schema->array()->items($schema->object())->description('All field definitions (replaces the list).'),
        ];
    }
}
