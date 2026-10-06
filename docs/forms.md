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
- Spam protection: honeypot fields (`<x-honeypot/>`, emitted by the
  component) + the `sunrice-forms` rate limiter
  (`sunrice.forms.rate_limit`, per IP + form → HTTP 429).
- `settings.notify_emails` (comma-separated) receives a queued
  `FormSubmittedNotification` per submission.
- `settings.redirect_url` redirects on success; otherwise the response
  flashes `sunrice_form_success.{handle}` and shows `success_message`.
- `sunrice.forms.prune_after_days` + the daily `model:prune` schedule
  delete old submissions and their uploaded files.
