<?php

declare(strict_types=1);

namespace Sunrice\Actions\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Sunrice\Mail\FormSubmittedNotification;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;

/**
 * Validates a public form submission against the form's field schema,
 * stores uploaded files on the private forms disk, records the
 * submission and queues the notification email.
 */
class SubmitForm
{
    /**
     * @param  array<string, mixed>  $input  Field values keyed by handle (files as UploadedFile).
     * @param  array<string, mixed>  $meta  ip, user_agent, referrer, ...
     */
    public function handle(Form $form, array $input, array $meta = []): FormSubmission
    {
        $schema = $form->schema();

        $data = Validator::make(['data' => $input], $schema->rules('data'))->validate()['data'];
        $data = $schema->normalize($data);

        // Store uploaded files on the private forms disk.
        foreach ($schema->fields() as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }
            $file = $data[$field['handle']] ?? null;
            if ($file instanceof UploadedFile) {
                $data[$field['handle']] = $file->store(
                    "form-uploads/{$form->handle}",
                    config('sunrice.forms.upload_disk')
                );
            }
        }

        $submission = $form->submissions()->create([
            'data' => $data,
            'ip_address' => $meta['ip'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'locale' => $meta['locale'] ?? null,
        ]);

        $emails = $form->setting('notify_emails');
        if (is_string($emails) && trim($emails) !== '') {
            Mail::to(array_map('trim', explode(',', $emails)))
                ->queue(new FormSubmittedNotification($form, $submission));
        }

        return $submission;
    }
}
