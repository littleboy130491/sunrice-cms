<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Settings\SaveSiteSettings;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Setting;
use Sunrice\Notifications\TestMail;
use Sunrice\Support\Branding;
use Sunrice\Support\SiteSettings;
use Throwable;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('sunrice.settings.edit');

        $homepage = Entry::query()->with('translations')->find((int) Setting::get('homepage_entry_id', 0));
        $image = SiteSettings::current()['seo']['image'] ?? null;
        $asset = is_numeric($image) ? Asset::query()->find((int) $image) : null;

        return Inertia::render('Settings/Edit', [
            'settings' => SiteSettings::current(),
            'homepage' => $homepage === null ? null : [
                'id' => $homepage->id,
                'title' => $homepage->mainTranslation()->title ?? "Entry #{$homepage->id}",
                'collection' => '',
            ],
            'shareImage' => $asset === null ? null : ['id' => $asset->id, 'url' => $asset->url('thumbnail'), 'filename' => $asset->filename],
            'timezones' => timezone_identifiers_list(),
            'mainLocked' => EntryTranslation::query()->exists(),
            'brandLogo' => ($logo = is_numeric(config('sunrice.branding.logo')) ? Asset::query()->find((int) config('sunrice.branding.logo')) : null) === null
                ? null
                : ['id' => $logo->id, 'url' => $logo->url(), 'filename' => $logo->filename],
            'mail' => static::mailInfo(),
            'fonts' => collect(Branding::FONTS)->map(fn (array $font, string $key) => ['value' => $key, 'label' => $font[0]])->values(),
        ]);
    }

    public function update(Request $request, SaveSiteSettings $save): RedirectResponse
    {
        Gate::authorize('sunrice.settings.edit');

        $save->handle($request->all());

        return back()->with('success', 'Settings saved.');
    }

    /** Send a test email right away, so SMTP problems show up here. */
    public function testMail(Request $request): RedirectResponse
    {
        Gate::authorize('sunrice.settings.edit');

        $email = (string) $request->validate(['test_email' => ['required', 'email', 'max:255']])['test_email'];

        try {
            Notification::route('mail', $email)->notifyNow(new TestMail);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['test_email' => 'Sending failed: '.$e->getMessage()]);
        }

        return back()->with('success', static::mailInfo()['delivers']
            ? "Test email sent to {$email}. Check the inbox (and the spam folder)."
            : "Test email for {$email} written to the log: the current mailer doesn't deliver email.");
    }

    /**
     * How mail is configured (from .env / config/mail.php), shown next to
     * the test form. No passwords.
     *
     * @return array<string, mixed>
     */
    public static function mailInfo(): array
    {
        $mailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport", $mailer);

        return [
            'mailer' => $mailer,
            'transport' => $transport,
            'host' => $transport === 'smtp' ? config("mail.mailers.{$mailer}.host") : null,
            'port' => $transport === 'smtp' ? config("mail.mailers.{$mailer}.port") : null,
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            // These don't deliver anything.
            'delivers' => ! in_array($transport, ['log', 'array'], true),
        ];
    }
}
