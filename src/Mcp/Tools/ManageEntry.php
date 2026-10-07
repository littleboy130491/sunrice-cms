<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Entries\DuplicateEntry;
use Sunrice\Actions\Entries\ForceDeleteEntry;
use Sunrice\Actions\Entries\RestoreEntry;
use Sunrice\Actions\Entries\TrashEntry;
use Sunrice\Actions\Entries\UnpublishEntry;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Entry;
use Sunrice\Models\Setting;

#[Description('Change an entry\'s lifecycle: unpublish (back to draft), trash, restore (from trash), delete (permanently, only from trash), duplicate (a draft copy), or set_homepage (show it at the site root). To publish, use update_entry with publish: true.')]
class ManageEntry extends SunriceTool
{
    protected string $name = 'manage_entry';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'id' => ['required', 'integer'],
            'action' => ['required', 'in:unpublish,trash,restore,delete,duplicate,set_homepage'],
        ]);
        $entry = Entry::withTrashed()->find($args['id']);
        if ($entry === null) {
            return $this->notFound('Entry');
        }

        switch ($args['action']) {
            case 'unpublish':
                $this->authorize('publish', $entry);
                app(UnpublishEntry::class)->handle($entry);
                break;
            case 'trash':
                $this->authorize('delete', $entry);
                app(TrashEntry::class)->handle($entry);
                break;
            case 'restore':
                $this->authorize('delete', $entry);
                app(RestoreEntry::class)->handle($entry);
                break;
            case 'delete':
                $this->authorize('delete', $entry);
                if (! $entry->trashed()) {
                    return Response::error('Trash the entry first; only trashed entries can be deleted permanently.');
                }
                app(ForceDeleteEntry::class)->handle($entry);

                return $this->json(['deleted' => true, 'id' => $args['id']]);
            case 'duplicate':
                $this->authorize('create', [Entry::class, $entry->collection_id]);
                $copy = app(DuplicateEntry::class)->handle($entry);

                return $this->json(['duplicated' => true] + Presenter::entry($copy));
            case 'set_homepage':
                $this->authorize('sunrice.settings.edit');
                Setting::set('homepage_entry_id', $entry->id);
                break;
        }

        return $this->json(['done' => $args['action']] + Presenter::entrySummary($entry->fresh() ?? $entry));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'action' => $schema->string()->enum(['unpublish', 'trash', 'restore', 'delete', 'duplicate', 'set_homepage'])->required(),
        ];
    }
}
