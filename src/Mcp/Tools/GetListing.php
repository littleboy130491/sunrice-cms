<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Support\Locales;

#[Description('Read a collection\'s listing page (archive, e.g. /blog): whether it is on, its URL, the listing blueprint\'s fields, and per language the heading, intro, field values and SEO. Change it with save_listing; turn it on or pick its blueprint with save_collection (has_archive, archive_route, archive_blueprint).')]
class GetListing extends SunriceTool
{
    protected string $name = 'get_listing';

    public function handle(Request $request): Response
    {
        $args = $request->validate(['collection' => ['required']]);
        $collection = $this->collection($args['collection']);
        if ($collection === null) {
            return $this->notFound('Collection');
        }
        if (! Gate::any(["sunrice.entries.{$collection->id}.edit", "sunrice.entries.{$collection->id}.translate"])) {
            return Response::error("You don't have permission for this collection's listing page.");
        }

        return $this->json(static::present($collection));
    }

    /** @return array<string, mixed> */
    public static function present(Collection $collection): array
    {
        $byLocale = Collection::archiveByLocale($collection->archive_data);
        $urls = app(UrlGenerator::class);
        $languages = [];
        foreach (Locales::available() as $locale) {
            $languages[$locale] = [
                'url' => $collection->setting('has_archive') ? url($urls->archive($collection, $locale)) : null,
                'title' => $byLocale[$locale]['title'] ?? null,
                'intro' => $byLocale[$locale]['intro'] ?? null,
                // Other languages: their own translatable values over the main language's.
                'data' => $collection->archiveFields($locale),
                'seo' => $byLocale[$locale]['seo'] ?? [],
            ];
        }

        $blueprintId = $collection->setting('archive_blueprint_id');

        return [
            'collection' => $collection->handle,
            'has_archive' => (bool) $collection->setting('has_archive', false),
            'listing_blueprint' => $blueprintId ? Blueprint::query()->whereKey($blueprintId)->value('handle') : null,
            'fields' => $collection->archiveSchema()?->fields() ?? [],
            'main_locale' => Locales::main(),
            'languages' => $languages,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'collection' => $schema->string()->description('Collection handle or id.')->required(),
        ];
    }
}
