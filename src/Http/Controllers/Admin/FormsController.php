<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Forms\DeleteForm;
use Sunrice\Actions\Forms\SaveForm;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Models\Form;

class FormsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('sunrice.access-admin');

        return Inertia::render('Forms/Index', [
            'forms' => Form::query()->orderBy('handle')->get()
                ->map(fn (Form $form) => [
                    'id' => $form->id,
                    'handle' => $form->handle,
                    'title' => $form->title,
                    'submissions_count' => $form->submissions()->count(),
                ])->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Form::class);

        return Inertia::render('Forms/Form', ['form' => null, 'fieldTypes' => $this->fieldTypes()]);
    }

    public function store(Request $request, SaveForm $save): RedirectResponse
    {
        Gate::authorize('create', Form::class);

        $form = $save->handle(null, $request->all());

        return redirect("/cms/forms/{$form->handle}");
    }

    public function edit(Form $form): Response
    {
        Gate::authorize('update', $form);

        return Inertia::render('Forms/Form', [
            'form' => $form->only('id', 'handle', 'title', 'fields', 'settings'),
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function update(Request $request, Form $form, SaveForm $save): RedirectResponse
    {
        Gate::authorize('update', $form);

        $save->handle($form, $request->all());

        return back()->with('success', 'Form saved.');
    }

    /**
     * Field type defs limited to the types allowed on forms, for the builder.
     *
     * @return array<int, array{type: string, settings: array<int, mixed>}>
     */
    protected function fieldTypes(): array
    {
        $registry = app(FieldRegistry::class);
        $out = [];
        foreach (SaveForm::ALLOWED_TYPES as $type) {
            $out[] = ['type' => $type, 'settings' => $registry->get($type)->settingsSchema()];
        }

        return $out;
    }

    public function destroy(Form $form, DeleteForm $delete): RedirectResponse
    {
        Gate::authorize('delete', $form);

        $delete->handle($form);

        return redirect('/cms');
    }
}
