<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;

#[Description('Delete form submissions permanently, with their uploaded files (e.g. spam, or someone asking for their data to be removed). Give the form handle and the submission ids (from get_form). Ask the user before deleting.')]
class DeleteSubmissions extends SunriceTool
{
    protected string $name = 'delete_submissions';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'form' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);
        $form = $this->findByHandle(Form::class, $args['form']);
        if ($form === null) {
            return $this->notFound('Form');
        }
        $this->authorize('deleteSubmissions', $form);

        $submissions = FormSubmission::query()->where('form_id', $form->id)->whereIn('id', $args['ids'])->get();
        foreach ($submissions as $submission) {
            // Its uploaded files go too, as when old submissions are pruned.
            $submission->pruning();
            $submission->delete();
        }

        return $this->json([
            'deleted' => $submissions->pluck('id')->all(),
            'not_found' => array_values(array_diff(array_map('intval', $args['ids']), $submissions->pluck('id')->all())),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->string()->description('Form handle.')->required(),
            'ids' => $schema->array()->items($schema->integer())->description('Submission ids.')->required(),
        ];
    }
}
