# Mail (SMTP)

Sunrice sends two kinds of email, both through Laravel's mailer:

- **Form notifications.** A form whose settings list notification
  addresses sends a `FormSubmittedNotification` for every submission.
  It is **queued**.
- **Admin password resets.** The "Forgot password?" link on the admin
  login page sends a reset link right away (not queued).

Nothing is Sunrice-specific: configure mail the Laravel way in `.env`.

## SMTP settings

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null          # "smtps" for implicit TLS on port 465
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=postmaster@example.com
MAIL_PASSWORD=secret
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

Common providers:

| Provider | Host | Port |
| --- | --- | --- |
| Gmail / Google Workspace (app password) | `smtp.gmail.com` | 587 |
| Microsoft 365 | `smtp.office365.com` | 587 |
| Mailgun | `smtp.mailgun.org` | 587 |
| Amazon SES | `email-smtp.<region>.amazonaws.com` | 587 |
| Postmark | `smtp.postmarkapp.com` | 587 |

Use port 465 with `MAIL_SCHEME=smtps` when the provider only offers
implicit TLS. `MAIL_FROM_ADDRESS` must be an address the provider lets
you send from, or mail is rejected or lands in spam.

After changing `.env` on a server with a cached config, run
`php artisan config:clear` (or `config:cache` again).

## Queue worker

Form notifications are queued. With `QUEUE_CONNECTION=sync` they send
during the request; with `database`, `redis` or another queue, run a
worker or they are never sent:

```bash
php artisan queue:work
```

Keep the worker running with Supervisor, systemd or your host's process
manager, and restart it after each deploy (`php artisan queue:restart`).

## Testing locally

Use `MAIL_MAILER=log` to write every email to `storage/logs/laravel.log`,
or point SMTP at a local catcher such as Mailpit
(`MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`).

To send a test message from the server:

```bash
php artisan tinker --execute="Mail::raw('It works.', fn (\$m) => \$m->to('you@example.com')->subject('Test'))"
```

## Form notification addresses

Set them per form in the form builder: **Forms → (form) → Settings →
Notify emails**, as a comma-separated list. See [Forms](forms.md).
