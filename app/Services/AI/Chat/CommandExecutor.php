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
use App\Models\Project;
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
use App\Services\AI\Chat\DTOs\ParsedCommand;
use App\Services\AI\Chat\DTOs\ParsedIntent;
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
     * Execute a command based on intent (legacy single command).
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
            'delete' => $this->executeDelete($intent),
            'help' => $this->executeHelp(),
            default => CommandResult::failed("Unknown intent: {$intent->intent}"),
        };
    }

    /**
     * Execute multiple commands from ParsedIntent.
     *
     * @return CommandResult[] Array of results for each command
     */
    public function executeMultiple(ParsedIntent $parsedIntent): array
    {
        $results = [];

        if (! $parsedIntent->hasCommands()) {
            return [CommandResult::failed('Не обнаружено команд для выполнения')];
        }

        foreach ($parsedIntent->commands as $command) {
            $results[] = $this->executeCommand($command);
        }

        return $results;
    }

    /**
     * Execute a single ParsedCommand.
     */
    public function executeCommand(ParsedCommand $command): CommandResult
    {
        return match ($command->action) {
            'deploy' => $this->executeDeployCommand($command),
            'restart' => $this->executeRestartCommand($command),
            'stop' => $this->executeStopCommand($command),
            'start' => $this->executeStartCommand($command),
            'logs' => $this->executeLogsCommand($command),
            'status' => $this->executeStatusCommand($command),
            'delete' => $this->executeDeleteCommand($command),
            'help' => $this->executeHelp(),
            default => CommandResult::failed("Неизвестное действие: {$command->action}"),
        };
    }

    /**
     * Deploy an application (new method for ParsedCommand).
     */
    private function executeDeployCommand(ParsedCommand $command): CommandResult
    {
        $resource = $this->resolveResourceFromCommand($command, 'application');
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('deploy', 'application');
            }

            return CommandResult::notFound('application');
        }

        if (! ($resource instanceof Application)) {
            return CommandResult::failed('Deploy доступен только для приложений');
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
                        "Деплой уже в очереди для этого коммита. UUID: {$result['deployment_uuid']}",
                        ['deployment_uuid' => $result['deployment_uuid']]
                    );
                }
            }

            return CommandResult::success(
                "🚀 Запущен деплой **{$resource->name}**. UUID: `{$deploymentUuid}`",
                ['deployment_uuid' => $deploymentUuid, 'application_uuid' => $resource->uuid]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat deploy failed', ['error' => $e->getMessage(), 'application_id' => $resource->id]);

            return CommandResult::failed("Ошибка деплоя: {$e->getMessage()}");
        }
    }

    /**
     * Restart a resource (new method for ParsedCommand).
     */
    private function executeRestartCommand(ParsedCommand $command): CommandResult
    {
        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('restart', $command->resourceType);
            }

            return CommandResult::notFound($command->resourceType ?? 'resource');
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
                    "🔄 Перезапуск **{$resource->name}**. UUID: `{$deploymentUuid}`",
                    ['deployment_uuid' => $deploymentUuid]
                );
            }

            if ($resource instanceof Service) {
                RestartService::dispatch($resource, false);

                return CommandResult::success("🔄 Перезапуск сервиса **{$resource->name}**.");
            }

            if ($this->isDatabase($resource)) {
                RestartDatabase::dispatch($resource);

                return CommandResult::success("🔄 Перезапуск базы данных **{$resource->name}**.");
            }

            return CommandResult::failed('Этот тип ресурса не поддерживает перезапуск');
        } catch (\Throwable $e) {
            Log::error('AI Chat restart failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка перезапуска: {$e->getMessage()}");
        }
    }

    /**
     * Stop a resource (new method for ParsedCommand).
     */
    private function executeStopCommand(ParsedCommand $command): CommandResult
    {
        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('stop', $command->resourceType);
            }

            return CommandResult::notFound($command->resourceType ?? 'resource');
        }

        if (! $this->authorize('update', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            if ($resource instanceof Application) {
                StopApplication::dispatch($resource);

                return CommandResult::success("⏹️ Остановка приложения **{$resource->name}**.");
            }

            if ($resource instanceof Service) {
                StopService::dispatch($resource);

                return CommandResult::success("⏹️ Остановка сервиса **{$resource->name}**.");
            }

            if ($this->isDatabase($resource)) {
                StopDatabase::dispatch($resource);

                return CommandResult::success("⏹️ Остановка базы данных **{$resource->name}**.");
            }

            return CommandResult::failed('Этот тип ресурса не поддерживает остановку');
        } catch (\Throwable $e) {
            Log::error('AI Chat stop failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка остановки: {$e->getMessage()}");
        }
    }

    /**
     * Start a resource (new method for ParsedCommand).
     */
    private function executeStartCommand(ParsedCommand $command): CommandResult
    {
        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('start', $command->resourceType);
            }

            return CommandResult::notFound($command->resourceType ?? 'resource');
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
                    "▶️ Запуск приложения **{$resource->name}**. UUID: `{$deploymentUuid}`",
                    ['deployment_uuid' => $deploymentUuid]
                );
            }

            if ($resource instanceof Service) {
                StartService::dispatch($resource, false);

                return CommandResult::success("▶️ Запуск сервиса **{$resource->name}**.");
            }

            if ($this->isDatabase($resource)) {
                StartDatabase::dispatch($resource);

                return CommandResult::success("▶️ Запуск базы данных **{$resource->name}**.");
            }

            return CommandResult::failed('Этот тип ресурса не поддерживает запуск');
        } catch (\Throwable $e) {
            Log::error('AI Chat start failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка запуска: {$e->getMessage()}");
        }
    }

    /**
     * Get logs (new method for ParsedCommand).
     */
    private function executeLogsCommand(ParsedCommand $command): CommandResult
    {
        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('view logs for', $command->resourceType);
            }

            return CommandResult::notFound($command->resourceType ?? 'resource');
        }

        if (! $this->authorize('view', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            $logs = $this->fetchLogs($resource);

            return CommandResult::success(
                "📋 Логи **{$resource->name}**:\n```\n{$logs}\n```",
                ['logs' => $logs]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat logs failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка получения логов: {$e->getMessage()}");
        }
    }

    /**
     * Get status (new method for ParsedCommand).
     */
    private function executeStatusCommand(ParsedCommand $command): CommandResult
    {
        $resource = $this->resolveResourceFromCommand($command);

        if (! $resource) {
            return $this->executeStatusOverview();
        }

        if (! $this->authorize('view', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            $status = $this->getResourceStatus($resource);

            return CommandResult::success(
                "📊 Статус **{$resource->name}**: {$status['status']}",
                $status
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat status failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка получения статуса: {$e->getMessage()}");
        }
    }

    /**
     * Delete a resource (new method for ParsedCommand).
     */
    private function executeDeleteCommand(ParsedCommand $command): CommandResult
    {
        // Handle project deletion
        if ($command->resourceType === 'project' || $this->looksLikeProject($command->resourceName)) {
            return $this->executeDeleteProjectCommand($command);
        }

        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listDeletableResources();
            }

            return CommandResult::notFound($command->resourceType ?? 'resource');
        }

        if (! $this->authorize('delete', $resource)) {
            return CommandResult::unauthorized('У вас нет прав на удаление этого ресурса.');
        }

        try {
            $resourceName = $resource->name ?? 'Unknown';
            $resourceClass = class_basename($resource);

            $resource->delete();

            return CommandResult::success(
                "✅ Успешно удалён {$resourceClass} **{$resourceName}**.",
                ['deleted' => true, 'type' => $resourceClass, 'name' => $resourceName]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat delete failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка удаления: {$e->getMessage()}");
        }
    }

    /**
     * Delete a project (new method for ParsedCommand).
     */
    private function executeDeleteProjectCommand(ParsedCommand $command): CommandResult
    {
        $projectName = $command->resourceName ?? $command->projectName;

        if (! $projectName) {
            return $this->listDeletableProjects('Какой проект вы хотите удалить?');
        }

        $project = Project::where('team_id', $this->teamId)
            ->where('name', 'ILIKE', "%{$projectName}%")
            ->first();

        if (! $project) {
            return CommandResult::notFound('project');
        }

        if (! $this->authorize('delete', $project)) {
            return CommandResult::unauthorized('У вас нет прав на удаление этого проекта.');
        }

        try {
            $deletedName = $project->name;
            $project->delete();

            return CommandResult::success(
                "✅ Успешно удалён проект **{$deletedName}**.",
                ['deleted' => true, 'type' => 'Project', 'name' => $deletedName]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat delete project failed', ['error' => $e->getMessage(), 'project_id' => $project->id]);

            return CommandResult::failed("Ошибка удаления проекта: {$e->getMessage()}");
        }
    }

    /**
     * Resolve resource from ParsedCommand.
     */
    private function resolveResourceFromCommand(ParsedCommand $command, ?string $preferredType = null): ?Model
    {
        $resourceType = $command->resourceType ?? $preferredType;
        $resourceName = $command->resourceName;
        $projectName = $command->projectName;
        $envName = $command->environmentName;

        if (! $resourceName && ! $command->resourceId && ! $command->resourceUuid) {
            return null;
        }

        // Try by ID
        if ($command->resourceId) {
            return $this->findResourceById($resourceType, $command->resourceId);
        }

        // Try by UUID
        if ($command->resourceUuid) {
            return $this->findResourceByUuid($resourceType, $command->resourceUuid);
        }

        // Try by name
        if ($resourceName) {
            return $this->findResourceByName($resourceType, $resourceName, $projectName, $envName);
        }

        return null;
    }

    /**
     * Format multiple results into a single message.
     */
    public static function formatMultipleResults(array $results): string
    {
        if (count($results) === 0) {
            return 'Не выполнено ни одной команды.';
        }

        if (count($results) === 1) {
            return $results[0]->message;
        }

        $output = "**Результаты выполнения команд:**\n\n";

        foreach ($results as $i => $result) {
            $icon = $result->success ? '✓' : '✗';
            $output .= "{$icon} {$result->message}\n";
        }

        $successCount = count(array_filter($results, fn ($r) => $r->success));
        $totalCount = count($results);

        $output .= "\n---\n";
        $output .= "Выполнено успешно: {$successCount}/{$totalCount}";

        return $output;
    }

    /**
     * Deploy an application.
     */
    private function executeDeploy(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent, 'application');
        if (! $resource) {
            // Check if user didn't specify which resource
            if (! $this->hasResourceSpecified($intent)) {
                return $this->listAvailableResources('deploy', 'application');
            }

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
            if (! $this->hasResourceSpecified($intent)) {
                return $this->listAvailableResources('restart', $intent->getResourceType());
            }

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
            if (! $this->hasResourceSpecified($intent)) {
                return $this->listAvailableResources('stop', $intent->getResourceType());
            }

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
            if (! $this->hasResourceSpecified($intent)) {
                return $this->listAvailableResources('start', $intent->getResourceType());
            }

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
            if (! $this->hasResourceSpecified($intent)) {
                return $this->listAvailableResources('view logs for', $intent->getResourceType());
            }

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
     * Get status of a resource or overview of all resources.
     */
    private function executeStatus(IntentResult $intent): CommandResult
    {
        $resource = $this->resolveResource($intent);

        // If no specific resource, show overview
        if (! $resource) {
            return $this->executeStatusOverview();
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
     * Show status overview of all resources.
     */
    private function executeStatusOverview(): CommandResult
    {
        try {
            $apps = Application::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->select('name', 'status', 'uuid')
                ->take(10)
                ->get();

            $services = Service::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->select('name', 'uuid')
                ->take(10)
                ->get();

            $servers = Server::where('team_id', $this->teamId)
                ->select('name', 'uuid', 'ip')
                ->take(10)
                ->get();

            $output = "**Resources Overview:**\n\n";

            if ($apps->count() > 0) {
                $output .= "**Applications:**\n";
                foreach ($apps as $app) {
                    $statusEmoji = $app->status === 'running' ? '🟢' : ($app->status === 'stopped' ? '🔴' : '🟡');
                    $output .= "- {$statusEmoji} **{$app->name}** - {$app->status}\n";
                }
                $output .= "\n";
            }

            if ($services->count() > 0) {
                $output .= "**Services:**\n";
                foreach ($services as $service) {
                    $output .= "- **{$service->name}**\n";
                }
                $output .= "\n";
            }

            if ($servers->count() > 0) {
                $output .= "**Servers:**\n";
                foreach ($servers as $server) {
                    $output .= "- **{$server->name}** ({$server->ip})\n";
                }
            }

            if ($apps->count() === 0 && $services->count() === 0 && $servers->count() === 0) {
                $output = 'No resources found in your team. Try creating an application or adding a server first.';
            }

            $output .= "\n\n*To check a specific resource, say: \"status of [resource name]\"*";

            return CommandResult::success($output, [
                'applications' => $apps->count(),
                'services' => $services->count(),
                'servers' => $servers->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Chat status overview failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to get status overview: {$e->getMessage()}");
        }
    }

    /**
     * Delete a resource (project, application, service, or database).
     */
    private function executeDelete(IntentResult $intent): CommandResult
    {
        $resourceType = $intent->getResourceType();
        $resourceName = $intent->params['resource_name'] ?? null;

        // Handle project deletion
        if ($resourceType === 'project' || $this->looksLikeProject($resourceName)) {
            return $this->executeDeleteProject($intent);
        }

        // Handle other resources (application, service, database)
        $resource = $this->resolveResource($intent);
        if (! $resource) {
            if (! $this->hasResourceSpecified($intent)) {
                return $this->listDeletableResources();
            }

            return CommandResult::notFound($resourceType ?? 'resource');
        }

        if (! $this->authorize('delete', $resource)) {
            return CommandResult::unauthorized('You do not have permission to delete this resource.');
        }

        try {
            $resourceName = $resource->name ?? 'Unknown';
            $resourceClass = class_basename($resource);

            $resource->delete();

            return CommandResult::success(
                "✅ Successfully deleted {$resourceClass} **{$resourceName}**.",
                ['deleted' => true, 'type' => $resourceClass, 'name' => $resourceName]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat delete failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Failed to delete: {$e->getMessage()}");
        }
    }

    /**
     * Delete a project.
     */
    private function executeDeleteProject(IntentResult $intent): CommandResult
    {
        $projectName = $intent->params['resource_name'] ?? $intent->params['project_name'] ?? null;
        $excludeNames = $intent->params['exclude_names'] ?? [];

        // If user wants to delete all projects except some
        if ($this->isDeleteAllExceptRequest($intent)) {
            return $this->executeDeleteAllProjectsExcept($excludeNames);
        }

        if (! $projectName) {
            return $this->listDeletableProjects('Which project do you want to delete?');
        }

        $project = Project::where('team_id', $this->teamId)
            ->where('name', 'ILIKE', "%{$projectName}%")
            ->first();

        if (! $project) {
            return CommandResult::notFound('project');
        }

        if (! $this->authorize('delete', $project)) {
            return CommandResult::unauthorized('You do not have permission to delete this project.');
        }

        try {
            $deletedName = $project->name;
            $project->delete();

            return CommandResult::success(
                "✅ Successfully deleted project **{$deletedName}**.",
                ['deleted' => true, 'type' => 'Project', 'name' => $deletedName]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat delete project failed', ['error' => $e->getMessage(), 'project_id' => $project->id]);

            return CommandResult::failed("Failed to delete project: {$e->getMessage()}");
        }
    }

    /**
     * Delete all projects except specified ones.
     */
    private function executeDeleteAllProjectsExcept(array $excludeNames): CommandResult
    {
        $query = Project::where('team_id', $this->teamId);

        if (! empty($excludeNames)) {
            foreach ($excludeNames as $name) {
                $query->where('name', 'NOT ILIKE', "%{$name}%");
            }
        }

        $projectsToDelete = $query->get();

        if ($projectsToDelete->isEmpty()) {
            return CommandResult::success('No projects to delete (all projects match the exclusion criteria).');
        }

        $deleted = [];
        $failed = [];

        foreach ($projectsToDelete as $project) {
            if (! $this->authorize('delete', $project)) {
                $failed[] = "{$project->name} (no permission)";

                continue;
            }

            try {
                $deleted[] = $project->name;
                $project->delete();
            } catch (\Throwable $e) {
                $failed[] = "{$project->name} ({$e->getMessage()})";
                Log::error('AI Chat bulk delete project failed', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            }
        }

        $output = '';
        if (! empty($deleted)) {
            $output .= "✅ Successfully deleted projects:\n";
            foreach ($deleted as $name) {
                $output .= "- **{$name}**\n";
            }
        }

        if (! empty($failed)) {
            $output .= "\n⚠️ Failed to delete:\n";
            foreach ($failed as $info) {
                $output .= "- {$info}\n";
            }
        }

        return CommandResult::success($output, [
            'deleted' => $deleted,
            'failed' => $failed,
        ]);
    }

    /**
     * Check if the delete request looks like "delete all except X".
     */
    private function isDeleteAllExceptRequest(IntentResult $intent): bool
    {
        return ! empty($intent->params['exclude_names']) ||
               ! empty($intent->params['delete_all_except']);
    }

    /**
     * Check if the resource name looks like a project (not application/service/database).
     */
    private function looksLikeProject(?string $name): bool
    {
        if (! $name) {
            return false;
        }

        // Check if this name matches a project
        return Project::where('team_id', $this->teamId)
            ->where('name', 'ILIKE', "%{$name}%")
            ->exists();
    }

    /**
     * List projects that can be deleted.
     */
    private function listDeletableProjects(string $message = 'Available projects:'): CommandResult
    {
        $projects = Project::where('team_id', $this->teamId)
            ->select('id', 'name', 'uuid')
            ->orderBy('name')
            ->take(20)
            ->get();

        if ($projects->isEmpty()) {
            return CommandResult::success('No projects found in your team.');
        }

        $output = "**{$message}**\n\n";
        foreach ($projects as $project) {
            $output .= "- **{$project->name}**\n";
        }
        $output .= "\n*Say: \"delete [project name]\" to delete a specific project.*";

        return CommandResult::success($output, ['projects' => $projects->pluck('name')->toArray()]);
    }

    /**
     * List all deletable resources.
     */
    private function listDeletableResources(): CommandResult
    {
        $output = "**What would you like to delete?**\n\n";

        // Projects
        $projects = Project::where('team_id', $this->teamId)
            ->select('name')
            ->orderBy('name')
            ->take(10)
            ->get();

        if ($projects->isNotEmpty()) {
            $output .= "**Projects:**\n";
            foreach ($projects as $project) {
                $output .= "- {$project->name}\n";
            }
            $output .= "\n";
        }

        // Applications
        $apps = Application::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->select('name')
            ->orderBy('name')
            ->take(10)
            ->get();

        if ($apps->isNotEmpty()) {
            $output .= "**Applications:**\n";
            foreach ($apps as $app) {
                $output .= "- {$app->name}\n";
            }
            $output .= "\n";
        }

        $output .= '*Say: "delete [resource name]" to delete a specific resource.*';

        return CommandResult::success($output);
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
- **delete** - Delete a project, application, service, or database

**Examples:**
- "Deploy my-app"
- "Restart the database"
- "Show logs for api-service"
- "What's the status of my application?"
- "Delete project test-project"
- "Delete all projects except PIXELPETS"

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
        $projectName = $intent->params['project_name'] ?? null;
        $envName = $intent->params['environment_name'] ?? null;

        // Try to find by ID first
        if ($resourceId) {
            return $this->findResourceById($resourceType, $resourceId);
        }

        // Try to find by UUID
        if ($resourceUuid) {
            return $this->findResourceByUuid($resourceType, $resourceUuid);
        }

        // Try to find by name (with optional project/environment filter)
        if ($resourceName) {
            return $this->findResourceByName($resourceType, $resourceName, $projectName, $envName);
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
     * Find resource by name with optional project/environment filter.
     */
    private function findResourceByName(?string $type, string $name, ?string $projectName = null, ?string $envName = null): ?Model
    {
        $name = trim($name);

        return match ($type) {
            'application' => $this->findApplicationByName($name, $projectName, $envName),
            'service' => $this->findServiceByName($name, $projectName, $envName),
            'server' => Server::where('name', 'ILIKE', "%{$name}%")
                ->where('team_id', $this->teamId)
                ->first(),
            default => $this->findAnyResourceByName($name, $projectName, $envName),
        };
    }

    /**
     * Find application by name with project/environment filter.
     */
    private function findApplicationByName(string $name, ?string $projectName = null, ?string $envName = null): ?Application
    {
        $query = Application::where('name', 'ILIKE', "%{$name}%")
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));

        if ($projectName) {
            $query->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', "%{$projectName}%"));
        }
        if ($envName) {
            $query->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', "%{$envName}%"));
        }

        return $query->first();
    }

    /**
     * Find service by name with project/environment filter.
     */
    private function findServiceByName(string $name, ?string $projectName = null, ?string $envName = null): ?Service
    {
        $query = Service::where('name', 'ILIKE', "%{$name}%")
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));

        if ($projectName) {
            $query->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', "%{$projectName}%"));
        }
        if ($envName) {
            $query->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', "%{$envName}%"));
        }

        return $query->first();
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
     * Find any resource by name with optional project/environment filter.
     */
    private function findAnyResourceByName(string $name, ?string $projectName = null, ?string $envName = null): ?Model
    {
        // Try application
        $app = $this->findApplicationByName($name, $projectName, $envName);
        if ($app) {
            return $app;
        }

        // Try service
        $service = $this->findServiceByName($name, $projectName, $envName);
        if ($service) {
            return $service;
        }

        // Server (no project/environment)
        $server = Server::where('name', 'ILIKE', "%{$name}%")
            ->where('team_id', $this->teamId)
            ->first();
        if ($server) {
            return $server;
        }

        // Try databases with project/environment filter
        foreach (array_unique(self::DATABASE_MODELS) as $model) {
            $query = $model::where('name', 'ILIKE', "%{$name}%")
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));

            if ($projectName) {
                $query->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', "%{$projectName}%"));
            }
            if ($envName) {
                $query->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', "%{$envName}%"));
            }

            $db = $query->first();
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

    /**
     * Check if intent has a resource specified.
     */
    private function hasResourceSpecified(IntentResult $intent): bool
    {
        return $intent->getResourceId() !== null
            || $intent->getResourceUuid() !== null
            || ! empty($intent->params['resource_name']);
    }

    /**
     * List available resources for the user to choose from.
     */
    private function listAvailableResources(string $action, ?string $resourceType = null): CommandResult
    {
        $resources = [];

        // Get applications with project and environment info
        if (! $resourceType || $resourceType === 'application') {
            $apps = Application::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->with(['environment.project'])
                ->orderBy('name')
                ->take(20)
                ->get();

            foreach ($apps as $app) {
                $resources[] = [
                    'name' => $app->name,
                    'status' => $app->status,
                    'type' => 'application',
                    'project' => $app->environment?->project?->name ?? 'Unknown',
                    'environment' => $app->environment?->name ?? 'default',
                    'uuid' => $app->uuid,
                ];
            }
        }

        // Get services with project and environment info
        if (! $resourceType || $resourceType === 'service') {
            $services = Service::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->with(['environment.project'])
                ->orderBy('name')
                ->take(20)
                ->get();

            foreach ($services as $service) {
                $resources[] = [
                    'name' => $service->name,
                    'type' => 'service',
                    'project' => $service->environment?->project?->name ?? 'Unknown',
                    'environment' => $service->environment?->name ?? 'default',
                    'uuid' => $service->uuid,
                ];
            }
        }

        // Get databases with project and environment info
        if (! $resourceType || $resourceType === 'database') {
            foreach (array_unique(self::DATABASE_MODELS) as $model) {
                $dbs = $model::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                    ->with(['environment.project'])
                    ->orderBy('name')
                    ->take(10)
                    ->get();

                foreach ($dbs as $db) {
                    $resources[] = [
                        'name' => $db->name,
                        'status' => $db->status ?? 'unknown',
                        'type' => 'database',
                        'project' => $db->environment?->project?->name ?? 'Unknown',
                        'environment' => $db->environment?->name ?? 'default',
                        'uuid' => $db->uuid,
                    ];
                }
            }
        }

        $displayType = $resourceType ?? 'resource';

        return CommandResult::needsResource($action, $displayType, $resources);
    }
}
