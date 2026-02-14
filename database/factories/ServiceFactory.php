<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'environment_id' => 1,
            'server_id' => 1,
            'destination_id' => 1,
            'destination_type' => 'App\Models\StandaloneDocker',
        ];
    }
}
