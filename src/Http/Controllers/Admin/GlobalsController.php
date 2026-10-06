<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Globals\SaveGlobalValues;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Blueprint;
use Sunrice\Models\GlobalSet;
use Sunrice\Support\Locales;

class GlobalsController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', GlobalSet::class);

        return Inertia::render('Globals/Index', [
            'globals' => GlobalSet::query()->with('blueprint:id,title')->orderBy('title')->get()
                ->map(fn (GlobalSet $g) => [
                    'id' => $g->id,
                    'handle' => $g->handle,
                    'title' => $g->title,
                    'group' => $g->group,
                    'translatable' => (bool) $g->translatable,
                    'blueprint' => $g->blueprint?->only('id', 'title'),
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', GlobalSet::class);

        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', GlobalSet::class);

        $validated = $request->validate([
            'handle' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:sunrice_globals,handle'],
            'title' => ['required', 'string', 'max:255'],
            'group' => ['required', Rule::in(['global', 'template_part'])],
            'blueprint_id' => ['required', 'integer', 'exists:sunrice_blueprints,id'],
            'translatable' => ['boolean'],
        ]);

        $set = GlobalSet::create($validated);
        ContentChanged::dispatch('global_saved');

        return redirect()->route('sunrice.admin.globals.edit', $set)->with('success', 'Global set created.');
    }

    public function edit(GlobalSet $globalSet): Response
    {
        $this->authorize('update', $globalSet);

        return $this->form($globalSet);
    }

    public function update(Request $request, GlobalSet $globalSet): RedirectResponse
    {
        $this->authorize('update', $globalSet);

        $validated = $request->validate([
            'locale' => ['nullable', 'string'],
            'values' => ['array'],
        ]);

        if (! $globalSet->translatable) {
            $validated['locale'] = null;
        } elseif (! Locales::isAvailable($validated['locale'] ?? '')) {
            return back()->with('error', 'Unknown language. Reload the page and try again.');
        }

        app(SaveGlobalValues::class)->handle($globalSet, $validated);
        ContentChanged::dispatch('global_saved');

        return back()->with('success', 'Saved.');
    }

    public function updateMeta(Request $request, GlobalSet $globalSet): RedirectResponse
    {
        $this->authorize('update', $globalSet);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'blueprint_id' => ['required', 'integer', 'exists:sunrice_blueprints,id'],
            'translatable' => ['boolean'],
        ]);

        $globalSet->update($validated);
        ContentChanged::dispatch('global_saved');

        return back()->with('success', 'Global settings saved.');
    }

    public function destroy(GlobalSet $globalSet): RedirectResponse
    {
        $this->authorize('delete', $globalSet);

        $globalSet->delete();
        ContentChanged::dispatch('global_saved');

        return redirect()->route('sunrice.admin.globals.index')->with('success', 'Global set deleted.');
    }

    protected function form(?GlobalSet $set): Response
    {
        return Inertia::render('Globals/Form', [
            'globalSet' => $set?->only('id', 'handle', 'title', 'group', 'blueprint_id', 'translatable'),
            'blueprint' => $set?->blueprint?->schema()->toAdminTabs(),
            'values' => $set?->values->keyBy(fn ($v) => $v->locale ?? '_shared')->map->data,
            'blueprints' => Blueprint::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'locales' => Locales::available(),
            'mainLocale' => Locales::main(),
        ]);
    }
}
