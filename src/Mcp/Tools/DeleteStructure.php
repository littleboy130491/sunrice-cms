<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Sunrice\Actions\Forms\DeleteForm;
use Sunrice\Actions\Structure\DeleteBlueprint;
use Sunrice\Actions\Structure\DeleteCollection;
use Sunrice\Actions\Structure\DeleteFieldset;
use Sunrice\Actions\Taxonomies\DeleteTaxonomy;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Fieldset;
use Sunrice\Models\Form;
use Sunrice\Models\GlobalSet;
use Sunrice\Models\Menu;
use Sunrice\Models\Taxonomy;
use Sunrice\Permissions\SyncPermissions;

#[IsDestructive]
#[Description('Delete a collection, taxonomy, blueprint, fieldset, global set, menu or form by handle. Deleting a collection or taxonomy hides its entries/terms (re-creating the handle brings them back); blueprints and fieldsets still in use are refused. Ask the user before deleting.')]
class DeleteStructure extends SunriceTool
{
    protected string $name = 'delete_structure';

    protected const TYPES = [
        'collection' => Collection::class,
        'taxonomy' => Taxonomy::class,
        'blueprint' => Blueprint::class,
        'fieldset' => Fieldset::class,
        'global' => GlobalSet::class,
        'menu' => Menu::class,
        'form' => Form::class,
    ];

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'handle' => ['required', 'string'],
        ]);
        $model = $this->findByHandle(self::TYPES[$args['type']], $args['handle']);
        if ($model === null) {
            return $this->notFound(ucfirst($args['type']));
        }
        $this->authorize('delete', $model);

        match (true) {
            $model instanceof Collection => app(DeleteCollection::class)->handle($model),
            $model instanceof Taxonomy => app(DeleteTaxonomy::class)->handle($model),
            $model instanceof Blueprint => app(DeleteBlueprint::class)->handle($model),
            $model instanceof Fieldset => app(DeleteFieldset::class)->handle($model),
            $model instanceof Form => app(DeleteForm::class)->handle($model),
            default => $model->delete(),
        };
        if ($model instanceof Menu) {
            app(SyncPermissions::class)->handle();
        }
        if ($model instanceof GlobalSet || $model instanceof Menu) {
            ContentChanged::dispatch($args['type'].'_deleted');
        }

        return $this->json(['deleted' => true, 'type' => $args['type'], 'handle' => $args['handle']]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_keys(self::TYPES))->required(),
            'handle' => $schema->string()->required(),
        ];
    }
}
