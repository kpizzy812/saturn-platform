<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LocalPersistentVolumeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().'-volume',
            'mount_path' => '/data/'.fake()->word(),
            'resource_type' => 'App\Models\Application',
            'resource_id' => 1,
        ];
    }
}
