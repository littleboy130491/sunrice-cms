<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;

class EntryTranslationFactory extends Factory
{
    protected $model = EntryTranslation::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'entry_id' => Entry::factory(),
            'collection_id' => fn (array $attrs) => Entry::find($attrs['entry_id'])?->collection_id ?? \Sunrice\Models\Collection::factory(),
            'locale' => \Sunrice\Support\Locales::main(),
            'title' => $title,
            'slug' => Str::slug($title),
            'data' => [],
            'seo' => [],
            'is_ready' => true,
            'content_published_at' => now(),
        ];
    }
}
