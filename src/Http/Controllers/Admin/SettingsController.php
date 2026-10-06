<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Actions\Settings\SaveSiteSettings;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Setting;
use Sunrice\Support\SiteSettings;

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
                'title' => $homepage->mainTranslation()?->title ?? "Entry #{$homepage->id}",
                'collection' => '',
            ],
            'shareImage' => $asset === null ? null : ['id' => $asset->id, 'url' => $asset->url('thumbnail'), 'filename' => $asset->filename],
            'timezones' => timezone_identifiers_list(),
            'mainLocked' => EntryTranslation::query()->exists(),
        ]);
    }

    public function update(Request $request, SaveSiteSettings $save): RedirectResponse
    {
        Gate::authorize('sunrice.settings.edit');

        $save->handle($request->all());

        return back()->with('success', 'Settings saved.');
    }
}
