<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Asset;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $filename = fake()->unique()->slug(2).'.jpg';

        return [
            'folder_id' => null,
            'disk' => 'public',
            'path' => 'sunrice/'.date('Y/m').'/'.$filename,
            'filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 500000),
            'width' => 800,
            'height' => 600,
            'sizes' => [],
            'version' => 1,
        ];
    }
}
