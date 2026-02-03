<?php

namespace App\Services\AI\Chat;

use App\Actions\Application\StopApplication;
use App\Actions\Database\RestartDatabase;
use App\Actions\Database\StartDatabase;
use App\Actions\Database\StopDatabase;
use App\Actions\Service\RestartService;
use App\Actions\Service\StartService;
use App\Actions\Service\StopService;
use App\Models\Application;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Models\User;
use App\Services\AI\Chat\DTOs\CommandResult;
use App\Services\AI\Chat\DTOs\IntentResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Visus\Cuid2\Cuid2;

/**
 * Executes commands based on intent detection.
 */
class CommandExecutor
{
    private User $user;

    private int $teamId;

    /**
     * Database model classes.
     */
    private const DATABASE_MODELS = [
        'postgresql' => StandalonePostgresql::class,
        'postgres' => StandalonePostgresql::class,
        'mysql' => StandaloneMysql::class,
        'mariadb' => StandaloneMariadb::class,
        'mongodb' => StandaloneMongodb::class,
        'mongo' => StandaloneMongodb::class,
        'redis' => StandaloneRedis::class,
        'keydb' => StandaloneKeydb::class,
        'dragonfly' => StandaloneDragonfly::class,
        'clickhouse' => StandaloneClickhouse::class,
    ];

    public function __construct(User $user, int $teamId)
    {
        $this->user = $user;
        $this->teamId = $teamId;
    }

    /**
     * Execute a command based on intent.
     */
    public function execute(IntentResult $intent): CommandResult
    {
        if (! $intent->hasIntent()) {
            return CommandResult::failed('No intent detected');
        }

        return match ($intent->intent) {
            'deploy' => $this->executeDeploy($intent),
            'restart' => $this->executeRestart($intent),
            'stop' => $this->executeStop($intent),
            'start' => $this->executeStart($intent),
            'logs' => $this->executeLogs($intent),
            'status' => $this->executeStatus($intent),
            'help' => $this->executeHelp(),
            default => CommandResult::failed("Unknown intent: {$intent->intent}"),
        };
    }

    /**
     * Deploy an application.
     */
    private function executeDeploy(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent, 'application');
        if (! $resource) {
            return CommandResult::notFound('application');
        }

        if (! ($resource instanceof Application)) {
            return CommandResult::failed('Deploy is only available for applications');
        }

