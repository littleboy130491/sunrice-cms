<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Support\CsvCell;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
                // Shown in the submission's detail popup.
                'ip_address' => $s->getAttribute('ip_address'),
                'user_agent' => $s->getAttribute('user_agent'),
                'locale' => $s->getAttribute('locale'),
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
    public function download(FormSubmission $submission, string $field): HttpResponse
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

        // Sent whole (uploads are small and capped) rather than streamed: a
        // streamed download announces its size up front, and anything else
        // the server adds to the output (PHP output compression, a stray
        // blank line from a config file) then breaks it ("invalid
        // response"). As an attachment, so the browser never renders it.
        $name = basename($path);

        return response((string) $disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, Str::ascii($name) ?: 'download'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
