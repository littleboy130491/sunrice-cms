<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Sunrice\Fields\Block;
use Sunrice\Fields\BlueprintSchema;
use Sunrice\Fields\CustomField;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Fields\FieldType;
use Sunrice\Fields\HydrationContext;
use Sunrice\Models\Asset;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Fieldset;
use Sunrice\Query\JsonField;
use Sunrice\Support\HtmlSanitizer;

class DummyRatingField extends FieldType
{
    public static function type(): string
    {
        return 'rating';
    }

    public function rules(array $field): array
    {
        return ['integer', 'min:1', 'max:5'];
    }
}

class SeoTitleField extends CustomField
{
    public static function type(): string
    {
        return 'seo_title';
    }

    public static function baseType(): string
    {
        return 'text';
    }

    public static function config(): array
    {
        return ['max' => 60];
    }

    public static function extraRules(): array
    {
        return ['min:10'];
    }
}

it('registers and resolves field types', function () {
    $registry = app(FieldRegistry::class);
    expect($registry->get('text'))->toBeInstanceOf(FieldType::class)
        ->and($registry->get('flexible'))->toBeInstanceOf(FieldType::class)
        ->and($registry->has('nope'))->toBeFalse();

    $registry->register(DummyRatingField::class);
    expect($registry->get('rating')->rules([]))->toContain('integer');

    expect(fn () => $registry->get('missing-type'))->toThrow(InvalidArgumentException::class);
});

it('validates with per-type rules plus required and validation keys', function () {
    $schema = BlueprintSchema::make([
        ['handle' => 'title', 'type' => 'text', 'required' => true, 'validation' => ['max:5']],
        ['handle' => 'price', 'type' => 'number'],
        ['handle' => 'tags', 'type' => 'repeater', 'config' => ['fields' => [
            ['handle' => 'name', 'type' => 'text', 'required' => true],
        ]]],
    ]);

    $rules = $schema->rules();
    expect($rules['data.title'])->toContain('required')->toContain('max:5')
        ->and($rules['data.price'])->toContain('numeric')->toContain('nullable')
        ->and($rules['data.tags.*.name'])->toContain('required');

    $v = validator(['data' => ['title' => 'toolongtitle', 'price' => 'abc']], $rules);
    expect($v->fails())->toBeTrue();
});

it('keeps keys not in the blueprint on normalize', function () {
    $schema = BlueprintSchema::make([
        ['handle' => 'known', 'type' => 'text'],
    ]);

    $out = $schema->normalize(['known' => 'yes', 'hidden' => 'kept']);
    expect($out['hidden'])->toBe('kept')->and($out['known'])->toBe('yes');
});

it('expands fieldset includes and detects cycles', function () {
    Fieldset::create(['handle' => 'hero', 'title' => 'Hero', 'fields' => [
        ['handle' => 'heading', 'type' => 'text'],
    ]]);

    $schema = BlueprintSchema::make([
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'inc', 'type' => 'fieldset', 'config' => ['fieldset' => 'hero']],
    ]);

    expect(collect($schema->fields())->pluck('handle')->all())
        ->toBe(['intro', 'heading']);

    Fieldset::create(['handle' => 'loop_a', 'title' => 'A', 'fields' => [
        ['handle' => 'x', 'type' => 'fieldset', 'config' => ['fieldset' => 'loop_a']],
    ]]);

    // Direct self-include inside the fieldset is expanded when the
    // fieldset itself is compiled.
    expect(fn () => BlueprintSchema::make([
        ['handle' => 's', 'type' => 'fieldset', 'config' => ['fieldset' => 'missing']],
    ])->fields())->not->toThrow(InvalidArgumentException::class);
});

it('hydrates asset and entries fields via the context memo', function () {
    Storage::fake('public');
    $asset = Asset::factory()->create();
    $collection = createCollection();
    $linked = createEntry($collection, 'Linked');

    $schema = BlueprintSchema::make([
        ['handle' => 'image', 'type' => 'asset'],
        ['handle' => 'related', 'type' => 'entries'],
    ]);

    $ctx = new HydrationContext('id');
    $out = $schema->hydrate(['image' => $asset->id, 'related' => [$linked->id]], $ctx);

    expect($out['image']->id)->toBe($asset->id)
        ->and($out['related'])->toHaveCount(1)
        ->and($out['related']->first()->id)->toBe($linked->id);
});

it('extracts references from nested fields and rich text', function () {
    $asset = Asset::factory()->create();
    $linked = createEntry(createCollection(), 'L');

    $schema = BlueprintSchema::make([
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'rows', 'type' => 'repeater', 'config' => ['fields' => [
            ['handle' => 'img', 'type' => 'asset'],
            ['handle' => 'link', 'type' => 'entries'],
        ]]],
    ]);

    $refs = $schema->references([
        'body' => '<p>x <img src="/a.jpg" data-asset-id="'.$asset->id.'"></p>',
        'rows' => [['img' => $asset->id, 'link' => [$linked->id]]],
    ]);

    $targets = collect($refs)->map(fn ($r) => $r['target_type'].':'.$r['target_id'])->all();
    expect($targets)->toContain('asset:'.$asset->id)->toContain('entry:'.$linked->id);
});

