<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GithubAppFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().'-github',
            'api_url' => 'https://api.github.com',
            'html_url' => 'https://github.com',
            'app_id' => fake()->randomNumber(6),
            'installation_id' => fake()->randomNumber(8),
            'client_id' => fake()->sha1(),
            'client_secret' => fake()->sha256(),
            'webhook_secret' => fake()->sha256(),
            'is_public' => false,
        ];
    }
}
