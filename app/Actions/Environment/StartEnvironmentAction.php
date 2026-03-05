<?php

namespace App\Actions\Environment;

use App\Models\Application;
use App\Models\Environment;
use App\Services\DependencyResolver;
use Illuminate\Support\Facades\Log;

class StartEnvironmentAction
{
    public function __construct(
        private DependencyResolver $resolver,
    ) {}

    /**
     * Start all resources in an environment respecting dependency order.
     *
     * @return array{started: array<string>, skipped: array<string>, errors: array<string>}
     */
    public function execute(Environment $environment): array
    {
        $tiers = $this->resolver->resolve($environment);
        $started = [];
        $skipped = [];
        $errors = [];

        foreach ($tiers as $tierIndex => $tier) {
            Log::info("Starting environment tier {$tierIndex}", [
                'environment' => $environment->name,
                'resources' => array_column($tier, 'name'),
            ]);

            foreach ($tier as $resource) {
                try {
                    $this->startResource($environment, $resource['uuid'], $resource['type']);
                    $started[] = $resource['name'];
                } catch (\Throwable $e) {
                    Log::error("Failed to start resource {$resource['name']}: {$e->getMessage()}");
                    $errors[] = "{$resource['name']}: {$e->getMessage()}";
                }
            }
        }

        return compact('started', 'skipped', 'errors');
    }

    private function startResource(Environment $environment, string $uuid, string $type): void
    {
        match ($type) {
            'application' => $this->startApplication($environment, $uuid),
            'database' => $this->startDatabase($environment, $uuid),
            'service' => $this->startService($environment, $uuid),
        };
    }

    private function startApplication(Environment $environment, string $uuid): void
    {
        $app = $environment->applications()->where('uuid', $uuid)->firstOrFail();

        if ($app->status === 'running') {
            return;
        }

        // Queue a deployment for the application
        $deployment_uuid = new \Visus\Cuid2\Cuid2;
        queue_application_deployment(
            application: $app,
            deployment_uuid: $deployment_uuid,
            restart_only: true,
        );
    }

    private function startDatabase(Environment $environment, string $uuid): void
    {
        $db = $environment->databases()->first(fn ($d) => $d->uuid === $uuid);

        if (! $db) {
            return;
        }

        if ($db->status === 'running') {
            return;
        }

        // Use the appropriate start action based on database type
        $actionClass = match (true) {
            $db instanceof \App\Models\StandalonePostgresql => \App\Actions\Database\StartPostgresql::class,
            $db instanceof \App\Models\StandaloneMysql => \App\Actions\Database\StartMysql::class,
            $db instanceof \App\Models\StandaloneMariadb => \App\Actions\Database\StartMariadb::class,
            $db instanceof \App\Models\StandaloneMongodb => \App\Actions\Database\StartMongodb::class,
            $db instanceof \App\Models\StandaloneRedis => \App\Actions\Database\StartRedis::class,
            $db instanceof \App\Models\StandaloneKeydb => \App\Actions\Database\StartKeydb::class,
            $db instanceof \App\Models\StandaloneDragonfly => \App\Actions\Database\StartDragonfly::class,
            $db instanceof \App\Models\StandaloneClickhouse => \App\Actions\Database\StartClickhouse::class,
            default => null,
        };

        if ($actionClass) {
            $server = $db->destination?->server;
            if ($server) {
                resolve($actionClass)($db);
            }
        }
    }

    private function startService(Environment $environment, string $uuid): void
    {
        $service = $environment->services()->where('uuid', $uuid)->firstOrFail();

        if (str($service->status())->contains('running')) {
            return;
        }

        resolve(\App\Actions\Service\StartService::class)($service);
    }
}
