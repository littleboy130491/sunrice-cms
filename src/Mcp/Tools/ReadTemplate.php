<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Mcp\Templates;

#[IsReadOnly]
#[Description('Read a template file. source "site" (default): the site\'s templates in resources/views/sunrice; "starter": the starter templates shipped with Sunrice (complete examples: layouts/app, partials/header|footer|menu|card, show, index, articles/show, taxonomies/show, blocks/*); "default": the package fallback views (show, index, term); "theme": CSS/JS files in public/sunrice-theme. Paths are relative, e.g. "layouts/app.blade.php".')]
class ReadTemplate extends SunriceTool
{
    protected string $name = 'read_template';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'in:site,starter,default,theme'],
        ]);
        $this->authorize('sunrice.access-admin');

        $source = $args['source'] ?? 'site';
        $root = match ($source) {
            'starter' => Templates::starterDirectory(),
            'default' => Templates::defaultsDirectory(),
            'theme' => Templates::themeDirectory(),
            default => Templates::appDirectory(),
        };
        $file = Templates::resolve($root, $args['path'], $source === 'theme' ? WriteTemplate::THEME_EXTENSIONS : ['.blade.php']);
        if (! is_file($file)) {
            return Response::error("No {$source} template \"{$args['path']}\". Use get_template_guide for the list.");
        }

        return $this->json([
            'source' => $source,
            'path' => $args['path'],
            'view' => $source === 'site' ? Templates::viewName($args['path']) : null,
            'content' => (string) file_get_contents($file),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required(),
            'source' => $schema->string()->enum(['site', 'starter', 'default', 'theme']),
        ];
    }
}
