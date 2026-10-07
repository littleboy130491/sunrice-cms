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
