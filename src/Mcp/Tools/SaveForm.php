<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Forms\SaveForm as SaveFormAction;
use Sunrice\Models\Form;

#[Description('Create or update a form (contact, newsletter, application…), found by handle. fields: the complete list [{handle, type: text|textarea|select|toggle|date|number|file, label, required, validation (Laravel rules, e.g. ["email"]), config (select: options [{value,label}]; file: mimes ["pdf"], max_kb)}]. settings: notify_emails (comma separated), success_message, redirect_url, captcha (true to require the captcha; works only when captcha keys are set in .env). Show it in a template with <x-sunrice::form handle="…" />.')]
class SaveForm extends SunriceTool
{
    protected string $name = 'save_form';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'handle' => ['required', 'string'],
            'title' => ['nullable', 'string'],
            'fields' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);
        $form = Form::query()->where('handle', $args['handle'])->first();
        $this->authorize($form === null ? 'create' : 'update', $form ?? Form::class);

        $saved = app(SaveFormAction::class)->handle($form, [
            'handle' => $args['handle'],
            'title' => $args['title'] ?? ($form === null ? ucfirst($args['handle']) : $form->title),
            'fields' => $args['fields'] ?? ($form === null ? [] : $form->fields),
            'settings' => array_merge($form === null ? [] : ($form->settings ?? []), $args['settings'] ?? []),
        ]);

        return $this->json(['saved' => true, 'created' => $form === null, 'handle' => $saved->handle, 'fields' => $saved->fields, 'settings' => $saved->settings]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
            'title' => $schema->string(),
            'fields' => $schema->array()->items($schema->object())->description('All fields (replaces the list).'),
            'settings' => $schema->object()->description('Merged into the current settings.'),
        ];
    }
}
