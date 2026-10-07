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
use Sunrice\Models\Entry;

#[IsReadOnly]
#[Description('List the entries of a collection (newest first), with search by title, status filter and paging. Returns id, title, slug, status, URL and languages; use get_entry for the content.')]
class ListEntries extends SunriceTool
{
    protected string $name = 'list_entries';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'collection' => ['required'],
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:published,draft,scheduled,trashed'],
            'parent_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $collection = $this->collection($args['collection']);
        if ($collection === null) {
            return $this->notFound('Collection');
        }
        $this->authorize('viewAny', [Entry::class, $collection->id]);

        $query = Entry::query()->where('collection_id', $collection->id)->with('translations')
            ->when($args['search'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $q) => $q
                ->whereHas('translations', fn (Builder $t) => $t->whereLike('title', "%{$term}%"))
                ->when(ctype_digit($term), fn (Builder $q) => $q->orWhere('id', (int) $term))))
            ->when(array_key_exists('parent_id', $args), fn (Builder $q) => $q->where('parent_id', $args['parent_id']))
            ->latest('id');

        match ($args['status'] ?? null) {
            'published' => $query->where('status', 'published')->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now())),
            'scheduled' => $query->where('status', 'published')->where('published_at', '>', now()),
            'draft' => $query->where('status', 'draft'),
            'trashed' => $query->onlyTrashed(),
            default => null,
        };

        $page = $query->paginate((int) ($args['per_page'] ?? 25), ['*'], 'page', (int) ($args['page'] ?? 1));

        return $this->json([
            'collection' => $collection->handle,
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'entries' => $page->getCollection()->map(fn (Entry $e) => Presenter::entrySummary($e))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'collection' => $schema->string()->description('Collection handle (or id).')->required(),
            'search' => $schema->string()->description('Part of the title, or an entry id.'),
            'status' => $schema->string()->enum(['published', 'draft', 'scheduled', 'trashed']),
            'parent_id' => $schema->integer()->description('Only children of this entry (hierarchical collections).'),
            'page' => $schema->integer()->description('Page number, from 1.'),
            'per_page' => $schema->integer()->description('Up to 100 (default 25).'),
        ];
    }
}
