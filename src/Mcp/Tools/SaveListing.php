<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Collections\SaveListing as SaveListingAction;
use Sunrice\Models\Collection;
use Sunrice\Support\Locales;

#[Description('Write a collection\'s listing page content in one language (default: the main language): title (the heading), intro, data (the listing blueprint\'s field values by handle, merged) and seo {title, description, image (asset id), canonical, noindex} (merged). Only the keys you send change; it is saved and live at once. Other languages store only their translatable fields; the rest show the main language.')]
class SaveListing extends SunriceTool
{
    protected string $name = 'save_listing';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'collection' => ['required'],
            'locale' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'intro' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
        ]);
        $collection = $this->collection($args['collection']);
        if ($collection === null) {
            return $this->notFound('Collection');
        }

        $locale = (string) ($args['locale'] ?? Locales::main());
        if (! Locales::isAvailable($locale)) {
            return Response::error("Unknown language \"{$locale}\". Languages: ".implode(', ', Locales::available()).'.');
        }
        $mayEdit = Gate::allows("sunrice.entries.{$collection->id}.edit");
        if (! ($mayEdit || (! Locales::isMain($locale) && Gate::allows("sunrice.entries.{$collection->id}.translate")))) {
            return Response::error("You don't have permission to edit this collection's listing page".(Locales::isMain($locale) ? '.' : ' in this language.'));
        }

        // Merge what was sent into what is stored for this language.
        $own = Collection::archiveByLocale($collection->archive_data)[$locale] ?? [];
        app(SaveListingAction::class)->handle($collection, [
            'locale' => $locale,
            'title' => array_key_exists('title', $args) ? $args['title'] : ($own['title'] ?? null),
            'intro' => array_key_exists('intro', $args) ? $args['intro'] : ($own['intro'] ?? null),
            'data' => array_replace($collection->archiveFields($locale), (array) ($args['data'] ?? [])),
            'seo' => array_replace((array) ($own['seo'] ?? []), (array) ($args['seo'] ?? [])),
        ]);

        return $this->json(['saved' => true] + GetListing::present($collection->refresh()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'collection' => $schema->string()->description('Collection handle or id.')->required(),
            'locale' => $schema->string()->description('Language code; default the main language.'),
            'title' => $schema->string()->description('The listing page heading.'),
            'intro' => $schema->string()->description('Intro text under the heading.'),
            'data' => $schema->object()->description('Listing blueprint field values by handle (merged).'),
            'seo' => $schema->object()->description('{title, description, image, canonical, noindex} (merged).'),
        ];
    }
}
