<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Sunrice\Models\Collection;
use Sunrice\Models\Taxonomy;

/**
 * Base for Sunrice's MCP tools: every call runs as the signed-in user
 * (the token's owner), with the same permission checks as the admin.
 */
abstract class SunriceTool extends Tool
{
    /**
     * @param  mixed  $arguments  policy arguments (model, class, [class, id]…)
     *
     * @throws AuthorizationException
     */
    protected function authorize(string $ability, mixed $arguments = []): void
    {
        if (! Gate::allows($ability, $arguments)) {
            throw new AuthorizationException("You don't have permission for this ({$ability}).");
        }
    }

    protected function json(mixed $data): Response
    {
        return Response::json($data);
    }

    protected function notFound(string $what): Response
    {
        return Response::error("{$what} not found.");
    }

    /** A collection by handle or id. */
    protected function collection(mixed $key): ?Collection
    {
        return $this->findByHandle(Collection::class, $key);
    }

    /** A taxonomy by handle or id. */
    protected function taxonomy(mixed $key): ?Taxonomy
    {
        return $this->findByHandle(Taxonomy::class, $key);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function findByHandle(string $class, mixed $key): ?Model
    {
        if ($key === null || $key === '') {
            return null;
        }

        /** @var T|null $model */
        $model = is_numeric($key)
            ? $class::query()->find((int) $key)
            : $class::query()->where('handle', (string) $key)->first();

        return $model;
    }
}
