<?php

namespace App\Actions\Environment;

use App\Models\Environment;
use App\Services\DependencyResolver;
use Illuminate\Support\Facades\Log;

class StopEnvironmentAction
{
    public function __construct(
        private DependencyResolver $resolver,
    ) {}

    /**
     * Stop all resources in an environment in reverse dependency order.
     *
     * @return array{stopped: array<string>, errors: array<string>}
     */
    public function execute(Environment $environment): array
    {
        $tiers = $this->resolver->resolve($environment);
        $stopped = [];
        $errors = [];

        // Reverse order: stop dependents first, then dependencies
        foreach (array_reverse($tiers) as $tierIndex => $tier) {
            Log::info("Stopping environment tier {$tierIndex}", [
                'environment' => $environment->name,
                'resources' => array_column($tier, 'name'),
            ]);

            foreach ($tier as $resource) {
                try {
                    $this->stopResource($environment, $resource['uuid'], $resource['type']);
                    $stopped[] = $resource['name'];
                } catch (\Throwable $e) {
                    Log::error("Failed to stop resource {$resource['name']}: {$e->getMessage()}");
                    $errors[] = "{$resource['name']}: {$e->getMessage()}";
                }
            }
        }

        return compact('stopped', 'errors');
    }

    private function stopResource(Environment $environment, string $uuid, string $type): void
    {
        match ($type) {
            'application' => $this->stopApplication($environment, $uuid),
            'database' => $this->stopDatabase($environment, $uuid),
            'service' => $this->stopService($environment, $uuid),
            default => throw new \InvalidArgumentException("Unknown resource type: {$type}"),
        };
    }

    private function stopApplication(Environment $environment, string $uuid): void
    {
        $app = $environment->applications()->where('uuid', $uuid)->firstOrFail();
        resolve(\App\Actions\Application\StopApplication::class)($app);
    }

    private function stopDatabase(Environment $environment, string $uuid): void
    {
        $db = $environment->databases()->first(fn ($d) => $d->uuid === $uuid);
        if (! $db) {
            return;
        }
        resolve(\App\Actions\Database\StopDatabase::class)($db);
    }

    private function stopService(Environment $environment, string $uuid): void
    {
        $service = $environment->services()->where('uuid', $uuid)->firstOrFail();
        resolve(\App\Actions\Service\StopService::class)($service);
    }
}
