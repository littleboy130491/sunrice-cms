<?php

declare(strict_types=1);

namespace Sunrice\Actions\Forms;

use Illuminate\Support\Facades\Storage;
use Sunrice\Events\ContentChanged;
use Sunrice\Models\Form;
use Sunrice\Permissions\SyncPermissions;

/**
 * Deletes a form. Submissions are removed via the cascade on
 * sunrice_form_submissions.form_id; uploaded files under
 * form-uploads/{handle} are cleaned up too.
 */
class DeleteForm
{
    public function handle(Form $form): void
    {
        $form->delete();

        Storage::disk(config('sunrice.forms.upload_disk'))
            ->deleteDirectory("form-uploads/{$form->handle}");

        app(SyncPermissions::class)->handle();
        ContentChanged::dispatch('form_deleted');
    }
}
