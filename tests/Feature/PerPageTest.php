<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;

use function Pest\Laravel\get;
use function Pest\Laravel\putJson;

beforeEach(function () {
    actingAsSuperAdmin();
});

it('remembers rows per page for each table', function () {
    $pages = createCollection('pages');
    foreach (range(1, 12) as $i) {
        createEntry($pages, "Page {$i}");
    }

    get('/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page->where('rows.per_page', 20));

    putJson('/cms/table-preferences/entries-pages', ['per_page' => 10])->assertOk()->assertJson(['per_page' => 10]);

    get('/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page
        ->where('rows.per_page', 10)
        ->where('rows.last_page', 2)
        ->has('rows.data', 10));
    // The URL still wins, and stays within 1–100.
    get('/cms/collections/pages/entries?per_page=5')->assertInertia(fn (Assert $page) => $page->where('rows.per_page', 5));
    get('/cms/collections/pages/entries?per_page=5000')->assertInertia(fn (Assert $page) => $page->where('rows.per_page', 100));

    // Saving rows per page leaves the column choice alone, and vice versa.
    putJson('/cms/table-preferences/entries-pages', ['columns' => ['title']])->assertOk();
    get('/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page->where('rows.per_page', 10)->where('visibleColumns', ['title']));
});

it('validates the rows per page choice', function () {
    putJson('/cms/table-preferences/entries-pages', ['per_page' => 0])->assertJsonValidationErrors('per_page');
    putJson('/cms/table-preferences/entries-pages', ['per_page' => 101])->assertJsonValidationErrors('per_page');
    putJson('/cms/table-preferences/entries-pages', [])->assertJsonValidationErrors(['columns', 'per_page']);
});

it('pages form submissions and assets by the saved choice', function () {
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => []]);
    foreach (range(1, 12) as $i) {
        FormSubmission::query()->create(['form_id' => $form->id, 'data' => ['n' => $i]]);
    }

    get("/cms/forms/{$form->id}/submissions")->assertInertia(fn (Assert $page) => $page->where('submissions.per_page', 25));
    putJson('/cms/table-preferences/submissions-contact', ['per_page' => 10])->assertOk();
    get("/cms/forms/{$form->id}/submissions")->assertInertia(fn (Assert $page) => $page->where('submissions.per_page', 10)->has('submissions.data', 10));

    putJson('/cms/table-preferences/assets', ['per_page' => 48])->assertOk();
    get('/cms/assets')->assertInertia(fn (Assert $page) => $page->where('assets.per_page', 48));
});
