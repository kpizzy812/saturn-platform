<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationPreviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => 1,
            'pull_request_id' => fake()->randomNumber(4),
            'pull_request_html_url' => fake()->url(),
        ];
    }
}
