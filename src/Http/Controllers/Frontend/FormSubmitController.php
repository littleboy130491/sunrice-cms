<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Sunrice\Actions\Forms\SubmitForm;
use Sunrice\Models\Form;
use Sunrice\Support\Locales;

/**
 * POST /sunrice/forms/{handle} — public form submissions.
 * Honeypot + rate limit are enforced by route middleware.
 *
 * The route has no language prefix, so the form posts the page's
 * language (`_locale`): messages come back in it and the submission
 * records it. Both outcomes return to the form (#sunrice-form-{handle}).
 */
class FormSubmitController extends Controller
{
    public function __invoke(Request $request, Form $form, SubmitForm $submit): RedirectResponse
    {
        $locale = (string) $request->input('_locale', '');
        $locale = Locales::isAvailable($locale) ? $locale : Locales::main();
        app()->setLocale($locale);

        $input = (array) $request->input('data', []);

        // Merge uploaded files into the input under their field handle.
        foreach ($form->schema()->fields() as $field) {
            if (($field['type'] ?? null) === 'file' && $request->hasFile('data.'.$field['handle'])) {
                $input[$field['handle']] = $request->file('data.'.$field['handle']);
            }
        }

        $back = strtok(url()->previous(), '#').'#sunrice-form-'.$form->handle;

        try {
            $submit->handle($form, $input, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->headers->get('referer'),
                'locale' => $locale,
            ]);
        } catch (ValidationException $e) {
            return redirect($back)
                ->withErrors($e->errors())
                ->withInput($request->except('_token')); // uploaded files are never flashed
        }

        $redirect = $form->setting('redirect_url');
        if (is_string($redirect) && $redirect !== '') {
            return redirect($redirect);
        }

        return redirect($back)->with("sunrice_form_success.{$form->handle}", true);
    }
}
