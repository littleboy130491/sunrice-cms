<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;

class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'taxonomy_id' => Taxonomy::factory(),
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Term $term): void {
            if ($term->translations()->count() === 0) {
                $name = fake()->unique()->words(2, true);
                $term->translations()->create([
                    'taxonomy_id' => $term->taxonomy_id,
                    'locale' => \Sunrice\Support\Locales::main(),
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'data' => [],
                ]);
            }
        });
    }
}
