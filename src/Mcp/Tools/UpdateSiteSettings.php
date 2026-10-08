<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Settings\SaveSiteSettings;
use Sunrice\Models\Setting;
use Sunrice\Support\SiteSettings;

#[Description('Change site settings (merged into the current ones; get_site_info shows them): name, description (default meta description), timezone, homepage_entry_id, locales {main, available, names}, seo {noindex, twitter_site, image, title_suffix (append " | site name" to every <title>), title_separator}, branding {name, tagline, logo, font, color}, code {head, body_start, body_end} (tracking scripts). The main language can\'t change once content exists.')]
class UpdateSiteSettings extends SunriceTool
{
    protected string $name = 'update_site_settings';

    public function handle(Request $request): Response
    {
        $this->authorize('sunrice.settings.edit');

        $current = SiteSettings::current();
        $current['homepage_entry_id'] = Setting::get('homepage_entry_id');
        $input = array_replace_recursive($current, $request->all());
        // Lists are replaced, not merged by index.
        if (isset($request->all()['locales']['available'])) {
            $input['locales']['available'] = $request->all()['locales']['available'];
        }

        app(SaveSiteSettings::class)->handle($input);

        return $this->json(['saved' => true, 'settings' => SiteSettings::current()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string(),
            'description' => $schema->string(),
            'timezone' => $schema->string(),
            'homepage_entry_id' => $schema->integer(),
            'locales' => $schema->object(),
            'seo' => $schema->object(),
            'branding' => $schema->object(),
            'code' => $schema->object(),
        ];
    }
}
