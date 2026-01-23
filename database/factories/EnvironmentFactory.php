<?php

namespace Database\Factories;

use App\Models\Environment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Environment>
 */
class EnvironmentFactory extends Factory
{
    protected $model = Environment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'env-'.$this->faker->unique()->uuid(),
            'project_id' => 1,
        ];
    }
}
