<?php

namespace Database\Factories;

use App\Enums\ApplicationDeploymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationDeploymentQueueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => 1,
            'deployment_uuid' => (string) Str::uuid(),
            'status' => ApplicationDeploymentStatus::QUEUED->value,
            'commit' => fake()->sha1(),
            'force_rebuild' => false,
            'is_webhook' => false,
        ];
    }
}
