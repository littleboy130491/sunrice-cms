<?php

declare(strict_types=1);

namespace Sunrice\Actions\Forms;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Form;
use Sunrice\Permissions\SyncPermissions;

/**
 * Creates or updates a form definition: title, handle, fields and
 * settings (notify_emails, success_message, redirect_url).
 */
class SaveForm
{
    /** Field types allowed on public forms (containers and references excluded). */
    public const ALLOWED_TYPES = ['text', 'textarea', 'number', 'toggle', 'select', 'date', 'file'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(?Form $form, array $attributes): Form
    {
        $validated = Validator::make($attributes, [
            'handle' => [
                $form === null ? 'required' : 'sometimes',
                'alpha_dash', 'max:100',
                Rule::unique('sunrice_forms', 'handle')->ignore($form?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'fields' => ['required', 'array'],
            'fields.*.handle' => ['required', 'alpha_dash', 'max:100', 'distinct'],
            'fields.*.type' => ['required', Rule::in(static::ALLOWED_TYPES)],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['boolean'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.config' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'settings.notify_emails' => ['nullable', 'string', 'max:1000'],
            'settings.success_message' => ['nullable', 'string', 'max:1000'],
            'settings.redirect_url' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $form ??= new Form;
        $form->fill([
            'handle' => $validated['handle'] ?? $form->handle,
            'title' => $validated['title'],
            'fields' => Arr::get($validated, 'fields', $form->fields ?? []),
            'settings' => Arr::get($validated, 'settings', $form->settings ?? []),
        ]);
        $form->save();

        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('form_saved');

        return $form->refresh();
    }
}
