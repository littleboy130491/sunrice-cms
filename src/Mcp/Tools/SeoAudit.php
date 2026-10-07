<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Frontend\UrlGenerator;
use Sunrice\Models\Asset;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Support\Locales;

#[IsReadOnly]
#[Description('Check published entries for SEO problems: missing or badly sized meta titles (aim 30-60 characters) and descriptions (70-160), duplicates, no social image, noindex, thin content, missing or unready translations, and images without alt text. Returns issues per entry with what to fix; fix them with update_entry (seo: {title, description, image}) and save_asset (alt).')]
class SeoAudit extends SunriceTool
{
    protected string $name = 'seo_audit';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'collection' => ['nullable'],
            'locale' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $collections = Collection::query()->get()->filter(fn (Collection $c) => $c->hasSinglePages()
            && Gate::allows('viewAny', [Entry::class, $c->id]));
        if (! empty($args['collection'])) {
            $one = $this->collection($args['collection']);
            if ($one === null) {
                return $this->notFound('Collection');
            }
            $collections = $collections->where('id', $one->id);
        }
        $locales = ! empty($args['locale']) ? [$args['locale']] : Locales::available();

        $entries = Entry::query()->published()->whereIn('collection_id', $collections->pluck('id'))
            ->with(['translations', 'collection'])->latest('id')->limit((int) ($args['limit'] ?? 200))->get();

        $siteDescription = (string) config('sunrice.seo.description', '');
        $siteImage = config('sunrice.seo.image');
        $report = [];
        $titles = [];
        $descriptions = [];

        foreach ($entries as $entry) {
            $collectionSeo = (array) $entry->collection->setting('seo', []);
            foreach ($locales as $locale) {
                $t = $entry->translations->firstWhere('locale', $locale);
                $issues = [];
                if (! Locales::isMain($locale) && $entry->collection->setting('translatable', true) !== false) {
                    if ($t === null) {
                        $report[] = ['entry_id' => $entry->id, 'locale' => $locale, 'title' => $entry->mainTranslation()?->title, 'issues' => ['No translation in this language (the page shows the main language).']];

                        continue;
                    }
                    if (! $t->is_ready) {
                        $issues[] = 'Translation not Ready: the page shows the main language.';
                    }
                }
                if (! $t instanceof EntryTranslation) {
                    continue;
                }

                $seo = (array) ($t->seo ?? []);
                $metaTitle = trim((string) ($seo['title'] ?? '')) ?: $t->title;
                $length = mb_strlen($metaTitle);
                if ($length > 60) {
                    $issues[] = "Meta title is {$length} characters; keep it under 60 (set seo.title).";
                } elseif ($length < 30) {
                    $issues[] = "Meta title is short ({$length} characters); 30-60 works best.";
                }

                $description = trim((string) ($seo['description'] ?? ''));
                if ($description === '') {
                    $issues[] = ($collectionSeo['description'] ?? '') !== '' || $siteDescription !== ''
                        ? 'No own meta description (a generic collection/site one is used); write one for this page.'
                        : 'No meta description.';
                } else {
                    $d = mb_strlen($description);
                    if ($d > 160) {
                        $issues[] = "Meta description is {$d} characters; keep it under 160.";
                    } elseif ($d < 70) {
                        $issues[] = "Meta description is short ({$d} characters); 70-160 works best.";
                    }
                    $descriptions[$locale][mb_strtolower($description)][] = $entry->id;
                }
                $titles[$locale][mb_strtolower($metaTitle)][] = $entry->id;

                if (empty($seo['image']) && empty($collectionSeo['image']) && empty($siteImage)) {
                    $issues[] = 'No social sharing image (seo.image, an asset id).';
                }
                if (! empty($seo['noindex'])) {
                    $issues[] = 'Hidden from search engines (seo.noindex).';
                }
                $words = str_word_count(strip_tags(implode(' ', array_map(
                    fn ($v) => is_string($v) ? $v : '',
                    $entry->dataFor($t),
                ))));
                if ($words < 150) {
                    $issues[] = "Thin content: about {$words} words of text.";
                }
                if (mb_strlen($t->slug) > 75) {
                    $issues[] = 'Long URL slug; shorten it.';
                }

                if ($issues !== []) {
                    $report[] = [
                        'entry_id' => $entry->id,
                        'locale' => $locale,
                        'title' => $t->title,
                        'url' => app(UrlGenerator::class)->entryUrl($entry, $locale),
                        'issues' => $issues,
                    ];
                }
            }
        }

        $duplicates = [];
        foreach (['title' => $titles, 'description' => $descriptions] as $kind => $byLocale) {
            foreach ($byLocale as $locale => $values) {
                foreach ($values as $value => $ids) {
                    if (count($ids) > 1) {
                        $duplicates[] = ['kind' => "meta {$kind}", 'locale' => $locale, 'value' => Str::limit((string) $value, 80), 'entry_ids' => $ids];
                    }
                }
            }
        }

        return $this->json([
            'checked_entries' => $entries->count(),
            'languages' => $locales,
            'issues' => $report,
            'duplicates' => $duplicates,
            'images_without_alt' => Asset::query()->where('mime_type', 'like', 'image/%')
                ->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))->count(),
            'site' => [
                'default_description' => $siteDescription !== '' ? $siteDescription : null,
                'default_image' => $siteImage,
                'noindex' => (bool) config('sunrice.seo.noindex', false),
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'collection' => $schema->string()->description('Only this collection (handle).'),
            'locale' => $schema->string()->description('Only this language.'),
            'limit' => $schema->integer()->description('Newest entries to check (default 200).'),
        ];
    }
}
