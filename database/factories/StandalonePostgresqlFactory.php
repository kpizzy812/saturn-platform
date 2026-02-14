<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StandalonePostgresqlFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().'-pg',
            'postgres_user' => 'postgres',
            'postgres_password' => fake()->password(),
            'postgres_db' => 'test_db',
            'image' => 'postgres:15-alpine',
            'environment_id' => 1,
            'destination_id' => 1,
            'destination_type' => 'App\Models\StandaloneDocker',
            'ports_exposes' => '5432',
        ];
    }
}
