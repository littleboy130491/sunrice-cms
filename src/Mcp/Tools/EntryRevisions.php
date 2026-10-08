<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Entries\RestoreRevision;
use Sunrice\Models\Entry;
use Sunrice\Models\Revision;
use Sunrice\Support\Locales;

#[Description('An entry\'s published versions in one language (a revision is saved each time it is published). action "list" (default): newest first, with date, who and the title; "show": one revision\'s full content (title, slug, data, seo); "restore": copy a revision into the current draft (replaces unsaved draft changes; publish it with update_entry publish: true).')]
class EntryRevisions extends SunriceTool
{
    protected string $name = 'entry_revisions';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'action' => ['nullable', 'in:list,show,restore'],
            'entry_id' => ['required_without:revision_id', 'nullable', 'integer'],
            'locale' => ['nullable', 'string'],
            'revision_id' => ['required_if:action,show,restore', 'nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $action = $args['action'] ?? 'list';

        if ($action !== 'list') {
            $revision = Revision::query()->with('translation')->find((int) $args['revision_id']);
            $translation = $revision?->translation;
            $entry = $translation === null ? null : Entry::withTrashed()->find($translation->entry_id);
            if ($revision === null || $translation === null || $entry === null) {
                return $this->notFound('Revision');
            }
            $this->authorize(Locales::isMain($translation->locale) ? 'update' : 'translate', $entry);

            if ($action === 'show') {
                return $this->json(['id' => $revision->id, 'entry_id' => $translation->entry_id, 'locale' => $translation->locale, 'created_at' => $revision->created_at?->toIso8601String(), 'content' => $revision->content]);
            }

            app(RestoreRevision::class)->handle($revision);

            return $this->json(['restored' => true, 'entry_id' => $translation->entry_id, 'locale' => $translation->locale, 'draft' => $translation->fresh()?->draft]);
        }

        $entry = Entry::withTrashed()->find((int) $args['entry_id']);
        if ($entry === null) {
            return $this->notFound('Entry');
        }
        $this->authorize('view', $entry);
        $locale = (string) ($args['locale'] ?? Locales::main());
        $translation = $entry->translations()->where('locale', $locale)->first();
        if ($translation === null) {
            return $this->json(['entry_id' => $entry->id, 'locale' => $locale, 'revisions' => []]);
        }

        $revisions = $translation->revisions()->orderByDesc('id')->with('user')->limit((int) ($args['limit'] ?? 20))->get()
            ->map(fn (Revision $r) => [
                'id' => $r->id,
                'created_at' => $r->created_at?->toIso8601String(),
                'by' => $r->user?->getAttribute('name'),
                'title' => $r->content['title'] ?? null,
            ])->values()->all();

        return $this->json(['entry_id' => $entry->id, 'locale' => $locale, 'revisions' => $revisions]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['list', 'show', 'restore']),
            'entry_id' => $schema->integer()->description('For list.'),
            'locale' => $schema->string()->description('For list; default the main language.'),
            'revision_id' => $schema->integer()->description('For show and restore.'),
            'limit' => $schema->integer()->description('For list; default 20.'),
        ];
    }
}
