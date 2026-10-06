<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
use Sunrice\Events\ContentChanged;
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
        $table = $this->entriesTable($request, $collection, $query);
        $meta = $table->meta();
        [$defaultColumn] = $collection->defaultSort();

        // Drag-and-drop when the collection is ordered manually and the
        // list shows that order unfiltered.
        $manual = $defaultColumn === 'sort_order';
        $mayReorder = $manual && $request->user()->can('reorder', [Entry::class, $collection->id]);
        $canReorder = $mayReorder
            && in_array($meta['sort'], [null, 'sort_order'], true)
            && $meta['search'] === null
            && $meta['filters'] === [];

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
                    'status' => match (true) {
                        $e->trashed() => 'trashed',
                        $e->status === 'published' && $e->published_at?->isFuture() => 'scheduled',
                        default => $e->status,
                    },
                    'updated_at' => $e->updated_at?->diffForHumans(),
                ];
            }),
            'meta' => $meta,
            // Ordered by hand, but a search, filter or column sort hides that order.
            'reorderPaused' => $mayReorder && ! $canReorder,
            'visibleColumns' => TablePreferencesController::columnsFor($userId, 'entries', ['id', 'title', 'status', 'updated_at']),
            'can' => [
                'create' => $request->user()->can('create', [Entry::class, $collection->id]),
                'reorder' => $canReorder,
                // The listing page editor (heading, intro, listing blueprint fields).
                'listing' => $collection->setting('has_archive', false)
                    && ($request->user()->can("sunrice.entries.{$collection->id}.edit") || $request->user()->can("sunrice.entries.{$collection->id}.translate")),
            ],
        ]);
    }

    /**
     * The entries list query: search, status filter and sorting from the
     * request, else the collection's own order. Shared by the list and
     * its CSV export so both show the same entries.
     *
     * @param  Builder<Entry>  $query
     */
    protected function entriesTable(Request $request, Collection $collection, Builder $query): TableQuery
    {
        $main = Locales::main();
        $mainTitle = EntryTranslation::query()
            ->select('title')
            ->whereColumn('entry_id', (new Entry)->qualifyColumn('id'))
            ->where('locale', $main)
            ->limit(1);

        $table = TableQuery::for($query)
            ->searchUsing(function (Builder $q, string $term): void {
                $q->where(function (Builder $q) use ($term): void {
                    $q->whereHas('translations', fn (Builder $t) => $t->whereLike('title', "%{$term}%"));
                    if (ctype_digit($term)) {
                        $q->orWhere('id', (int) $term);
                    }
                });
            })
            // Scheduled = published with a future date; Published = live now.
            ->filter('status', fn (Builder $q, mixed $value) => match ($value) {
                'published' => $q->where('status', 'published')->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now())),
                'scheduled' => $q->where('status', 'published')->where('published_at', '>', now()),
                default => $q->where('status', $value),
            })
            ->sortUsing('title', fn (Builder $q, string $direction) => $q->orderBy($mainTitle, $direction))
            ->sortable(['id', 'published_at', 'sort_order', 'created_at', 'updated_at'])
            ->apply($request);

        // No column clicked: the collection's own order (Structure →
        // Collections → Order).
        [$defaultColumn, $defaultDirection] = $collection->defaultSort();
        $meta = $table->meta();
        if ($meta['sort'] === null) {
            $defaultColumn === 'title'
                ? $query->orderBy($mainTitle, $defaultDirection)
                : $query->orderBy($defaultColumn, $defaultDirection);
            $query->orderBy('id', $defaultColumn === 'sort_order' ? 'asc' : 'desc');
        }

        return $table;
    }

    public function export(Request $request, Collection $collection, CsvExporter $csv): StreamedResponse
    {
        $this->authorize('viewAny', [Entry::class, $collection->id]);

        $table = $this->entriesTable($request, $collection, Entry::query()->where('collection_id', $collection->id)->with('translations'));

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

        // The editor can't show a trashed entry: go back to the list.
        return redirect()->route('sunrice.admin.entries.index', $entry->collection)->with('success', 'Entry moved to trash.');
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

    /**
     * Change only the publish date of a published entry (reschedule or
     * backdate) without publishing the current draft.
     */
    public function publishDate(Request $request, Entry $entry): RedirectResponse
    {
        $this->authorize('publish', $entry);
        $validated = $request->validate(['published_at' => ['required', 'date']]);

        if ($entry->status !== 'published') {
            return back()->with('error', 'Publish the entry first, then change its date.');
        }

        $entry->published_at = Carbon::parse($validated['published_at']);
        $entry->save();
        ContentChanged::dispatch('entry_saved');

        return back()->with('success', $entry->published_at->isFuture() ? 'Entry scheduled.' : 'Publish date changed.');
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
        try {
            $action->handle($translation);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Translation returned to draft.');
    }

    public function restoreRevision(Revision $revision, RestoreRevision $action): RedirectResponse
    {
        $translation = $revision->translation;
        $this->authorize(Locales::isMain($translation->locale) ? 'update' : 'translate', $translation->entry);

        // Keep the draft being replaced so the restore can be undone.
        session()->put(static::undoKey($translation), ['draft' => $translation->draft]);
        $action->handle($revision);

        return back()->with('success', 'Revision restored to draft.');
    }

    /**
     * Put back the draft a revision restore replaced (this session only).
     */
    public function undoRestore(EntryTranslation $translation): RedirectResponse
    {
        $this->authorize(Locales::isMain($translation->locale) ? 'update' : 'translate', $translation->entry);

        $key = static::undoKey($translation);
        if (! session()->has($key)) {
            return back()->with('error', 'There is no restore to undo.');
        }

        $translation->draft = session()->pull($key)['draft'] ?? null;
        $translation->save();

        return back()->with('success', 'Restore undone: your previous draft is back.');
    }

    protected static function undoKey(EntryTranslation $translation): string
    {
        return "sunrice.undo_restore.{$translation->id}";
    }

    public function reorder(Request $request, Collection $collection, Reorder $reorder): RedirectResponse
    {
        $this->authorize('reorder', [Entry::class, $collection->id]);

        $validated = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer']]);

        // The list is paginated: `items` is one page in its new order. Keep
        // every other entry where it is and put these in the slots they
        // occupied, then number the whole collection 1..n.
        $moved = array_map('intval', $validated['items']);
        $all = Entry::withTrashed()->where('collection_id', $collection->id)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $moved = array_values(array_intersect($moved, $all));
        $slots = array_keys(array_intersect($all, $moved));
        foreach ($slots as $i => $slot) {
            $all[$slot] = $moved[$i];
        }
        if ($all !== []) {
            $reorder->handle(Entry::class, array_combine(range(1, count($all)), $all));
        }
        ContentChanged::dispatch('entry_saved');

        return back()->with('success', 'Order saved.');
    }

    public function bulk(Request $request, Collection $collection): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:trash,restore,delete,publish'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $entries = Entry::withTrashed()
            ->where('collection_id', $collection->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        $user = $request->user();

        $done = 0;
        foreach ($entries as $entry) {
            $allowed = match ($validated['action']) {
                'trash' => ! $entry->trashed() && $user->can('delete', $entry),
                'restore', 'delete' => $entry->trashed() && $user->can('delete', $entry),
                'publish' => ! $entry->trashed() && $entry->mainTranslation() !== null && $user->can('publish', $entry),
                default => false,
            };
            if (! $allowed) {
                continue;
            }
            switch ($validated['action']) {
                case 'trash':
                    app(TrashEntry::class)->handle($entry);
                    break;
                case 'restore':
                    app(RestoreEntry::class)->handle($entry);
                    break;
                case 'delete':
                    app(ForceDeleteEntry::class)->handle($entry);
                    break;
                case 'publish':
                    app(PublishTranslation::class)->handle($entry->mainTranslation());
                    break;
            }
            $done++;
        }

        $template = ['trash' => 'Moved %d %s to trash.', 'restore' => 'Restored %d %s.', 'delete' => 'Deleted %d %s permanently.', 'publish' => 'Published %d %s.'][$validated['action']];
        $message = sprintf($template, $done, $done === 1 ? 'entry' : 'entries');
        $skipped = count($validated['ids']) - $done;

        return $done === 0
            ? back()->with('error', 'Nothing changed: you may not have permission for the selected entries.')
            : back()->with('success', $skipped > 0 ? "{$message} {$skipped} skipped." : $message);
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
        if (! Locales::isAvailable($locale)) {
            throw ValidationException::withMessages(['locale' => 'Unknown language. Reload the page and try again.']);
        }

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
                    'can_undo_restore' => session()->has(static::undoKey($t)),
                    'revisions' => $t->revisions->map(fn (Revision $r) => [
                        'id' => $r->id,
                        'created_at' => $r->created_at?->diffForHumans(),
                        'created_at_iso' => $r->created_at?->toIso8601String(),
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
