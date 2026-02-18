<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamWebhook>
 */
class TeamWebhookFactory extends Factory
{
    protected $model = TeamWebhook::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true).' Webhook',
            'url' => fake()->url(),
            'events' => ['deploy.started', 'deploy.finished'],
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
