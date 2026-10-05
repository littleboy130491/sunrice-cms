<?php

declare(strict_types=1);

namespace Sunrice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;

/** @extends Factory<FormSubmission> */
class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'data' => ['message' => fake()->sentence()],
            'locale' => 'id',
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Pest',
        ];
    }
}
