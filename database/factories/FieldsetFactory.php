<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Fieldset;

class FieldsetFactory extends Factory
{
    protected $model = Fieldset::class;

    public function definition(): array
    {
        return [
            'handle' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'fields' => [
                ['handle' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ];
    }
}
