<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Entries\ChangeBlueprint;
use Sunrice\Actions\Entries\CreateEntry;
use Sunrice\Actions\Entries\DuplicateEntry;
use Sunrice\Actions\Entries\ForceDeleteEntry;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Actions\Entries\RestoreEntry;
use Sunrice\Actions\Entries\RestoreRevision;
use Sunrice\Actions\Entries\ReturnTranslationToDraft;
use Sunrice\Actions\Entries\SaveDraft;
use Sunrice\Actions\Entries\TrashEntry;
use Sunrice\Actions\Entries\UnpublishEntry;
use Sunrice\Actions\Support\Reorder;
use Sunrice\Admin\Export\CsvExporter;
use Sunrice\Admin\Table\Column;
use Sunrice\Admin\Table\TableQuery;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Revision;
use Sunrice\Support\Locales;
use Sunrice\Support\SlugValidator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntriesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Collection $collection): Response
    {
        $this->authorize('viewAny', [Entry::class, $collection->id]);

        $query = Entry::query()
            ->where('collection_id', $collection->id)
            ->with(['translations' => fn ($q) => $q->where('locale', Locales::main())]);

        $table = TableQuery::for($query)
            ->searchable(['id'])
            ->filterable(['status'])
            ->sortable(['id', 'published_at', 'sort_order', 'created_at', 'updated_at'])
            ->apply($request);

        $userId = $request->user()->getAuthIdentifier();
        $columns = [
            new Column('id', 'ID', sortable: true, type: 'number'),
            new Column('title', 'Title', sortable: true),
            new Column('status', 'Status', type: 'badge'),
            new Column('updated_at', 'Updated', sortable: true, type: 'date'),
        ];

        return Inertia::render('Entries/Index', [
            'collection' => $collection->only('id', 'handle', 'title', 'settings'),
            'columns' => $columns,
            'rows' => $table->paginate($request)->through(function (Model $e): array {
                /** @var Entry $e */
                $t = $e->translations->first();

                return [
                    'id' => $e->id,
                    'title' => $t === null ? '—' : $t->title,
                    'status' => $e->trashed() ? 'trashed' : $e->status,
                    'updated_at' => $e->updated_at?->diffForHumans(),
                ];
            }),
            'meta' => $table->meta(),
            'visibleColumns' => TablePreferencesController::columnsFor($userId, 'entries', ['id', 'title', 'status', 'updated_at']),
            'can' => [
                'create' => $request->user()->can('create', [Entry::class, $collection->id]),
            ],
        ]);
    }

    public function export(Request $request, Collection $collection, CsvExporter $csv): StreamedResponse
    {
        $this->authorize('viewAny', [Entry::class, $collection->id]);

        $table = TableQuery::for(
            Entry::query()->where('collection_id', $collection->id)->with('translations')
        )->filterable(['status'])->apply($request);

        return $csv->download($table, [
            new Column('id', 'ID'),
            new Column('translations.0.title', 'Title'),
            new Column('status', 'Status'),
            new Column('published_at', 'Published at'),
        ], "{$collection->handle}-entries.csv");
    }

    public function create(Collection $collection): Response
    {
        $this->authorize('create', [Entry::class, $collection->id]);

        return Inertia::render('Entries/Edit', $this->editorProps($collection, null));
    }

    public function store(Request $request, Collection $collection, CreateEntry $create): RedirectResponse
    {
        $this->authorize('create', [Entry::class, $collection->id]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('sunrice_entry_translations', 'slug')
                    ->where(fn ($q) => $q->where('collection_id', $collection->id)->where('locale', Locales::main())),
            ],
            'data' => ['array'],
            'seo' => ['array'],
            'blueprint_id' => ['nullable', 'integer', 'exists:sunrice_blueprints,id'],
        ]);

        $entry = $create->handle($collection, $validated, $request->user()->getAuthIdentifier(), $validated['blueprint_id'] ?? null);

        return redirect()->route('sunrice.admin.entries.edit', $entry)
            ->with('success', 'Entry created.');
    }

    public function edit(Entry $entry): Response
    {
        $this->authorize('view', $entry);

        return Inertia::render('Entries/Edit', $this->editorProps($entry->collection, $entry));
    }

    public function update(Request $request, Entry $entry, SaveDraft $saveDraft): RedirectResponse
    {
        // The main language needs edit rights; other languages also accept
        // the translate permission.
        $this->authorize(Locales::isMain((string) $request->input('locale', Locales::main())) ? 'update' : 'translate', $entry);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('sunrice_entry_translations', 'slug')
                    ->where(fn ($q) => $q
                        ->where('collection_id', $entry->collection_id)
                        ->where('locale', $request->input('locale', Locales::main())))
                    ->ignore($entry->id, 'entry_id'),
            ],
            'data' => ['array'],
            'seo' => ['array'],
            'is_ready' => ['nullable', 'boolean'],
        ]);

        // Marking a translation Ready puts it live, which translate-only
        // users can't do.
        if (! $request->user()->can('update', $entry)) {
            unset($validated['is_ready']);
        }

        $translation = $this->translationFor($entry, $validated['locale']);
        $saveDraft->handle($translation, $validated);

        return back()->with('success', 'Draft saved.');
    }

    public function destroy(Entry $entry, TrashEntry $trash): RedirectResponse
    {
        $this->authorize('delete', $entry);
        $trash->handle($entry);

        return back()->with('success', 'Entry moved to trash.');
    }

    public function restore(Entry $entry, RestoreEntry $restore): RedirectResponse
    {
        $this->authorize('delete', $entry);
        $restore->handle($entry);

        return back()->with('success', 'Entry restored.');
    }

    public function forceDelete(Entry $entry, ForceDeleteEntry $delete): RedirectResponse
    {
        $this->authorize('delete', $entry);
        $delete->handle($entry);

        return redirect()->route('sunrice.admin.entries.index', $entry->collection)->with('success', 'Entry deleted permanently.');
    }

    public function publish(Request $request, Entry $entry, PublishTranslation $publish): RedirectResponse
    {
        $this->authorize('publish', $entry);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'published_at' => ['nullable', 'date'],
        ]);

        $translation = $this->translationFor($entry, $validated['locale']);
        $publish->handle($translation, $validated['published_at'] ?? null);

        return back()->with('success', 'Published.');
    }

    public function unpublish(Entry $entry, UnpublishEntry $unpublish): RedirectResponse
    {
        $this->authorize('publish', $entry);
        $unpublish->handle($entry);

        return back()->with('success', 'Entry unpublished.');
    }

    public function duplicate(Entry $entry, DuplicateEntry $duplicate): RedirectResponse
    {
        $this->authorize('create', [Entry::class, $entry->collection_id]);
        $copy = $duplicate->handle($entry);

        return redirect()->route('sunrice.admin.entries.edit', $copy)->with('success', 'Entry duplicated.');
    }

    public function changeBlueprint(Request $request, Entry $entry, ChangeBlueprint $change): RedirectResponse
    {
        $this->authorize('update', $entry);

        $validated = $request->validate(['blueprint_id' => ['nullable', 'integer', 'exists:sunrice_blueprints,id']]);
        $blueprint = isset($validated['blueprint_id']) ? Blueprint::find($validated['blueprint_id']) : null;
        $change->handle($entry, $blueprint);

        return back()->with('success', 'Blueprint changed.');
    }

    public function returnToDraft(EntryTranslation $translation, ReturnTranslationToDraft $action): RedirectResponse
    {
        $this->authorize('update', $translation->entry);
        $action->handle($translation);

        return back()->with('success', 'Translation returned to draft.');
    }

    public function restoreRevision(Revision $revision, RestoreRevision $action): RedirectResponse
    {
        $translation = $revision->translation;
        $this->authorize(Locales::isMain($translation->locale) ? 'update' : 'translate', $translation->entry);
        $action->handle($revision);

        return back()->with('success', 'Revision restored to draft.');
    }

    public function reorder(Request $request, Collection $collection, Reorder $reorder): RedirectResponse
    {
        $this->authorize('reorder', [Entry::class, $collection->id]);

        $validated = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer']]);
        // Only this collection's entries, in the posted order.
        $ids = array_map('intval', $validated['items']);
        $own = Entry::query()->where('collection_id', $collection->id)->whereIn('id', $ids)->pluck('id')->all();
        $reorder->handle(Entry::class, array_values(array_intersect($ids, $own)));

        return back();
    }

    public function bulk(Request $request, Collection $collection): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:trash,restore,delete,publish'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $entries = Entry::withTrashed()->whereIn('id', $validated['ids'])->get();

        $user = $request->user();

        foreach ($entries as $entry) {
            match ($validated['action']) {
                'trash' => $user->can('delete', $entry) ? app(TrashEntry::class)->handle($entry) : null,
                'restore' => $entry->trashed() && $user->can('delete', $entry) ? app(RestoreEntry::class)->handle($entry) : null,
                'delete' => $entry->trashed() && $user->can('delete', $entry) ? app(ForceDeleteEntry::class)->handle($entry) : null,
                'publish' => $user->can('publish', $entry) ? app(PublishTranslation::class)->handle($entry->mainTranslation()) : null,
                default => null,
            };
        }

        return back()->with('success', 'Done.');
    }

    public function preview(Request $request, Entry $entry): RedirectResponse
    {
        $this->authorize('view', $entry);

        $validated = $request->validate(['locale' => ['required', 'string']]);

        $url = URL::temporarySignedRoute(
            'sunrice.frontend.preview',
            now()->addMinutes(30),
            ['entry' => $entry->id, 'locale' => $validated['locale']],
        );

        return back()->with('success', 'Preview link created.')->with('preview_url', $url);
    }

    // ---- internals -----------------------------------------------------------

    /**
     * Standard SEO fields rendered as the `seo` admin tab (stored in the
     * translation's `seo` JSON).
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function seoFields(): array
    {
        return [
            ['handle' => 'title', 'type' => 'text', 'label' => 'Meta title'],
            ['handle' => 'description', 'type' => 'textarea', 'label' => 'Meta description'],
            ['handle' => 'canonical', 'type' => 'text', 'label' => 'Canonical URL'],
            ['handle' => 'image', 'type' => 'asset', 'label' => 'Open Graph image'],
            ['handle' => 'noindex', 'type' => 'toggle', 'label' => 'Hide from search engines (noindex)'],
        ];
    }

    protected function translationFor(Entry $entry, string $locale): EntryTranslation
    {
        abort_unless(Locales::isAvailable($locale), 422, 'Unknown locale.');

        $main = $entry->mainTranslation();

        return $entry->translations()->firstOrCreate(
            ['locale' => $locale],
            [
                'collection_id' => $entry->collection_id,
                'title' => $main === null ? '' : $main->title,
                'slug' => SlugValidator::unique($main === null ? 'entry' : $main->slug, $entry->collection_id, $locale),
                // Secondary languages store only translated values; until
                // the editor translates something, the main text shows.
                'data' => [],
                'seo' => $main === null ? [] : $main->seo,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function editorProps(Collection $collection, ?Entry $entry): array
    {
        $entry?->load(['translations.revisions', 'collection', 'terms']);
        $user = request()->user();

        $blueprint = $entry?->activeBlueprint() ?? $collection->blueprint;
        $translations = [];

        if ($entry !== null) {
            $main = $entry->mainTranslation();
            $mainData = (array) ($main->draft['data'] ?? $main->data ?? []);

            foreach ($entry->translations as $t) {
                $translations[$t->locale] = [
                    'id' => $t->id,
                    'title' => $t->title,
                    'slug' => $t->slug,
                    // Secondary languages edit their text in the main layout.
                    'data' => $entry->dataFor($t, (array) ($t->draft['data'] ?? $t->data ?? []), $mainData),
                    'seo' => $t->draft['seo'] ?? $t->seo ?? [],
                    'is_ready' => (bool) $t->is_ready,
                    'is_outdated' => $t->isOutdated(),
                    'has_draft' => $t->draft !== null,
                    'draft_title' => $t->draft['title'] ?? $t->title,
                    'draft_slug' => $t->draft['slug'] ?? $t->slug,
                    'revisions' => $t->revisions->map(fn (Revision $r) => [
                        'id' => $r->id,
                        'created_at' => $r->created_at?->diffForHumans(),
                        'user_id' => $r->user_id,
                    ])->values(),
                ];
            }
        }

        return [
            'collection' => $collection->only('id', 'handle', 'title', 'settings', 'blueprint_id'),
            'entry' => $entry === null ? null : [
                'id' => $entry->id,
                'status' => $entry->status,
                'published_at' => $entry->published_at?->toIso8601String(),
                'blueprint_id' => $entry->blueprint_id,
                'author_id' => $entry->author_id,
                'term_ids' => $entry->terms->pluck('id'),
                'translations' => $translations,
            ],
            'blueprint' => $blueprint === null ? null : array_merge(
                $blueprint->schema()->toAdminTabs(),
                [['handle' => 'seo', 'label' => 'SEO', 'fields' => static::seoFields()]],
            ),
            'blueprints' => Blueprint::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'taxonomies' => $collection->taxonomies->map(fn ($t) => $t->only('id', 'handle', 'title')),
            'locales' => Locales::available(),
            'mainLocale' => Locales::main(),
            'can' => $entry === null ? null : [
                'update' => $user->can('update', $entry),
                'translate' => $user->can('translate', $entry),
                'publish' => $user->can('publish', $entry),
                'delete' => $user->can('delete', $entry),
                'create' => $user->can('create', [Entry::class, $entry->collection_id]),
            ],
        ];
    }
}
