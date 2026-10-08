# Forms

Build forms in the admin (`sunrice.forms.create`, then `sunrice.forms.{id}.edit`); render them in Blade:

```blade
<x-sunrice::form handle="contact">
    <input name="data[name]" value="{{ $component->old('name') }}" required>
    <input name="data[email]" type="email" value="{{ $component->old('email') }}" required>
    @if ($message = $component->error('email')) <p>{{ $message }}</p> @endif
    <textarea name="data[message]">{{ $component->old('message') }}</textarea>
</x-sunrice::form>
```

- Fields are validated server-side against the form schema (per-field
  `required`, `validation` rules, `config.mimes`/`config.max_kb` for
  `file` fields).
- Allowed field types: `text`, `textarea`, `number`, `toggle`, `select`,
  `date`, `file`. `file` uploads land on `sunrice.forms.upload_disk`
  under `form-uploads/{handle}` and are never public — admins download
  them through the authorized submission screen.

## File uploads

In the form builder, a **File** field has two settings:

- **Accepted file types** — tick groups (images, PDF, documents,
  spreadsheets…) and/or type other extensions. Stored as `config.mimes`.
  Nothing ticked accepts any type, except ones that could run as code
  (`.php`, `.html`, `.js`, `.exe`…), which are always refused.
- **Maximum file size** — in MB (stored as `config.max_kb`). Empty uses
  `sunrice.forms.upload_max_kb` (10 MB). The builder shows this server's
  own limit too: the smaller of PHP's `upload_max_filesize` and
  `post_max_size`. Files over that are refused by PHP before Sunrice sees
  them, so raise both in `php.ini` (and your web server's body size limit,
  e.g. nginx `client_max_body_size`) to accept larger files.

The starter form template adds `accept=".pdf,.jpg"` to the file input and a
hint such as "Accepted: PDF, JPG. Up to 5 MB." (`File::acceptAttribute()`,
`File::hint()`). Upload errors use Sunrice's own messages
(`sunrice::frontend.upload_*`, English and Indonesian), so they read well
even when the site has no `lang/` validation file for its language.
- Spam protection: honeypot fields (`<x-honeypot/>`, emitted by the
  component) + the `sunrice-forms` rate limiter
  (`sunrice.forms.rate_limit`, per IP + form → HTTP 429).
- `settings.notify_emails` (comma-separated) receives a queued
  `FormSubmittedNotification` per submission.
- `settings.redirect_url` redirects on success; otherwise the response
  flashes `sunrice_form_success.{handle}` and shows `success_message`.
- `sunrice.forms.prune_after_days` + the daily `model:prune` schedule
  delete old submissions and their uploaded files.

## Submitting without a page reload

Post the form with JavaScript and ask for JSON (`Accept: application/json`);
the same address then answers with JSON instead of a redirect. Send the
whole form as `FormData`, so the CSRF token, the honeypot fields, the
page's language (`_locale`) and any files go along:

```blade
<x-sunrice::form handle="contact" data-ajax>
    <input name="data[name]" required>
    <input name="data[email]" type="email" required>
    <p data-error="email"></p>
</x-sunrice::form>

<script>
document.querySelectorAll('form[data-ajax]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        form.querySelectorAll('[data-error]').forEach((el) => (el.textContent = ''));

        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new FormData(form),
        });
        const result = await response.json().catch(() => ({}));

        if (response.ok) {
            if (result.redirect_url) return (window.location = result.redirect_url);
            const done = Object.assign(document.createElement('div'), { className: 'sunrice-form-success', role: 'status', textContent: result.message });
            form.replaceWith(done);
        } else if (response.status === 422) {
            for (const [field, messages] of Object.entries(result.errors)) {
                const el = form.querySelector(`[data-error="${field.replace(/^data\./, '')}"]`);
                if (el) el.textContent = messages[0];
            }
        } else {
            alert('Something went wrong. Please try again.'); // 429: too many tries
        }
    });
});
</script>
```

| Outcome | Status | Body |
| --- | --- | --- |
| Sent | `200` | `{"success": true, "message": "Thank you!", "redirect_url": null}` |
| Invalid | `422` | `{"errors": {"data.email": ["The email field must be a valid email address."]}}` |
| Too many tries | `429` | the rate limiter's response |

- `message` is the form's `success_message`, or the default thank-you
  text in the page's language.
- `redirect_url` is the form's redirect setting, or `null`; the script
  decides whether to follow it.
- Error keys are the input names in dot form (`data.email` for
  `data[email]`); the messages are in the page's language.
- A post without `Accept: application/json` still redirects as before,
  so the form keeps working when JavaScript is off.
