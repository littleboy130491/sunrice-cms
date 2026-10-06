<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Actions\Forms\DeleteForm;
use Sunrice\Actions\Forms\SaveForm;
use Sunrice\Actions\Forms\SubmitForm;
use Sunrice\Mail\FormSubmittedNotification;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;

use function Pest\Laravel\artisan;
use function Pest\Laravel\post;

beforeEach(function () {
    actingAsSuperAdmin();
});

function makeForm(array $fields = [], array $settings = []): Form
{
    return app(SaveForm::class)->handle(null, [
        'handle' => 'contact',
        'title' => 'Contact us',
        'fields' => $fields ?: [
            ['handle' => 'name', 'type' => 'text', 'required' => true],
            ['handle' => 'email', 'type' => 'text', 'required' => true, 'validation' => ['email']],
            ['handle' => 'message', 'type' => 'textarea', 'required' => false],
        ],
        'settings' => $settings,
    ]);
}

it('saves and deletes forms and syncs permissions', function () {
    $form = makeForm();

    expect($form->handle)->toBe('contact');
    expect(auth()->user()->can("sunrice.forms.{$form->id}.view-submissions"))->toBeTrue();

    app(DeleteForm::class)->handle($form);
    expect(Form::query()->find($form->id))->toBeNull();
});

it('validates submissions against the field schema and stores them', function () {
    $form = makeForm();

    $submission = app(SubmitForm::class)->handle($form, [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'message' => 'Hi',
    ]);

    expect($submission->data['name'])->toBe('Ada')
        ->and(FormSubmission::query()->count())->toBe(1);
});

it('rejects submissions failing validation', function () {
    $form = makeForm();

    app(SubmitForm::class)->handle($form, ['name' => 'Ada', 'email' => 'not-an-email']);
})->throws(ValidationException::class);

it('stores file uploads on the private forms disk', function () {
    Storage::fake('local');
    $form = makeForm([['handle' => 'cv', 'type' => 'file', 'required' => true, 'config' => ['mimes' => 'txt', 'max_kb' => 100]]]);

    $submission = app(SubmitForm::class)->handle($form, [
        'cv' => UploadedFile::fake()->create('cv.txt', 5),
    ]);

    $path = $submission->data['cv'];
    expect($path)->toStartWith('form-uploads/contact/');
    Storage::disk('local')->assertExists($path);
});

it('queues the notification email to notify_emails', function () {
    Mail::fake();
    $form = makeForm([], ['notify_emails' => 'a@x.com, b@x.com']);

    app(SubmitForm::class)->handle($form, ['name' => 'Ada', 'email' => 'a@b.com']);

    Mail::assertQueued(FormSubmittedNotification::class);
});

it('accepts public POST submissions, rejects bad data and rate limits', function () {
    config()->set('honeypot.enabled', false);
    $form = makeForm();

    $payload = ['data' => ['name' => 'Ada', 'email' => 'a@b.com']];

    post("/sunrice/forms/{$form->handle}", $payload)->assertRedirect();
    expect(FormSubmission::query()->count())->toBe(1);

    post("/sunrice/forms/{$form->handle}", ['data' => ['name' => 'x']])
        ->assertSessionHasErrors();

    // 5/min limit: burn remaining then expect 429
    for ($i = 0; $i < 4; $i++) {
        post("/sunrice/forms/{$form->handle}", $payload);
    }
    post("/sunrice/forms/{$form->handle}", $payload)->assertStatus(429);
});

it('rejects filled honeypot fields', function () {
    $form = makeForm();

    $response = post("/sunrice/forms/{$form->handle}", [
        'data' => ['name' => 'Bot', 'email' => 'bot@x.com'],
        'my_name' => 'spam',
    ]);

    expect(FormSubmission::query()->count())->toBe(0);
});

it('deletes uploaded files when submissions are pruned', function () {
    Storage::fake('local');
    config()->set('sunrice.forms.prune_after_days', 30);
    $form = makeForm([['handle' => 'cv', 'type' => 'file', 'required' => true]]);

    $submission = app(SubmitForm::class)->handle($form, ['cv' => UploadedFile::fake()->create('cv.txt', 5)]);
    $submission->update(['created_at' => now()->subDays(60)]);
    $path = $submission->data['cv'];

    artisan('model:prune', ['--model' => FormSubmission::class])->assertSuccessful();

    Storage::disk('local')->assertMissing($path);
    expect(FormSubmission::query()->count())->toBe(0);
});
