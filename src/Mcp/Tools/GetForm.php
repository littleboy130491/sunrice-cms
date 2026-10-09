<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Support\Captcha;

#[IsReadOnly]
#[Description('Read a form (fields and settings) and its latest submissions (newest first, paged). Uploaded files are listed by path; staff download them in the admin.')]
class GetForm extends SunriceTool
{
    protected string $name = 'get_form';

    public function handle(Request $request): Response
    {
        $args = $request->validate(['handle' => ['required'], 'page' => ['nullable', 'integer', 'min:1']]);
        $form = $this->findByHandle(Form::class, $args['handle']);
        if ($form === null) {
            return $this->notFound('Form');
        }

        $out = [
            'handle' => $form->handle,
            'title' => $form->title,
            'fields' => $form->fields,
            'settings' => $form->settings,
            // Whether captcha keys are set in .env (settings.captcha only works then).
            'captcha_available' => Captcha::configured(),
            'template_usage' => "<x-sunrice::form handle=\"{$form->handle}\" />",
        ];
        if (Gate::allows('viewSubmissions', $form)) {
            $page = $form->submissions()->latest('id')->paginate(25, ['*'], 'page', (int) ($args['page'] ?? 1));
            $out['submissions'] = [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'items' => $page->getCollection()->map(fn (FormSubmission $s) => [
                    'id' => $s->id,
                    'data' => $s->data,
                    'submitted_at' => $s->created_at?->toIso8601String(),
                    'locale' => $s->getAttribute('locale'),
                ])->all(),
            ];
        }

        return $this->json($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'handle' => $schema->string()->required(),
            'page' => $schema->integer()->description('Submissions page.'),
        ];
    }
}
