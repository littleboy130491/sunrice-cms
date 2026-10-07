<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Globals\SaveGlobalValues;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Blueprint;
use Sunrice\Models\GlobalSet;
use Sunrice\Support\Locales;

#[Description('Create a global set (give title and blueprint; group "global" or "template_part") or change its values: `values` are merged by field handle into the values of `locale` (translatable sets; default the main language).')]
class SaveGlobal extends SunriceTool
{
    protected string $name = 'save_global';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'handle' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'title' => ['nullable', 'string', 'max:255'],
            'blueprint' => ['nullable', 'string'],
            'group' => ['nullable', Rule::in(['global', 'template_part'])],
            'translatable' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string'],
            'values' => ['nullable', 'array'],
        ]);
        $set = GlobalSet::query()->where('handle', $args['handle'])->first();
        $created = false;

        if ($set === null) {
            $this->authorize('create', GlobalSet::class);
            $blueprint = $this->findByHandle(Blueprint::class, $args['blueprint'] ?? null);
            if ($blueprint === null) {
                return Response::error('A new global set needs a blueprint (create one with save_blueprint).');
            }
            $set = GlobalSet::create([
                'handle' => $args['handle'],
                'title' => $args['title'] ?? ucfirst(str_replace('_', ' ', $args['handle'])),
                'group' => $args['group'] ?? 'global',
                'blueprint_id' => $blueprint->id,
                'translatable' => (bool) ($args['translatable'] ?? false),
            ]);
            ContentChanged::dispatch('global_saved');
            $created = true;
        } else {
            $this->authorize('update', $set);
        }

        if (isset($args['values'])) {
            $locale = $set->translatable ? ($args['locale'] ?? Locales::main()) : null;
            app(SaveGlobalValues::class)->handle($set, [
                'locale' => $locale,
                'values' => array_replace($set->valuesFor($locale ?? Locales::main()), $args['values']),
            ]);
        }

        return $this->json(['saved' => true, 'created' => $created, 'handle' => $set->handle, 'values' => $set->fresh()?->valuesFor($args['locale'] ?? Locales::main())]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
            'title' => $schema->string()->description('When creating.'),
            'blueprint' => $schema->string()->description('Blueprint handle, when creating.'),
            'group' => $schema->string()->enum(['global', 'template_part']),
            'translatable' => $schema->boolean()->description('When creating: values differ per language.'),
            'locale' => $schema->string(),
            'values' => $schema->object()->description('Field values by handle (merged).'),
        ];
    }
}
