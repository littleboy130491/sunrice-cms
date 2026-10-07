<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Asset;

#[IsReadOnly]
#[Description('Search the media library (newest first): id, filename, title, alt text, size, URL. Use asset ids in asset fields, SEO images and rich text.')]
class ListAssets extends SunriceTool
{
    protected string $name = 'list_assets';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'images_only' => ['nullable', 'boolean'],
            'missing_alt' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $this->authorize('viewAny', Asset::class);

        $page = Asset::query()
            ->when($args['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q->whereLike('filename', "%{$s}%")->orWhereLike('title', "%{$s}%")))
            ->when(! empty($args['images_only']) || ! empty($args['missing_alt']), fn (Builder $q) => $q->where('mime_type', 'like', 'image/%'))
            ->when(! empty($args['missing_alt']), fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('alt')->orWhere('alt', '')))
            ->latest('id')
            ->paginate(50, ['*'], 'page', (int) ($args['page'] ?? 1));

        return $this->json([
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'assets' => $page->getCollection()->map(fn (Asset $a) => Presenter::asset($a))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of the filename or title.'),
            'images_only' => $schema->boolean(),
            'missing_alt' => $schema->boolean()->description('Only images without alt text (accessibility / SEO).'),
            'page' => $schema->integer(),
        ];
    }
}
