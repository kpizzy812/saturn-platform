<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InstanceSettingsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => 0,
            'instance_name' => 'Saturn Test',
        ];
    }
}
