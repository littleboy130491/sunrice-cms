<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionsController extends Controller
{
    public function index(Request $request, Form $form): Response
    {
        Gate::authorize('viewSubmissions', $form);

        $query = $form->submissions()->orderByDesc('id');
        if ($search = $request->string('search')->toString()) {
            // `data` is a JSON column: cast to text for matching (pgsql
            // also requires ILIKE for case-insensitive search).
            $driver = $query->getModel()->getConnection()->getDriverName();
            $expr = match ($driver) {
                'pgsql' => '"data"::text',
                'mysql', 'mariadb' => 'CAST(`data` AS CHAR)',
                default => '"data"',
            };
            $query->whereRaw("{$expr} ".($driver === 'pgsql' ? 'ILIKE' : 'LIKE').' ?', ["%{$search}%"]);
        }
        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        return Inertia::render('Forms/Submissions', [
            'form' => $form->only('id', 'handle', 'title', 'fields'),
            'submissions' => $query->paginate(25)->withQueryString()->through(fn (FormSubmission $s) => [
                'id' => $s->id,
                'data' => $s->data,
                'created_at' => $s->created_at?->toIso8601String(),
            ]),
            'filters' => $request->only(['search', 'from', 'to']),
        ]);
    }

    public function show(FormSubmission $submission): JsonResponse
    {
        Gate::authorize('viewSubmissions', $submission->form);

        return response()->json($submission->only('id', 'form_id', 'data', 'meta', 'created_at'));
    }

    /**
     * Authorized download of a private form-upload file.
     */
    public function download(FormSubmission $submission, string $field): StreamedResponse
    {
        Gate::authorize('viewSubmissions', $submission->form);

        // Only file fields hold upload paths: any other value (e.g. text a
        // visitor typed) is never treated as one.
        $fileFields = array_filter($submission->form->schema()->fields(), fn (array $f) => ($f['type'] ?? null) === 'file');
        $isFileField = in_array($field, array_column($fileFields, 'handle'), true);
        $path = $submission->data[$field] ?? null;
        abort_unless(
            $isFileField
            && is_string($path)
            && str_starts_with($path, "form-uploads/{$submission->form->handle}/")
            && ! in_array('..', explode('/', str_replace('\\', '/', $path)), true),
            404,
        );

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('sunrice.forms.upload_disk'));
        abort_unless($disk->exists($path), 404, 'The uploaded file is missing from the "'.config('sunrice.forms.upload_disk').'" disk.');

        // Streamed through the disk (works for local and cloud disks alike),
        // as an attachment so the browser never renders it.
        return $disk->download($path, basename($path));
    }

    public function destroy(FormSubmission $submission): RedirectResponse
    {
        Gate::authorize('deleteSubmissions', $submission->form);

        // Its uploaded files go too, as when old submissions are pruned.
        $submission->pruning();
        $submission->delete();

        return back()->with('success', 'Submission deleted.');
    }

    public function export(Form $form): StreamedResponse
    {
        Gate::authorize('exportSubmissions', $form);

        return response()->streamDownload(function () use ($form): void {
            $out = fopen('php://output', 'w');
            $handles = array_map(fn (array $f) => $f['handle'], $form->schema()->fields());
            fputcsv($out, array_merge(['id', 'submitted_at'], $handles));
            $form->submissions()->orderBy('id')->chunk(200, function ($subs) use ($out, $handles): void {
                foreach ($subs as $sub) {
                    $row = [$sub->id, $sub->created_at?->toIso8601String()];
                    foreach ($handles as $handle) {
                        $value = $sub->data[$handle] ?? '';
                        $row[] = CsvCell::safe(is_scalar($value) ? $value : json_encode($value));
                    }
                    fputcsv($out, $row);
                }
            });
            fclose($out);
        }, "{$form->handle}-submissions.csv", ['Content-Type' => 'text/csv']);
    }
}