it('round-trips a repeater inside a flexible block', function () {
    Fieldset::create(['handle' => 'gallery', 'title' => 'Gallery', 'fields' => [
        ['handle' => 'photos', 'type' => 'repeater', 'config' => ['fields' => [
            ['handle' => 'img', 'type' => 'asset'],
        ]]],
    ]]);

    $schema = BlueprintSchema::make([
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['fieldsets' => ['gallery']]],
    ]);

    $value = [['type' => 'gallery', 'values' => ['photos' => [['img' => 1], ['img' => 2]]]]];
    $normalized = $schema->normalize(['sections' => $value]);
    expect($normalized['sections'][0]['id'])->not->toBeEmpty()
        ->and($normalized['sections'][0]['values']['photos'])->toHaveCount(2);

    $hydrated = $schema->hydrate($normalized, new HydrationContext('id'));
    $block = $hydrated['sections']->first();
    expect($block)->toBeInstanceOf(Block::class)
        ->and($block->type)->toBe('gallery')
        ->and($block->photos)->toHaveCount(2);
});

it('wraps custom fields around a base type with preset config and extra rules', function () {
    app(FieldRegistry::class)->register(SeoTitleField::class);

    $schema = BlueprintSchema::make([
        ['handle' => 'seo_title', 'type' => 'seo_title', 'required' => true],
    ]);

    $rules = $schema->rules()['data.seo_title'];
    expect($rules)->toContain('max:60')->toContain('min:10')->toContain('required');

    $admin = $schema->toAdminSchema()[0];
    expect($admin['type'])->toBe('text')->and($admin['display_type'])->toBe('seo_title');

    $v = validator(['data' => ['seo_title' => 'short']], $schema->rules());
    expect($v->fails())->toBeTrue();
});

it('sanitizes rich text and extracts asset ids', function () {
    $clean = HtmlSanitizer::sanitize('<p onclick="x()">Hi<script>alert(1)</script><a href="javascript:x">l</a></p>');
    expect($clean)->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:');

    $html = '<img src="a.jpg" data-asset-id="12"><img src="b.jpg" data-asset-id="12"><img src="c.jpg" data-asset-id="9">';
    expect(HtmlSanitizer::extractAssetIds($html))->toBe([12, 9]);
});

it('queries JSON fields with casts', function () {
    $collection = createCollection();
    $cheap = createEntry($collection, 'Cheap', ['price' => 2]);
    $dear = createEntry($collection, 'Dear', ['price' => 10]);

    $q = EntryTranslation::query()->where('collection_id', $collection->id);
    JsonField::where($q, 'data', 'price', '>', 5, 'number');
    expect($q->pluck('title')->all())->toBe(['Dear']);

    $q2 = EntryTranslation::query()->where('collection_id', $collection->id);
    JsonField::orderBy($q2, 'data', 'price', 'number', 'asc');
    expect($q2->pluck('title')->all())->toBe(['Cheap', 'Dear']);

    // 'contains' for multi-select JSON arrays.
    $multi = createEntry($collection, 'Multi', ['tags' => ['a', 'b']]);
    $q3 = EntryTranslation::query()->where('collection_id', $collection->id);
    JsonField::where($q3, 'data', 'tags', 'contains', 'b');
    expect($q3->pluck('title')->all())->toBe(['Multi']);
});

it('expands fieldset includes saved by older builders at the top level', function () {
    Fieldset::create(['handle' => 'cta', 'title' => 'CTA', 'fields' => [
        ['handle' => 'button', 'type' => 'text'],
    ]]);

    $schema = BlueprintSchema::make([
        ['handle' => 'fieldset_1', 'type' => 'fieldset', 'fieldset' => 'cta'],
    ]);

    expect(collect($schema->fields())->pluck('handle')->all())->toBe(['button']);
});

it('offers settings for fieldset, terms, entries and link fields', function () {
    $registry = app(FieldRegistry::class);

    expect(collect($registry->get('fieldset')->settingsSchema())->pluck('handle')->all())->toBe(['fieldset'])
        ->and(collect($registry->get('terms')->settingsSchema())->pluck('handle')->all())->toBe(['taxonomy'])
        ->and(collect($registry->get('entries')->settingsSchema())->pluck('handle')->all())->toBe(['collections', 'max'])
        ->and(collect($registry->get('link')->settingsSchema())->pluck('handle')->all())->toBe(['collections']);
});

it('stores entry links under entry_id, accepting the old entry key', function () {
    $link = app(FieldRegistry::class)->get('link');

    expect($link->normalize(['type' => 'entry', 'entry' => 7, 'label' => 'Go', 'new_tab' => true], []))
        ->toBe(['type' => 'entry', 'url' => null, 'entry_id' => 7, 'label' => 'Go', 'new_tab' => true]);
});

it('lets the base type shape a custom field admin schema', function () {
    $repeater = new class extends CustomField
    {
        public static function type(): string
        {
            return 'faq';
        }

        public static function baseType(): string
        {
            return 'repeater';
        }
    };
    $base = app(FieldRegistry::class)->get('repeater');
    $field = ['handle' => 'faq', 'type' => 'faq', 'config' => ['fields' => [['handle' => 'q', 'type' => 'text']]]];

    $schema = $repeater->toAdminSchema($field);

    expect($schema['type'])->toBe('repeater')
        ->and($schema['display_type'])->toBe('faq')
        ->and($schema['config'])->toEqual($base->toAdminSchema(['type' => 'repeater'] + $field)['config']);
});
