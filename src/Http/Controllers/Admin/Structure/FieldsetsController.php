<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Structure;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Structure\DeleteFieldset;
use Sunrice\Actions\Structure\SaveFieldset;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Models\Fieldset;

class FieldsetsController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Fieldset::class, 'fieldset');
    }

    public function index(): Response
    {
        return Inertia::render('Structure/Fieldsets/Index', [
            'fieldsets' => Fieldset::query()->orderBy('title')->get()
                ->map(fn (Fieldset $f) => [
                    'id' => $f->id,
                    'handle' => $f->handle,
                    'title' => $f->title,
                    'fields_count' => count($f->fields ?? []),
                ]),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request, SaveFieldset $save): RedirectResponse
    {
        $fieldset = $save->handle($request->all());

        return redirect()->route('sunrice.admin.structure.fieldsets.edit', $fieldset)
            ->with('success', "Fieldset \"{$fieldset->title}\" created.");
    }

    public function edit(Fieldset $fieldset): Response
    {
        return $this->form($fieldset);
    }

    public function update(Request $request, Fieldset $fieldset, SaveFieldset $save): RedirectResponse
    {
        $save->handle($request->all(), $fieldset);

        return back()->with('success', "Fieldset \"{$fieldset->title}\" saved.");
    }

    public function destroy(Fieldset $fieldset, DeleteFieldset $delete): RedirectResponse
    {
        try {
            $delete->handle($fieldset);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sunrice.admin.structure.fieldsets.index')
            ->with('success', "Fieldset \"{$fieldset->title}\" deleted.");
    }

    protected function form(?Fieldset $fieldset): Response
    {
        $registry = app(FieldRegistry::class);

        return Inertia::render('Structure/Fieldsets/Form', [
            'fieldset' => $fieldset?->only('id', 'handle', 'title', 'fields'),
            'fieldsets' => Fieldset::query()->orderBy('title')->get(['id', 'title', 'handle']),
            'fieldTypes' => collect($registry->all())->map(fn ($t) => [
                'type' => $t->type(),
                'translatable' => $t->translatableByDefault(),
                'settings' => $t->settingsSchema(),
            ])->values(),
        ]);
    }
}
