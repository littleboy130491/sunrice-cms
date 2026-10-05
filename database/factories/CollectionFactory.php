<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;

class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        $handle = fake()->unique()->slug(1);

        return [
            'handle' => $handle,
            'title' => fake()->words(2, true),
            'blueprint_id' => Blueprint::factory(),
            'settings' => [
                'route' => '/'.$handle.'/{slug}',
                'has_single' => true,
                'has_archive' => false,
                'translatable' => false,
                'default_sort' => 'manual',
                'per_page' => 12,
            ],
            'sort_order' => 0,
        ];
    }
}
