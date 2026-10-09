<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Sunrice\Actions\Forms\SubmitForm;
use Sunrice\Models\Form;
use Sunrice\Support\Captcha;
use Sunrice\Support\Locales;

/**
 * POST /sunrice/forms/{handle} — public form submissions.
 * Honeypot + rate limit are enforced by route middleware; forms with
 * "Require captcha" on must also pass the captcha (Support\Captcha),
 * whose error comes back under `_captcha`.
 *
 * The route has no language prefix, so the form posts the page's
 * language (`_locale`): messages come back in it and the submission
 * records it. Both outcomes return to the form (#sunrice-form-{handle}).
 *
 * AJAX submissions (`Accept: application/json`) get JSON instead:
 * `{errors: {...}}` with 422 on validation failure, `{success: true,
 * message, redirect_url}` on success (redirect_url is null unless the
 * form configures one).
 */
class FormSubmitController extends Controller
{
    public function __invoke(Request $request, Form $form, SubmitForm $submit): RedirectResponse|JsonResponse
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
            if (Captcha::requiredFor($form) && ! Captcha::verify((string) $request->input((string) Captcha::field()), $request->ip())) {
                throw ValidationException::withMessages(['_captcha' => __('sunrice::frontend.captcha_failed')]);
            }

            $submit->handle($form, $input, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->headers->get('referer'),
                'locale' => $locale,
            ]);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }

            return redirect($back)
                ->withErrors($e->errors())
                ->withInput($request->except(array_filter(['_token', Captcha::field()]))); // uploaded files are never flashed
        }

        $redirect = $form->setting('redirect_url');
        $redirect = is_string($redirect) && $redirect !== '' ? $redirect : null;

        if ($request->expectsJson()) {
            // The same text the form component shows after a normal submit.
            $message = $form->setting('success_message');

            return response()->json([
                'success' => true,
                'message' => is_string($message) && $message !== '' ? $message : __('sunrice::frontend.thank_you'),
                'redirect_url' => $redirect,
            ]);
        }

        if ($redirect !== null) {
            return redirect($redirect);
        }

        return redirect($back)->with("sunrice_form_success.{$form->handle}", true);
    }
}
