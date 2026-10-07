<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Fieldset;

#[IsReadOnly]
#[Description('Read a blueprint or fieldset (its fields as saved: tabs, field definitions, config). Without a handle: every field type with its config options, to build field definitions for save_blueprint.')]
class GetBlueprint extends SunriceTool
{
    protected string $name = 'get_blueprint';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'handle' => ['nullable', 'string'],
            'kind' => ['nullable', 'in:blueprint,fieldset'],
        ]);
        $kind = $args['kind'] ?? 'blueprint';
        $this->authorize('viewAny', $kind === 'fieldset' ? Fieldset::class : Blueprint::class);

        if (empty($args['handle'])) {
            return $this->json(['field_types' => collect(app(FieldRegistry::class)->all())->map(fn ($type, string $key) => [
                'type' => $key,
                'translatable_by_default' => $type->translatableByDefault(),
                'sortable' => $type->sortCast() !== null,
                'config_options' => $type->settingsSchema(),
            ])->values()->all(), 'common_keys' => [
                'handle' => 'a-z, 0-9, _ or -; unique in the blueprint',
                'type' => 'one of the types above',
                'label' => 'shown to editors',
                'required' => 'bool',
                'instructions' => 'help text under the field',
                'translatable' => 'bool; override the type\'s default',
                'validation' => 'extra Laravel rules, e.g. ["max:160"]',
                'config' => 'type options (config_options above); group and repeater take config.fields (child field definitions); flexible takes config.fieldsets (handles of the fieldsets editors can add as blocks; empty = all); a field of type "fieldset" with config.fieldset (handle) includes that fieldset\'s fields in place',
            ]]);
        }

        $model = $this->findByHandle($kind === 'fieldset' ? Fieldset::class : Blueprint::class, $args['handle']);
        if ($model === null) {
            return $this->notFound(ucfirst($kind));
        }

        return $this->json([
            'id' => $model->id,
            'handle' => $model->getAttribute('handle'),
            'title' => $model->getAttribute('title'),
            'fields' => $model->getAttribute('fields'),
            // Fieldsets expanded: the fields entries actually have.
            'resolved_fields' => $model instanceof Blueprint ? Presenter::fields($model->schema()->fields()) : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->description('Blueprint/fieldset handle; omit to list field types.'),
            'kind' => $schema->string()->enum(['blueprint', 'fieldset'])->description('Default blueprint.'),
        ];
    }
}
