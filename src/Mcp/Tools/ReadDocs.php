<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Read Sunrice\'s documentation. Without a page: the list of pages (content modeling, templates, helpers, blade components, multilingual, forms, assets, caching, custom fields, commands…).')]
class ReadDocs extends SunriceTool
{
    protected string $name = 'read_docs';

    public function handle(Request $request): Response
    {
        $args = $request->validate(['page' => ['nullable', 'string', 'regex:/^[a-z0-9-]+$/']]);
        $dir = dirname(__DIR__, 3).'/docs';

        if (empty($args['page'])) {
            $pages = [];
            foreach (glob($dir.'/*.md') ?: [] as $file) {
                $first = strtok((string) file_get_contents($file), "\n");
                $pages[] = ['page' => basename($file, '.md'), 'title' => ltrim((string) $first, '# ')];
            }

            return $this->json(['pages' => $pages]);
        }

        $file = $dir.'/'.$args['page'].'.md';
        if (! is_file($file)) {
            return $this->notFound('Docs page');
        }

        return Response::text((string) file_get_contents($file));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('Page name, e.g. "templates".')];
    }
}
