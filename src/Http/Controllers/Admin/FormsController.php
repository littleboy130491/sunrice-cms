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
use Sunrice\Support\Captcha;

class FormsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('sunrice.access-admin');

        return Inertia::render('Forms/Index', [
            'forms' => Form::query()->withCount('submissions')->orderBy('handle')->get()
                ->filter(fn (Form $form) => request()->user()->can('view', $form))
                ->map(fn (Form $form) => [
                    'id' => $form->id,
                    'handle' => $form->handle,
                    'title' => $form->title,
                    'submissions_count' => $form->submissions_count,
                    'can' => [
                        'edit' => request()->user()->can('update', $form),
                        'submissions' => request()->user()->can('viewSubmissions', $form),
                        'delete' => request()->user()->can('delete', $form),
                    ],
                ])->values()->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Form::class);

        return Inertia::render('Forms/Form', ['form' => null, 'fieldTypes' => $this->fieldTypes(), 'captcha' => $this->captcha()]);
    }

    public function store(Request $request, SaveForm $save): RedirectResponse
    {
        Gate::authorize('create', Form::class);

        $form = $save->handle(null, $request->all());

        return redirect()->route('sunrice.admin.forms.edit', $form)->with('success', "Form \"{$form->title}\" created.");
    }

    /** The editor's older address (/forms/{handle}). */
    public function legacyEdit(Form $form): RedirectResponse
    {
        return redirect()->route('sunrice.admin.forms.edit', $form, 301);
    }

    public function edit(Form $form): Response
    {
        Gate::authorize('update', $form);

        return Inertia::render('Forms/Form', [
            'form' => $form->only('id', 'handle', 'title', 'fields', 'settings'),
            'fieldTypes' => $this->fieldTypes(),
            'captcha' => $this->captcha(),
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

    /**
     * Whether captcha keys are set in .env, and for which provider.
     *
     * @return array{configured: bool, provider: string|null}
     */
    protected function captcha(): array
    {
        return ['configured' => Captcha::configured(), 'provider' => Captcha::label()];
    }

    public function destroy(Form $form, DeleteForm $delete): RedirectResponse
    {
        Gate::authorize('delete', $form);

        $delete->handle($form);

        return redirect()->route('sunrice.admin.forms.index')->with('success', 'Form deleted.');
    }
}