        if (! $this->authorize('deploy', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            $deploymentUuid = (string) new Cuid2;
            $result = queue_application_deployment(
                application: $resource,
                deployment_uuid: $deploymentUuid,
                commit: 'HEAD',
                force_rebuild: false,
                is_api: true,
                user_id: $this->user->id,
            );

            if (is_array($result) && isset($result['status'])) {
                if ($result['status'] === 'queue_full') {
                    return CommandResult::failed($result['message']);
                }
                if ($result['status'] === 'skipped') {
                    return CommandResult::success(
                        "Deployment already queued for this commit. Existing deployment: {$result['deployment_uuid']}",
                        ['deployment_uuid' => $result['deployment_uuid']]
                    );
                }
            }

            return CommandResult::success(
                "Deployment started for **{$resource->name}**. Deployment UUID: `{$deploymentUuid}`",
                [
                    'deployment_uuid' => $deploymentUuid,
                    'application_uuid' => $resource->uuid,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat deploy failed', ['error' => $e->getMessage(), 'application_id' => $resource->id]);

            return CommandResult::failed("Failed to deploy: {$e->getMessage()}");
        }
    }

    /**
     * Restart a resource.
     */
    private function executeRestart(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent);
        if (! $resource) {
            return CommandResult::notFound($intent->getResourceType() ?? 'resource');
        }

        if (! $this->authorize('update', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            if ($resource instanceof Application) {
                $deploymentUuid = (string) new Cuid2;
                queue_application_deployment(
                    application: $resource,
                    deployment_uuid: $deploymentUuid,
                    restart_only: true,
                    user_id: $this->user->id,
                );

                return CommandResult::success(
                    "Restarting **{$resource->name}**. Deployment UUID: `{$deploymentUuid}`",
                    ['deployment_uuid' => $deploymentUuid]
                );
            }

            if ($resource instanceof Service) {
                RestartService::dispatch($resource, false);

                return CommandResult::success("Restarting service **{$resource->name}**.");
            }

            if ($this->isDatabase($resource)) {
                RestartDatabase::dispatch($resource);

                return CommandResult::success("Restarting database **{$resource->name}**.");
            }

            return CommandResult::failed('Resource type does not support restart');
        } catch (\Throwable $e) {
            Log::error('AI Chat restart failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to restart: {$e->getMessage()}");
        }
    }

    /**
     * Stop a resource.
     */
    private function executeStop(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent);
        if (! $resource) {
            return CommandResult::notFound($intent->getResourceType() ?? 'resource');
        }

        if (! $this->authorize('update', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            if ($resource instanceof Application) {
                StopApplication::dispatch($resource);

                return CommandResult::success("Stopping application **{$resource->name}**.");
            }

            if ($resource instanceof Service) {
                StopService::dispatch($resource);

                return CommandResult::success("Stopping service **{$resource->name}**.");
            }

            if ($this->isDatabase($resource)) {
                StopDatabase::dispatch($resource);

                return CommandResult::success("Stopping database **{$resource->name}**.");
            }

            return CommandResult::failed('Resource type does not support stop');
        } catch (\Throwable $e) {
            Log::error('AI Chat stop failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to stop: {$e->getMessage()}");
        }
    }

    /**
     * Start a resource.
     */
    private function executeStart(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent);
        if (! $resource) {
            return CommandResult::notFound($intent->getResourceType() ?? 'resource');
        }

        if (! $this->authorize('update', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            if ($resource instanceof Application) {
                $deploymentUuid = (string) new Cuid2;
                queue_application_deployment(
                    application: $resource,
                    deployment_uuid: $deploymentUuid,
                    user_id: $this->user->id,
                );

                return CommandResult::success(
                    "Starting application **{$resource->name}**. Deployment UUID: `{$deploymentUuid}`",
                    ['deployment_uuid' => $deploymentUuid]
                );
            }

            if ($resource instanceof Service) {
                StartService::dispatch($resource, false);

                return CommandResult::success("Starting service **{$resource->name}**.");
            }

            if ($this->isDatabase($resource)) {
                StartDatabase::dispatch($resource);

                return CommandResult::success("Starting database **{$resource->name}**.");
            }

            return CommandResult::failed('Resource type does not support start');
        } catch (\Throwable $e) {
            Log::error('AI Chat start failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to start: {$e->getMessage()}");
        }
    }

    /**
     * Get logs for a resource.
     */
    private function executeLogs(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent);
        if (! $resource) {
            return CommandResult::notFound($intent->getResourceType() ?? 'resource');
        }

        if (! $this->authorize('view', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            $logs = $this->fetchLogs($resource);

            return CommandResult::success(
                "Logs for **{$resource->name}**:\n```\n{$logs}\n```",
                ['logs' => $logs]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat logs failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to fetch logs: {$e->getMessage()}");
        }
    }

    /**
     * Get status of a resource.
     */
    private function executeStatus(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent);
        if (! $resource) {
            return CommandResult::notFound($intent->getResourceType() ?? 'resource');
        }

        if (! $this->authorize('view', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            $status = $this->getResourceStatus($resource);

            return CommandResult::success(
                "Status of **{$resource->name}**: {$status['status']}",
                $status
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat status failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to get status: {$e->getMessage()}");
        }
    }

    /**
     * Show help information.
     */
    private function executeHelp(): CommandResult
    {
        $help = <<<'HELP'
**Available commands:**

- **deploy** - Deploy an application
- **restart** - Restart an application, service, or database
- **stop** - Stop an application, service, or database
- **start** - Start a stopped resource
- **logs** - View recent logs
- **status** - Check resource status

**Examples:**
- "Deploy my-app"
- "Restart the database"
- "Show logs for api-service"
- "What's the status of my application?"

You can also ask questions about your resources and I'll try to help!
HELP;

        return CommandResult::success($help);
    }

    /**
     * Resolve resource from intent parameters.
     */
    private function resolveResource(IntentResult $intent, ?string $preferredType = null): ?Model
    {
        $resourceType = $intent->getResourceType() ?? $preferredType;
        $resourceId = $intent->getResourceId();
        $resourceUuid = $intent->getResourceUuid();
        $resourceName = $intent->params['resource_name'] ?? null;

        // Try to find by ID first
        if ($resourceId) {
            return $this->findResourceById($resourceType, $resourceId);
        }

        // Try to find by UUID
        if ($resourceUuid) {
            return $this->findResourceByUuid($resourceType, $resourceUuid);
        }

        // Try to find by name
        if ($resourceName) {
            return $this->findResourceByName($resourceType, $resourceName);
        }

        return null;
    }

    /**
     * Find resource by ID.
     */
    private function findResourceById(?string $type, int $id): ?Model
    {
        return match ($type) {
            'application' => Application::where('id', $id)
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first(),
            'service' => Service::where('id', $id)
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first(),
            'server' => Server::where('id', $id)
                ->where('team_id', $this->teamId)
                ->first(),
            'database' => $this->findDatabaseById($id),
            default => $this->findAnyResourceById($id),
        };
    }

    /**
     * Find resource by UUID.
     */
    private function findResourceByUuid(?string $type, string $uuid): ?Model
    {
        return match ($type) {
            'application' => Application::where('uuid', $uuid)
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first(),
            'service' => Service::where('uuid', $uuid)
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first(),
            'server' => Server::where('uuid', $uuid)
                ->where('team_id', $this->teamId)
                ->first(),
            default => $this->findAnyResourceByUuid($uuid),
        };
    }

    /**
     * Find resource by name.
     */
    private function findResourceByName(?string $type, string $name): ?Model
    {
        $name = trim($name);

        return match ($type) {
            'application' => Application::where('name', 'ILIKE', "%{$name}%")
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first(),
            'service' => Service::where('name', 'ILIKE', "%{$name}%")
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first(),
            'server' => Server::where('name', 'ILIKE', "%{$name}%")
                ->where('team_id', $this->teamId)
                ->first(),
            default => $this->findAnyResourceByName($name),
        };
    }

    /**
     * Find database by ID across all database types.
     */
    private function findDatabaseById(int $id): ?Model
    {
        foreach (self::DATABASE_MODELS as $model) {
            $db = $model::where('id', $id)
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first();
            if ($db) {
                return $db;
            }
        }

        return null;
    }

    /**
     * Find any resource by ID.
     */
    private function findAnyResourceById(int $id): ?Model
    {
        // Try application first
        $app = Application::where('id', $id)
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->first();
        if ($app) {
            return $app;
        }

        // Try service
        $service = Service::where('id', $id)
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->first();
        if ($service) {
            return $service;
        }

        // Try databases
        return $this->findDatabaseById($id);
    }

    /**
     * Find any resource by UUID.
     */
    private function findAnyResourceByUuid(string $uuid): ?Model
    {
        $app = Application::where('uuid', $uuid)
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->first();
        if ($app) {
            return $app;
        }

        $service = Service::where('uuid', $uuid)
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->first();
        if ($service) {
            return $service;
        }

        $server = Server::where('uuid', $uuid)
            ->where('team_id', $this->teamId)
            ->first();
        if ($server) {
            return $server;
        }

        // Try databases
        foreach (array_unique(self::DATABASE_MODELS) as $model) {
            $db = $model::where('uuid', $uuid)
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first();
            if ($db) {
                return $db;
            }
        }

        return null;
    }

    /**
     * Find any resource by name.
     */
    private function findAnyResourceByName(string $name): ?Model
    {
        $app = Application::where('name', 'ILIKE', "%{$name}%")
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->first();
        if ($app) {
            return $app;
        }

        $service = Service::where('name', 'ILIKE', "%{$name}%")
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->first();
        if ($service) {
            return $service;
        }

        $server = Server::where('name', 'ILIKE', "%{$name}%")
            ->where('team_id', $this->teamId)
            ->first();
        if ($server) {
            return $server;
        }

        // Try databases
        foreach (array_unique(self::DATABASE_MODELS) as $model) {
            $db = $model::where('name', 'ILIKE', "%{$name}%")
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->first();
            if ($db) {
                return $db;
            }
        }

        return null;
    }

    /**
     * Check if model is a database.
     */
    private function isDatabase(Model $model): bool
    {
        return $model instanceof StandalonePostgresql
            || $model instanceof StandaloneMysql
            || $model instanceof StandaloneMariadb
            || $model instanceof StandaloneMongodb
            || $model instanceof StandaloneRedis
            || $model instanceof StandaloneKeydb
            || $model instanceof StandaloneDragonfly
            || $model instanceof StandaloneClickhouse;
    }

    /**
     * Check authorization for resource.
     */
    private function authorize(string $ability, Model $resource): bool
    {
        return Gate::forUser($this->user)->allows($ability, $resource);
    }

    /**
     * Fetch logs for a resource.
     */
    private function fetchLogs(Model $resource, int $lines = 50): string
    {
        try {
            if ($resource instanceof Application) {
                $server = $resource->destination->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                $containerName = $resource->uuid;
                $command = "docker logs --tail {$lines} {$containerName} 2>&1";
                $output = instant_remote_process([$command], $server, throwError: false);

                return $output ?: 'No logs available';
            }

            if ($resource instanceof Service) {
                $server = $resource->destination->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                // Get logs from first service application/database
                $firstApp = $resource->applications()->first();
                if ($firstApp) {
                    $containerName = $firstApp->uuid;
                    $command = "docker logs --tail {$lines} {$containerName} 2>&1";
                    $output = instant_remote_process([$command], $server, throwError: false);

                    return $output ?: 'No logs available';
                }

                return 'No container found for service';
            }

            if ($this->isDatabase($resource)) {
                $server = $resource->destination->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                $containerName = $resource->uuid;
                $command = "docker logs --tail {$lines} {$containerName} 2>&1";
                $output = instant_remote_process([$command], $server, throwError: false);

                return $output ?: 'No logs available';
            }

            return 'Resource type does not support logs';
        } catch (\Throwable $e) {
            return "Error fetching logs: {$e->getMessage()}";
        }
    }

    /**
     * Get resource status.
     */
    private function getResourceStatus(Model $resource): array
    {
        $status = [
            'name' => $resource->name ?? 'Unknown',
            'type' => class_basename($resource),
            'status' => 'unknown',
        ];

        if ($resource instanceof Application) {
            $status['status'] = $resource->status ?? 'unknown';
            $status['uuid'] = $resource->uuid;
            $status['fqdn'] = $resource->fqdn;
        } elseif ($resource instanceof Service) {
            $status['status'] = $resource->status() ?? 'unknown';
            $status['uuid'] = $resource->uuid;
        } elseif ($resource instanceof Server) {
            $status['status'] = $resource->isFunctional() ? 'running' : 'not_functional';
            $status['uuid'] = $resource->uuid;
            $status['ip'] = $resource->ip;
        } elseif ($this->isDatabase($resource)) {
            $status['status'] = $resource->status ?? 'unknown';
            $status['uuid'] = $resource->uuid;
        }

        return $status;
    }
}
