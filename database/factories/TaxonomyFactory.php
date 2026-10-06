<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Taxonomy;

/** @extends Factory<Taxonomy> */
class TaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return [
            'handle' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'hierarchical' => false,
            'settings' => ['has_archive' => false],
        ];
    }
}
