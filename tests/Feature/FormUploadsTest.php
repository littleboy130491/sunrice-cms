<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Actions\Forms\SaveForm;
use Sunrice\Actions\Forms\SubmitForm;
use Sunrice\Fields\Types\File;
use Sunrice\Models\FormSubmission;

use function Pest\Laravel\get;

beforeEach(function () {
    actingAsSuperAdmin();
    Storage::fake(config('sunrice.forms.upload_disk'));
    $this->form = app(SaveForm::class)->handle(null, ['handle' => 'apply', 'title' => 'Apply', 'fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'cv', 'type' => 'file', 'label' => 'CV', 'config' => ['mimes' => ['pdf', 'docx'], 'max_kb' => 2048]],
    ]]);
});

/** The validation message for one field, from a failing submission. */
function uploadError(array $input, string $field = 'cv'): string
{
    try {
        app(SubmitForm::class)->handle(test()->form, $input);
    } catch (ValidationException $e) {
        return (string) ($e->errors()["data.{$field}"][0] ?? '');
    }

    return '';
}

it('stores accepted uploads and serves them to staff', function () {
    $submission = app(SubmitForm::class)->handle($this->form, ['name' => 'Ada', 'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')]);

    expect($submission->data['cv'])->toStartWith('form-uploads/apply/');
    Storage::disk(config('sunrice.forms.upload_disk'))->assertExists($submission->data['cv']);

    $response = get("/cms/submissions/{$submission->id}/download/cv")->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

it('explains a missing upload instead of failing', function () {
    $submission = FormSubmission::query()->create(['form_id' => $this->form->id, 'data' => ['cv' => 'form-uploads/apply/gone.pdf']]);

    get("/cms/submissions/{$submission->id}/download/cv")->assertNotFound();
});

it('gives readable upload errors in the visitor\'s language, without the app\'s validation lines', function () {
    // Wrong type: the message names the accepted ones (not "validation.mimes").
    expect(uploadError(['cv' => UploadedFile::fake()->create('cv.png', 10, 'image/png')]))
        ->toBe('This file type isn\'t accepted. Please upload: PDF, DOCX.');

    expect(uploadError(['cv' => UploadedFile::fake()->create('cv.pdf', 3000, 'application/pdf')]))
        ->toBe('The file is too large. The maximum is 2 MB.');

    app()->setLocale('id');
    expect(uploadError(['cv' => UploadedFile::fake()->create('cv.png', 10, 'image/png')]))
        ->toBe('Jenis berkas ini tidak diterima. Unggah berkas: PDF, DOCX.');
});

it('never accepts files that could run as code, even when any type is allowed', function () {
    $open = app(SaveForm::class)->handle(null, ['handle' => 'open', 'title' => 'Open', 'fields' => [['handle' => 'file', 'type' => 'file']]]);

    expect(fn () => app(SubmitForm::class)->handle($open, ['file' => UploadedFile::fake()->create('shell.php', 1, 'text/plain')]))
        ->toThrow(ValidationException::class);
    expect(app(SubmitForm::class)->handle($open, ['file' => UploadedFile::fake()->create('plan.dwg', 1)])->data['file'])->toBeString();
});

it('validates upload settings in the form builder', function () {
    $save = fn (array $config) => app(SaveForm::class)->handle($this->form, ['title' => 'Apply', 'fields' => [['handle' => 'cv', 'type' => 'file', 'config' => $config]]]);

    expect(fn () => $save(['mimes' => ['php']]))->toThrow(ValidationException::class)
        ->and(fn () => $save(['mimes' => ['p d f']]))->toThrow(ValidationException::class)
        ->and(fn () => $save(['max_kb' => 0]))->toThrow(ValidationException::class);

    expect($save(['mimes' => ['pdf', 'jpg'], 'max_kb' => 5120])->fields[0]['config'])->toBe(['mimes' => ['pdf', 'jpg'], 'max_kb' => 5120]);
});

it('describes the field for the builder and the public form', function () {
    $field = ['type' => 'file', 'config' => ['mimes' => ['.PDF', 'jpg'], 'max_kb' => 5120]];

    expect(File::acceptedTypes($field))->toBe(['pdf', 'jpg'])
        ->and(File::acceptAttribute($field))->toBe('.pdf,.jpg')
        ->and(File::formatKilobytes(5120))->toBe('5 MB')
        ->and(File::formatKilobytes(1536))->toBe('1.5 MB')
        ->and(File::formatKilobytes(500))->toBe('500 KB');

    $settings = collect((new File)->settingsSchema())->keyBy('handle');
    expect($settings['mimes']['type'])->toBe('file_types')
        ->and($settings['mimes']['options']['groups'])->not->toBeEmpty()
        ->and($settings['max_kb']['options'])->toHaveKeys(['default_kb', 'server_kb', 'upload_max_filesize', 'post_max_size']);
});
