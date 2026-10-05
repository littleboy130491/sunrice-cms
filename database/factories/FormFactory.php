<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Form;

class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'handle' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'fields' => [
                ['handle' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
            ],
            'settings' => ['notify_emails' => [], 'success_message' => 'Thanks!'],
        ];
    }
}
