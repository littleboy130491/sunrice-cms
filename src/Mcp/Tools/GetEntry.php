<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Entry;

#[IsReadOnly]
#[Description('Everything about one entry: status, parent, terms, its blueprint fields (handle, type, config) and, per language, the live title, slug, URL, field data and SEO, plus the unpublished draft if there is one.')]
class GetEntry extends SunriceTool
{
    protected string $name = 'get_entry';

    public function handle(Request $request): Response
    {
        $args = $request->validate(['id' => ['required', 'integer']]);
        $entry = Entry::withTrashed()->find($args['id']);
        if ($entry === null) {
            return $this->notFound('Entry');
        }
        $this->authorize('view', $entry);

        return $this->json(Presenter::entry($entry));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->description('Entry id.')->required()];
    }
}
