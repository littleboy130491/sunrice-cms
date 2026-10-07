<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Entries\PublishTranslation;
use Sunrice\Models\Entry;
use Sunrice\Support\Locales;
use Sunrice\Translation\ContentTranslator;
use Sunrice\Translation\TranslationStats;
use Sunrice\Translation\TranslatorFactory;
use Throwable;

#[Description('Machine-translate an entry from the main language into other languages with the site\'s configured translation service (Gemini or OpenRouter API key in the site\'s .env). Saves drafts; publish: true also puts them live. When no service is configured, translate the text yourself and save it with update_entry (locale + title/slug/data/seo). For the whole site, run_command sunrice:translate.')]
class TranslateEntry extends SunriceTool
{
    protected string $name = 'translate_entry';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'id' => ['required', 'integer'],
            'to' => ['nullable', 'array'],
            'to.*' => ['string'],
            'force' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],
        ]);
        $entry = Entry::query()->with(['translations', 'collection.blueprint', 'blueprint'])->find($args['id']);
        if ($entry === null) {
            return $this->notFound('Entry');
        }
        $this->authorize('translate', $entry);
        if (! empty($args['publish'])) {
            $this->authorize('publish', $entry);
        }

        $targets = array_values(array_intersect($args['to'] ?? Locales::available(), Locales::available()));
        $targets = array_values(array_diff($targets, [Locales::main()]));
        if ($targets === []) {
            return Response::error('No other language to translate into. Available: '.implode(', ', Locales::available()).'.');
        }

        $driver = (string) config('sunrice.translation.driver', 'gemini');
        if ((string) config("sunrice.translation.{$driver}.key", '') === '') {
            return Response::error("No API key for the \"{$driver}\" translation service. Translate the text yourself and save it with update_entry (locale: …), or ask the site owner to set the key (see the Machine translation docs).");
        }

        $results = [];
        foreach ($targets as $locale) {
            $stats = new TranslationStats;
            try {
                (new ContentTranslator(app(TranslatorFactory::class)->make(), Locales::main(), $locale, (bool) ($args['force'] ?? false), false, $stats))
                    ->entry($entry->fresh(['translations', 'collection.blueprint', 'blueprint']) ?? $entry);
            } catch (Throwable $e) {
                $results[$locale] = ['error' => $e->getMessage()];

                continue;
            }
            $published = false;
            if (! empty($args['publish']) && $stats->failed === 0) {
                $translation = $entry->translations()->where('locale', $locale)->first();
                if ($translation !== null) {
                    app(PublishTranslation::class)->handle($translation);
                    $published = true;
                }
            }
            $results[$locale] = ['translated' => $stats->translated, 'skipped' => $stats->skipped, 'failed' => $stats->failed, 'errors' => $stats->errors, 'published' => $published];
        }

        return $this->json(['entry_id' => $entry->id, 'results' => $results]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'to' => $schema->array()->items($schema->string())->description('Language codes (default: every other language).'),
            'force' => $schema->boolean()->description('Re-translate text that already has a translation.'),
            'publish' => $schema->boolean()->description('Publish the translations (mark them Ready).'),
        ];
    }
}
