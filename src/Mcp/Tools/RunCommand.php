<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Throwable;

#[IsDestructive]
#[Description('Run an allowed artisan command (the list is in sunrice.mcp.commands; call with command "list" to see it) with options, e.g. {command: "sunrice:translate", options: {"--to": ["en"], "--dry-run": true}}. Runs non-interactively; commands that ask for confirmation need their --force option. Ask the user before anything that deletes data (e.g. sunrice:orphans --purge).')]
class RunCommand extends SunriceTool
{
    protected string $name = 'run_command';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'command' => ['required', 'string', 'max:100'],
            'options' => ['nullable', 'array'],
        ]);
        $this->authorize('sunrice.run-commands');

        $allowed = array_values(array_filter((array) config('sunrice.mcp.commands', []), 'is_string'));
        if ($args['command'] === 'list') {
            return $this->json(['allowed_commands' => $allowed]);
        }
        if (! in_array($args['command'], $allowed, true)) {
            return Response::error("\"{$args['command']}\" isn't in the allowed commands: ".implode(', ', $allowed).'.');
        }

        $parameters = [];
        foreach ((array) ($args['options'] ?? []) as $key => $value) {
            // Options only (--name); positional arguments by their name.
            if (! is_string($key) || ! preg_match('/^(--)?[a-z][a-z0-9-]*$/', $key)) {
                return Response::error("Invalid option name \"{$key}\".");
            }
            $parameters[$key] = $value;
        }
        $parameters['--no-interaction'] = true;

        try {
            $code = Artisan::call($args['command'], $parameters);
        } catch (Throwable $e) {
            return Response::error('The command failed: '.$e->getMessage());
        }

        return $this->json([
            'command' => $args['command'],
            'exit_code' => $code,
            'output' => mb_substr(Artisan::output(), -20000),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->description('Command name, or "list".')->required(),
            'options' => $schema->object()->description('{"--option": value, "argument": value}'),
        ];
    }
}
