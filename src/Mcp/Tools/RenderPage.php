<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
#[Description('Render a page of the site (a path such as "/", "/blog/hello" or "/en/about") as a visitor sees it and return the HTTP status and HTML, or the error a template threw. Use it to check templates after writing them.')]
class RenderPage extends SunriceTool
{
    protected string $name = 'render_page';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'path' => ['required', 'string', 'max:2000', 'regex:#^/#'],
            'max_length' => ['nullable', 'integer', 'min:1000', 'max:500000'],
        ]);
        $this->authorize('sunrice.access-admin');

        $admin = '/'.trim((string) config('sunrice.admin.path', 'cms'), '/');
        if ($args['path'] === $admin || str_starts_with($args['path'], $admin.'/')) {
            return Response::error('Only public pages can be rendered.');
        }

        // As a signed-out visitor; afterwards everything is put back.
        $guard = Auth::guard(config('sunrice.auth.guard', 'web'));
        $user = $guard->user();
        $original = app('request');
        try {
            $guard->forgetUser();
            $response = app(Kernel::class)->handle(HttpRequest::create($args['path'], 'GET'));
        } catch (Throwable $e) {
            return Response::error('Rendering failed: '.$e->getMessage());
        } finally {
            app()->instance('request', $original);
            if ($user !== null) {
                $guard->setUser($user);
            }
        }

        $html = (string) $response->getContent();
        $max = (int) ($args['max_length'] ?? 60000);
        $exception = property_exists($response, 'exception') ? $response->exception : null;

        return $this->json([
            'status' => $response->getStatusCode(),
            'redirect' => $response->headers->get('Location'),
            'error' => $exception instanceof Throwable ? $exception->getMessage().' in '.basename($exception->getFile()).':'.$exception->getLine() : null,
            'length' => strlen($html),
            'html' => mb_substr($html, 0, $max),
            'truncated' => strlen($html) > $max,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Path starting with "/".')->required(),
            'max_length' => $schema->integer()->description('Characters of HTML to return (default 60000).'),
        ];
    }
}
