<?php

namespace Database\Factories;

use App\Models\TeamWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'team_webhook_id' => TeamWebhook::factory(),
            'event' => fake()->randomElement(['deploy.started', 'deploy.finished', 'deploy.failed']),
            'status' => 'pending',
            'payload' => ['event' => 'test', 'timestamp' => now()->toIso8601String()],
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => 'success',
            'status_code' => 200,
            'response_time_ms' => fake()->numberBetween(50, 500),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'status_code' => 500,
            'response_time_ms' => fake()->numberBetween(100, 3000),
        ]);
    }
}
