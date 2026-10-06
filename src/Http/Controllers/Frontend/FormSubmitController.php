<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Actions\Forms\SubmitForm;
use Sunrice\Models\Form;

/**
 * POST /sunrice/forms/{handle} — public form submissions.
 * Honeypot + rate limit are enforced by route middleware.
 */
class FormSubmitController extends Controller
{
    public function __invoke(Request $request, Form $form, SubmitForm $submit): RedirectResponse
    {
        $input = (array) $request->input('data', []);

        // Merge uploaded files into the input under their field handle.
        foreach ($form->schema()->fields() as $field) {
            if (($field['type'] ?? null) === 'file' && $request->hasFile('data.'.$field['handle'])) {
                $input[$field['handle']] = $request->file('data.'.$field['handle']);
            }
        }

        $submit->handle($form, $input, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
        ]);

        $redirect = $form->setting('redirect_url');
        if (is_string($redirect) && $redirect !== '') {
            return redirect($redirect);
        }

        return back()->with("sunrice_form_success.{$form->handle}", true);
    }
}
