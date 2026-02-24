<?php

namespace Database\Factories;

use App\Models\Environment;
use App\Models\ResourceTransfer;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceTransfer>
 */
class ResourceTransferFactory extends Factory
{
    protected $model = ResourceTransfer::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'source_type' => StandalonePostgresql::class,
            'source_id' => 1,
            'target_environment_id' => Environment::factory(),
            'target_server_id' => Server::factory(),
            'transfer_mode' => ResourceTransfer::MODE_CLONE,
            'status' => ResourceTransfer::STATUS_PENDING,
            'progress' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ResourceTransfer::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ResourceTransfer::STATUS_FAILED,
            'error_message' => 'Test error',
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ResourceTransfer::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    public function transferring(): static
    {
        return $this->state(fn () => [
            'status' => ResourceTransfer::STATUS_TRANSFERRING,
            'progress' => 50,
            'started_at' => now(),
        ]);
    }
}
