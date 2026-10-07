<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Sunrice\Mcp\Templates;

#[IsDestructive]
#[Description('Create, replace or delete a template in resources/views/sunrice (Blade, target "site") or a theme file in public/sunrice-theme (CSS, JS, SVG…, target "theme", linked with asset("sunrice-theme/…")). Blade is checked for syntax errors before saving, and the previous version is kept as a backup. Read get_template_guide first; then check the page with render_page. Only super admins, and only when the site allows it (sunrice.mcp.templates).')]
class WriteTemplate extends SunriceTool
{
    protected string $name = 'write_template';

    public const THEME_EXTENSIONS = ['.css', '.js', '.svg', '.json', '.txt', '.map'];

    public static function allowed(?Authenticatable $user): bool
    {
        return (bool) config('sunrice.mcp.templates', true)
            && $user !== null && method_exists($user, 'hasRole') && $user->hasRole(config('sunrice.super_admin_role'));
    }

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'target' => ['nullable', 'in:site,theme'],
            'content' => ['nullable', 'string', 'max:500000'],
            'delete' => ['nullable', 'boolean'],
        ]);
        if (! static::allowed($request->user())) {
            return Response::error('Writing templates runs code on the server, so it needs a super admin, and sunrice.mcp.templates must be on (SUNRICE_MCP_TEMPLATES).');
        }

        $theme = ($args['target'] ?? 'site') === 'theme';
        $root = $theme ? Templates::themeDirectory() : Templates::appDirectory();
        $file = Templates::resolve($root, $args['path'], $theme ? self::THEME_EXTENSIONS : ['.blade.php']);

        if (! empty($args['delete'])) {
            if (! is_file($file)) {
                return $this->notFound('Template');
            }
            $this->backup($file, $root, $args['path']);
            File::delete($file);

            return $this->json(['deleted' => true, 'path' => $args['path']]);
        }

        $content = (string) ($args['content'] ?? '');
        if (! $theme && ($error = Templates::syntaxError($content)) !== null) {
            return Response::error("Not saved: the template has a syntax error: {$error}");
        }

        $existed = is_file($file);
        if ($existed) {
            $this->backup($file, $root, $args['path']);
        }
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $content);

        return $this->json([
            'saved' => true,
            'created' => ! $existed,
            'path' => $args['path'],
            'view' => $theme ? null : Templates::viewName($args['path']),
            'url' => $theme ? asset('sunrice-theme/'.ltrim($args['path'], '/')) : null,
        ]);
    }

    protected function backup(string $file, string $root, string $relative): void
    {
        $target = storage_path('app/sunrice/template-backups/'.date('Y-m-d_His').'/'.basename($root).'/'.ltrim($relative, '/'));
        File::ensureDirectoryExists(dirname($target));
        File::copy($file, $target);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Relative path, e.g. "pages/landing.blade.php" or "site.css".')->required(),
            'target' => $schema->string()->enum(['site', 'theme'])->description('Default site (Blade templates).'),
            'content' => $schema->string()->description('The whole file.'),
            'delete' => $schema->boolean()->description('Delete the file instead.'),
        ];
    }
}
