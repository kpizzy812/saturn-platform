<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EnvironmentVariableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => strtoupper(fake()->unique()->word()),
            'value' => fake()->word(),
            'is_preview' => false,
            'is_runtime' => true,
            'is_buildtime' => false,
            'resourceable_type' => 'App\Models\Application',
            'resourceable_id' => 1,
        ];
    }
}
