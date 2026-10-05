<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'status' => 'published',
            'published_at' => now(),
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft', 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->addDay()]);
    }
}
