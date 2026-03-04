<?php

namespace App\Services\AI\Chat;

use App\Actions\Application\StopApplication;
use App\Actions\Database\RestartDatabase;
use App\Actions\Database\StartDatabase;
use App\Actions\Database\StopDatabase;
use App\Actions\Service\RestartService;
use App\Actions\Service\StartService;
use App\Actions\Service\StopService;
use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
use App\Models\CodeReview;
use App\Models\DeploymentLogAnalysis;
use App\Models\Environment;
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
use App\Services\AI\DeploymentLogAnalyzer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Visus\Cuid2\Cuid2;

/**
 * Executes commands based on intent detection.
 */
class CommandExecutor
{
    private User $user;

    private int $teamId;

    /**
     * Rate limiting configuration for dangerous operations.
     * Format: [operation => [max_attempts_per_minute, decay_seconds]]
     */
    private const RATE_LIMITS = [
        'deploy' => [10, 60],      // 10 deploys per minute
        'rollback' => [5, 60],     // 5 rollbacks per minute
        'delete' => [5, 60],       // 5 deletes per minute
        'restart' => [10, 60],     // 10 restarts per minute
        'stop' => [10, 60],        // 10 stops per minute
    ];

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
     * SECURITY: Escape special ILIKE/LIKE pattern characters.
     *
     * Without this, user input containing % or _ could alter query logic.
     * Example: searching for "%admin%" would match anything containing "admin".
     */
    private function escapeIlike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * SECURITY: Check rate limit for dangerous operations to prevent DOS attacks.
     *
     * @return CommandResult|null Returns error result if rate limited, null if OK
     */
    private function checkRateLimit(string $operation): ?CommandResult
    {
        if (! isset(self::RATE_LIMITS[$operation])) {
            return null;
        }

        [$maxAttempts, $decaySeconds] = self::RATE_LIMITS[$operation];
        $key = "ai_chat:{$operation}:{$this->user->id}:{$this->teamId}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('AI Chat rate limit exceeded', [
                'user_id' => $this->user->id,
                'team_id' => $this->teamId,
                'operation' => $operation,
                'retry_after' => $seconds,
            ]);

            return CommandResult::failed(
                "⏳ Слишком много запросов на {$operation}. Подождите {$seconds} секунд."
            );
        }

        RateLimiter::hit($key, $decaySeconds);

        return null;
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
            'rollback' => $this->executeRollbackCommand($command),
            'restart' => $this->executeRestartCommand($command),
            'stop' => $this->executeStopCommand($command),
            'start' => $this->executeStartCommand($command),
            'logs' => $this->executeLogsCommand($command),
            'status' => $this->executeStatusCommand($command),
            'delete' => $this->executeDeleteCommand($command),
            'analyze_errors' => $this->executeAnalyzeErrorsCommand($command),
            'analyze_deployment' => $this->executeAnalyzeDeploymentCommand($command),
            'code_review' => $this->executeCodeReviewCommand($command),
            'health_check' => $this->executeHealthCheckCommand($command),
            'metrics' => $this->executeMetricsCommand($command),
            'help' => $this->executeHelp(),
            default => CommandResult::failed("Неизвестное действие: {$command->action}"),
        };
    }

    /**
     * Deploy an application (new method for ParsedCommand).
     */
    private function executeDeployCommand(ParsedCommand $command): CommandResult
    {
        // SECURITY: Rate limit deploy operations
        if ($rateLimitResult = $this->checkRateLimit('deploy')) {
            return $rateLimitResult;
        }

        $resource = $this->resolveResourceFromCommand($command, 'application');
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('deploy', 'application');
            }

            return $this->notFoundWithSuggestions($command);
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
     * Rollback an application to its last successful (or specified) deployment.
     */
    private function executeRollbackCommand(ParsedCommand $command): CommandResult
    {
        // SECURITY: Rate limit rollback operations
        if ($rateLimitResult = $this->checkRateLimit('rollback')) {
            return $rateLimitResult;
        }

        $resource = $this->resolveResourceFromCommand($command, 'application');
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('rollback', 'application');
            }

            return $this->notFoundWithSuggestions($command);
        }

        if (! ($resource instanceof Application)) {
            return CommandResult::failed('Rollback доступен только для приложений');
        }

        if (! $this->authorize('deploy', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            // Find the target deployment: by UUID if specified, otherwise the last successful one
            $targetDeployment = null;

            if ($command->deploymentUuid) {
                $targetDeployment = ApplicationDeploymentQueue::where('deployment_uuid', $command->deploymentUuid)
                    ->where('application_id', $resource->id)
                    ->where('status', ApplicationDeploymentStatus::FINISHED->value)
                    ->first();

                if (! $targetDeployment) {
                    return CommandResult::failed(
                        "Деплой `{$command->deploymentUuid}` не найден или не является успешным"
                    );
                }
            } else {
                // Last successful deployment (skip any current in-progress ones)
                $targetDeployment = ApplicationDeploymentQueue::where('application_id', $resource->id)
                    ->where('status', ApplicationDeploymentStatus::FINISHED->value)
                    ->where('pull_request_id', 0)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (! $targetDeployment || ! $targetDeployment->commit) {
                return CommandResult::failed(
                    "Нет успешных деплоев для отката приложения **{$resource->name}**"
                );
            }

            // Queue the rollback deployment
            $deploymentUuid = (string) new Cuid2;
            queue_application_deployment(
                application: $resource,
                deployment_uuid: $deploymentUuid,
                commit: $targetDeployment->commit,
                rollback: true,
                no_questions_asked: true,
                user_id: $this->user->id,
            );

            // Record the manual rollback event
            ApplicationRollbackEvent::createEvent(
                application: $resource,
                reason: ApplicationRollbackEvent::REASON_MANUAL,
                type: 'manual',
                user: $this->user,
            );

            Log::info('AI Chat rollback triggered', [
                'user_id' => $this->user->id,
                'application_id' => $resource->id,
                'target_commit' => $targetDeployment->commit,
                'deployment_uuid' => $deploymentUuid,
            ]);

            return CommandResult::success(
                "⏪ Откат **{$resource->name}** до коммита `{$targetDeployment->commit}`. UUID: `{$deploymentUuid}`",
                [
                    'deployment_uuid' => $deploymentUuid,
                    'application_uuid' => $resource->uuid,
                    'target_commit' => $targetDeployment->commit,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('AI Chat rollback failed', ['error' => $e->getMessage(), 'application_id' => $resource->id]);

            return CommandResult::failed("Ошибка отката: {$e->getMessage()}");
        }
    }

    /**
     * Restart a resource (new method for ParsedCommand).
     */
    private function executeRestartCommand(ParsedCommand $command): CommandResult
    {
        // SECURITY: Rate limit restart operations
        if ($rateLimitResult = $this->checkRateLimit('restart')) {
            return $rateLimitResult;
        }

        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('restart', $command->resourceType);
            }

            return $this->notFoundWithSuggestions($command);
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

                return CommandResult::success("🔄 Перезапуск базы данных **{$resource->getAttribute('name')}**.");
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
        // SECURITY: Rate limit stop operations
        if ($rateLimitResult = $this->checkRateLimit('stop')) {
            return $rateLimitResult;
        }

        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listAvailableResources('stop', $command->resourceType);
            }

            return $this->notFoundWithSuggestions($command);
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

                return CommandResult::success("⏹️ Остановка базы данных **{$resource->getAttribute('name')}**.");
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

            return $this->notFoundWithSuggestions($command);
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

                return CommandResult::success("▶️ Запуск базы данных **{$resource->getAttribute('name')}**.");
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

            return $this->notFoundWithSuggestions($command);
        }

        if (! $this->authorize('view', $resource)) {
            return CommandResult::unauthorized();
        }

        try {
            $logs = $this->fetchLogs($resource);

            return CommandResult::success(
                "📋 Логи **{$resource->getAttribute('name')}**:\n```\n{$logs}\n```",
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
                "📊 Статус **{$resource->getAttribute('name')}**: {$status['status']}",
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
        // SECURITY: Rate limit delete operations (most restrictive)
        if ($rateLimitResult = $this->checkRateLimit('delete')) {
            return $rateLimitResult;
        }

        // Handle project deletion
        if ($command->resourceType === 'project' || $this->looksLikeProject($command->resourceName)) {
            return $this->executeDeleteProjectCommand($command);
        }

        $resource = $this->resolveResourceFromCommand($command);
        if (! $resource) {
            if (! $command->hasResource()) {
                return $this->listDeletableResources();
            }

            return $this->notFoundWithSuggestions($command);
        }

        if (! $this->authorize('delete', $resource)) {
            return CommandResult::unauthorized('У вас нет прав на удаление этого ресурса.');
        }

        try {
            $resourceName = $resource->getAttribute('name') ?? 'Unknown';
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
        // Handle "delete all except" case
        if ($command->targetScope === 'all' || $command->targetScope === 'all_except') {
            $excludeNames = $command->resourceNames ?? [];
            // Also check resourceName as single exclusion
            if ($command->resourceName && ! in_array($command->resourceName, $excludeNames)) {
                $excludeNames[] = $command->resourceName;
            }

            return $this->executeDeleteAllProjectsExceptCommand($excludeNames);
        }

        $projectName = $command->resourceName ?? $command->projectName;

        if (! $projectName) {
            return $this->listDeletableProjects('Какой проект вы хотите удалить?');
        }

        $project = Project::where('team_id', $this->teamId)
            ->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%')
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
     * Delete all projects except specified ones.
     */
    private function executeDeleteAllProjectsExceptCommand(array $excludeNames): CommandResult
    {
        $query = Project::where('team_id', $this->teamId);

        // Exclude projects by name (case-insensitive partial match)
        if (! empty($excludeNames)) {
            foreach ($excludeNames as $name) {
                $query->where('name', 'NOT ILIKE', '%'.$this->escapeIlike($name).'%');
            }
        }

        $projectsToDelete = $query->get();

        if ($projectsToDelete->isEmpty()) {
            return CommandResult::success('Нет проектов для удаления (все проекты соответствуют критериям исключения).');
        }

        $deleted = [];
        $failed = [];

        foreach ($projectsToDelete as $project) {
            if (! $this->authorize('delete', $project)) {
                $failed[] = "{$project->name} (нет прав)";

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
            $output .= "✅ Успешно удалены проекты:\n";
            foreach ($deleted as $name) {
                $output .= "- **{$name}**\n";
            }
        }

        if (! empty($failed)) {
            $output .= "\n⚠️ Не удалось удалить:\n";
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
     * Preview what would be deleted for bulk delete operations.
     * Returns detailed info for confirmation messages.
     *
     * @return array{toDelete: array, toKeep: array, message: string}
     */
    public function previewBulkDelete(ParsedCommand $command): array
    {
        if ($command->resourceType !== 'project') {
            return [
                'toDelete' => [],
                'toKeep' => [],
                'message' => 'Preview only supported for project deletions.',
            ];
        }

        $excludeNames = $command->resourceNames ?? [];
        if ($command->resourceName && ! in_array($command->resourceName, $excludeNames)) {
            $excludeNames[] = $command->resourceName;
        }

        // Get all projects
        $allProjects = Project::where('team_id', $this->teamId)->get();

        $toDelete = [];
        $toKeep = [];

        foreach ($allProjects as $project) {
            $isExcluded = false;
            foreach ($excludeNames as $name) {
                if (stripos($project->name, $name) !== false) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                $toKeep[] = $project->name;
            } else {
                $toDelete[] = $project->name;
            }
        }

        $message = '';
        if (empty($toDelete)) {
            $message = 'Нет проектов для удаления.';
        } else {
            $message = "⚠️ **Будут удалены следующие проекты:**\n\n";
            foreach ($toDelete as $name) {
                $message .= "- 🗑️ **{$name}**\n";
            }

            if (! empty($toKeep)) {
                $message .= "\n✅ **Останутся:**\n";
                foreach ($toKeep as $name) {
                    $message .= "- {$name}\n";
                }
            }

            $message .= "\n⚠️ Это действие **необратимо**! Подтвердите, ответив **'да'** или **'confirm'**.";
        }

        return [
            'toDelete' => $toDelete,
            'toKeep' => $toKeep,
            'message' => $message,
        ];
    }

    /**
     * Analyze errors in resource logs using AI.
     */
    private function executeAnalyzeErrorsCommand(ParsedCommand $command): CommandResult
    {
        try {
            $analyzer = new ResourceErrorAnalyzer;

            // Handle different scopes
            $scope = $command->targetScope ?? 'single';

            if ($scope === 'all') {
                return $this->analyzeAllResourcesErrors($analyzer);
            }

            if ($scope === 'multiple' && ! empty($command->resourceNames)) {
                return $this->analyzeMultipleResourcesErrors($analyzer, $command->resourceNames);
            }

            // Single resource
            $resource = $this->resolveResourceFromCommand($command);
            if (! $resource) {
                if (! $command->hasResource()) {
                    return $this->listAvailableResources('analyze errors for', $command->resourceType);
                }

                return $this->notFoundWithSuggestions($command);
            }

            if (! $this->authorize('view', $resource)) {
                return CommandResult::unauthorized();
            }

            $result = $analyzer->analyze($resource);

            return $this->formatAnalysisResult($result);
        } catch (\Throwable $e) {
            Log::error('AI Chat analyze_errors failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка анализа: {$e->getMessage()}");
        }
    }

    /**
     * Analyze all resources in team for errors.
     */
    private function analyzeAllResourcesErrors(ResourceErrorAnalyzer $analyzer): CommandResult
    {
        $resources = [];

        // Get applications
        $apps = Application::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->take(10)
            ->get();

        foreach ($apps as $app) {
            if ($this->authorize('view', $app)) {
                $resources[] = $app;
            }
        }

        // Get services
        $services = Service::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->take(5)
            ->get();

        foreach ($services as $service) {
            if ($this->authorize('view', $service)) {
                $resources[] = $service;
            }
        }

        if (empty($resources)) {
            return CommandResult::success('Нет ресурсов для анализа.');
        }

        $results = $analyzer->analyzeMultiple($resources);

        return $this->formatMultipleAnalysisResults($results);
    }

    /**
     * Analyze multiple named resources for errors.
     */
    private function analyzeMultipleResourcesErrors(ResourceErrorAnalyzer $analyzer, array $names): CommandResult
    {
        $resources = [];

        foreach ($names as $name) {
            $resource = $this->findAnyResourceByName($name, null, null);
            if ($resource && $this->authorize('view', $resource)) {
                $resources[] = $resource;
            }
        }

        if (empty($resources)) {
            return CommandResult::failed('Не найдено ресурсов с указанными именами.');
        }

        $results = $analyzer->analyzeMultiple($resources);

        return $this->formatMultipleAnalysisResults($results);
    }

    /**
     * Format single analysis result.
     */
    private function formatAnalysisResult(array $result): CommandResult
    {
        $output = "## 🔍 Анализ ошибок: **{$result['resource_name']}**\n\n";

        if ($result['errors_found'] === 0) {
            $output .= "✅ Критических ошибок не обнаружено.\n";
            if ($result['summary']) {
                $output .= "\n{$result['summary']}";
            }

            return CommandResult::success($output, $result);
        }

        $output .= "**Найдено проблем:** {$result['errors_found']}\n\n";

        if (! empty($result['issues'])) {
            $output .= "### Проблемы:\n\n";
            foreach ($result['issues'] as $i => $issue) {
                $severityEmoji = match ($issue['severity'] ?? 'medium') {
                    'critical' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪',
                };
                $output .= "{$severityEmoji} **{$issue['severity']}**: {$issue['message']}\n";
                if (! empty($issue['suggestion'])) {
                    $output .= "   _Рекомендация: {$issue['suggestion']}_\n";
                }
                $output .= "\n";
            }
        }

        if (! empty($result['solutions'])) {
            $output .= "### Рекомендуемые действия:\n\n";
            foreach ($result['solutions'] as $i => $solution) {
                $output .= ($i + 1).". {$solution}\n";
            }
        }

        if ($result['summary']) {
            $output .= "\n---\n**Резюме:** {$result['summary']}";
        }

        return CommandResult::success($output, $result);
    }

    /**
     * Format multiple analysis results.
     */
    private function formatMultipleAnalysisResults(array $results): CommandResult
    {
        $totalErrors = 0;
        $resourcesWithErrors = 0;

        foreach ($results as $result) {
            $totalErrors += $result['errors_found'] ?? 0;
            if (($result['errors_found'] ?? 0) > 0) {
                $resourcesWithErrors++;
            }
        }

        $output = '## 🔍 Анализ ошибок: '.count($results)." ресурсов\n\n";
        $output .= "**Всего ошибок:** {$totalErrors} в {$resourcesWithErrors} ресурсах\n\n";

        foreach ($results as $name => $result) {
            $status = ($result['errors_found'] ?? 0) > 0 ? '🔴' : '✅';
            $output .= "{$status} **{$name}**: {$result['errors_found']} ошибок\n";
        }

        return CommandResult::success($output, ['results' => $results, 'total_errors' => $totalErrors]);
    }

    /**
     * Analyze a failed deployment using DeploymentLogAnalyzer.
     */
    private function executeAnalyzeDeploymentCommand(ParsedCommand $command): CommandResult
    {
        try {
            $deployment = null;

            // Find deployment by UUID
            if ($command->deploymentUuid) {
                $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $command->deploymentUuid)
                    ->whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                    ->first();
            }

            // Find by resource if specified
            if (! $deployment && $command->resourceName) {
                $app = $this->findApplicationByName($command->resourceName, $command->projectName, $command->environmentName);
                if ($app) {
                    $deployment = ApplicationDeploymentQueue::where('application_id', $app->id)
                        ->where('status', 'failed')
                        ->orderByDesc('created_at')
                        ->first();
                }
            }

            // Find the last failed deployment in team
            if (! $deployment) {
                $deployment = ApplicationDeploymentQueue::whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                    ->where('status', 'failed')
                    ->orderByDesc('created_at')
                    ->first();
            }

            if (! $deployment) {
                return CommandResult::success('Не найдено неудачных деплоев для анализа.');
            }

            // Check authorization
            if (! $this->authorize('view', $deployment->application)) {
                return CommandResult::unauthorized();
            }

            // Check if analysis already exists
            $existingAnalysis = DeploymentLogAnalysis::where('deployment_id', $deployment->id)
                ->where('status', 'completed')
                ->first();

            if ($existingAnalysis) {
                return $this->formatDeploymentAnalysis($deployment, $existingAnalysis);
            }

            // Run new analysis
            $analyzer = app(DeploymentLogAnalyzer::class);

            if (! $analyzer->isEnabledAndAvailable()) {
                return CommandResult::failed('AI анализ недоступен. Проверьте настройки API ключей.');
            }

            $analysis = $analyzer->analyzeAndSave($deployment);

            if ($analysis->isFailed()) {
                return CommandResult::failed("Ошибка анализа: {$analysis->error_message}");
            }

            return $this->formatDeploymentAnalysis($deployment, $analysis);
        } catch (\Throwable $e) {
            Log::error('AI Chat analyze_deployment failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка анализа деплоя: {$e->getMessage()}");
        }
    }

    /**
     * Format deployment analysis result.
     */
    private function formatDeploymentAnalysis(ApplicationDeploymentQueue $deployment, DeploymentLogAnalysis $analysis): CommandResult
    {
        $appName = $deployment->application->name ?? 'Unknown';
        $severityEmoji = match ($analysis->severity) {
            'critical' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };

        $output = "## 🔍 Анализ деплоя: **{$appName}**\n\n";
        $output .= "**UUID:** `{$deployment->deployment_uuid}`\n";
        $output .= "**Статус:** {$deployment->status}\n";
        $output .= "**Серьёзность:** {$severityEmoji} {$analysis->severity}\n";
        $output .= "**Категория:** {$analysis->category_label}\n";
        $output .= '**Уверенность:** '.round($analysis->confidence * 100).'%';
        $output .= "\n\n";

        if ($analysis->root_cause) {
            $output .= "### 🎯 Причина ошибки\n\n{$analysis->root_cause}\n\n";
        }

        if ($analysis->root_cause_details) {
            $output .= "### 📋 Детали\n\n{$analysis->root_cause_details}\n\n";
        }

        if (! empty($analysis->solution)) {
            $output .= "### ✅ Решение\n\n";
            foreach ($analysis->solution as $i => $step) {
                $output .= ($i + 1).". {$step}\n";
            }
            $output .= "\n";
        }

        if (! empty($analysis->prevention)) {
            $output .= "### 🛡️ Предотвращение\n\n";
            foreach ($analysis->prevention as $tip) {
                $output .= "- {$tip}\n";
            }
        }

        return CommandResult::success($output, [
            'deployment_uuid' => $deployment->deployment_uuid,
            'analysis' => $analysis->toArray(),
        ]);
    }

    /**
     * Show code review results for an application or deployment.
     */
    private function executeCodeReviewCommand(ParsedCommand $command): CommandResult
    {
        try {
            $codeReview = null;

            // Find by deployment UUID
            if ($command->deploymentUuid) {
                $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $command->deploymentUuid)
                    ->whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                    ->first();

                if ($deployment) {
                    $codeReview = CodeReview::where('deployment_id', $deployment->id)->first();
                }
            }

            // Find by application name
            if (! $codeReview && $command->resourceName) {
                $app = $this->findApplicationByName($command->resourceName, $command->projectName, $command->environmentName);
                if ($app) {
                    $codeReview = CodeReview::where('application_id', $app->id)
                        ->orderByDesc('created_at')
                        ->first();
                }
            }

            // Find the latest code review in team
            if (! $codeReview) {
                $codeReview = CodeReview::whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                    ->orderByDesc('created_at')
                    ->first();
            }

            if (! $codeReview) {
                return CommandResult::success('Не найдено code review для отображения. Code review создаётся автоматически при деплое.');
            }

            // Check authorization
            if (! $this->authorize('view', $codeReview->application)) {
                return CommandResult::unauthorized();
            }

            return $this->formatCodeReview($codeReview);
        } catch (\Throwable $e) {
            Log::error('AI Chat code_review failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка получения code review: {$e->getMessage()}");
        }
    }

    /**
     * Format code review result.
     */
    private function formatCodeReview(CodeReview $review): CommandResult
    {
        $appName = $review->application->name ?? 'Unknown';
        $statusEmoji = match ($review->status) {
            'completed' => $review->hasCriticalViolations() ? '🔴' : ($review->hasViolations() ? '🟡' : '✅'),
            'analyzing' => '🔄',
            'failed' => '❌',
            default => '⏳',
        };

        $output = "## 📝 Code Review: **{$appName}**\n\n";
        $output .= "**Коммит:** `{$review->commit_sha}`\n";
        $output .= "**Статус:** {$statusEmoji} {$review->status_label}\n";
        $output .= "**Нарушений:** {$review->violations_count} (критических: {$review->critical_count})\n";

        if ($review->summary) {
            $output .= "\n### Резюме\n\n{$review->summary}\n";
        }

        // Load violations
        $violations = $review->violations()->orderBy('severity')->take(10)->get();

        if ($violations->isNotEmpty()) {
            $output .= "\n### Нарушения\n\n";

            foreach ($violations as $violation) {
                $violationSeverity = $violation->getAttribute('severity');
                $severityEmoji = match ($violationSeverity) {
                    'critical' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪',
                };
                $output .= "{$severityEmoji} **{$violationSeverity}** [{$violation->getAttribute('rule_id')}]\n";
                $output .= "   {$violation->getAttribute('message')}\n";
                $violationFilePath = $violation->getAttribute('file_path');
                if ($violationFilePath) {
                    $output .= "   📄 `{$violationFilePath}:{$violation->getAttribute('line_number')}`\n";
                }
                $output .= "\n";
            }
        }

        if ($review->files_analyzed) {
            $filesCount = count($review->files_analyzed);
            $output .= "\n_Проанализировано файлов: {$filesCount}_";
        }

        return CommandResult::success($output, [
            'code_review_id' => $review->id,
            'violations_count' => $review->violations_count,
            'critical_count' => $review->critical_count,
        ]);
    }

    /**
     * Check health of all resources in project/environment.
     */
    private function executeHealthCheckCommand(ParsedCommand $command): CommandResult
    {
        try {
            $resources = [];
            $statuses = [
                'healthy' => 0,
                'unhealthy' => 0,
                'degraded' => 0,
                'unknown' => 0,
            ];

            // Get applications
            $apps = Application::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->with(['environment.project'])
                ->take(20)
                ->get();

            foreach ($apps as $app) {
                if ($this->authorize('view', $app)) {
                    $status = $this->getResourceStatus($app);
                    $health = $this->determineHealthStatus($status['status'] ?? 'unknown');
                    $statuses[$health]++;
                    $resources[] = [
                        'name' => $app->name,
                        'type' => 'Application',
                        'status' => $status['status'] ?? 'unknown',
                        'health' => $health,
                        'project' => $app->environment?->project?->name,
                    ];
                }
            }

            // Get services
            $services = Service::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->with(['environment.project'])
                ->take(10)
                ->get();

            foreach ($services as $service) {
                if ($this->authorize('view', $service)) {
                    $status = $this->getResourceStatus($service);
                    $health = $this->determineHealthStatus($status['status'] ?? 'unknown');
                    $statuses[$health]++;
                    $resources[] = [
                        'name' => $service->name,
                        'type' => 'Service',
                        'status' => $status['status'] ?? 'unknown',
                        'health' => $health,
                        'project' => $service->environment?->project?->name,
                    ];
                }
            }

            // Get databases
            foreach (array_unique(self::DATABASE_MODELS) as $model) {
                $dbs = $model::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                    ->with(['environment.project'])
                    ->take(5)
                    ->get();

                foreach ($dbs as $db) {
                    if ($this->authorize('view', $db)) {
                        $status = $this->getResourceStatus($db);
                        $health = $this->determineHealthStatus($status['status'] ?? 'unknown');
                        $statuses[$health]++;
                        $resources[] = [
                            'name' => $db->name,
                            'type' => 'Database',
                            'status' => $status['status'] ?? 'unknown',
                            'health' => $health,
                            'project' => $db->environment?->project?->name,
                        ];
                    }
                }
            }

            return $this->formatHealthCheckResult($resources, $statuses);
        } catch (\Throwable $e) {
            Log::error('AI Chat health_check failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка проверки здоровья: {$e->getMessage()}");
        }
    }

    /**
     * Determine health status from resource status.
     *
     * Status format can be:
     * - Simple: "running", "stopped", "healthy"
     * - Compound: "running:healthy", "exited:unhealthy", "running:unknown"
     */
    private function determineHealthStatus(string $status): string
    {
        $status = strtolower(trim($status));

        // Parse compound format "state:health"
        if (str_contains($status, ':')) {
            [$state, $health] = explode(':', $status, 2);

            // Health part takes priority when explicitly set
            if ($health === 'healthy') {
                return 'healthy';
            }
            if ($health === 'unhealthy') {
                return 'unhealthy';
            }

            // For "unknown" health, determine by state
            return match ($state) {
                'running', 'started' => 'healthy',
                'stopped', 'exited', 'dead', 'removing' => 'unhealthy',
                'restarting', 'starting', 'stopping', 'paused', 'degraded' => 'degraded',
                default => 'unknown',
            };
        }

        // Simple status format
        return match ($status) {
            'running', 'healthy', 'started' => 'healthy',
            'stopped', 'exited', 'not_functional', 'dead' => 'unhealthy',
            'restarting', 'starting', 'stopping', 'degraded', 'paused' => 'degraded',
            default => 'unknown',
        };
    }

    /**
     * Format health check result.
     */
    private function formatHealthCheckResult(array $resources, array $statuses): CommandResult
    {
        $total = count($resources);
        $healthyPercent = $total > 0 ? round(($statuses['healthy'] / $total) * 100) : 0;

        $overallStatus = match (true) {
            $statuses['unhealthy'] > 0 => '🔴 Проблемы обнаружены',
            $statuses['degraded'] > 0 => '🟡 Есть предупреждения',
            $statuses['unknown'] > $statuses['healthy'] => '⚪ Статус неизвестен',
            default => '✅ Всё в порядке',
        };

        $output = "## 🏥 Health Check\n\n";
        $output .= "**Общий статус:** {$overallStatus}\n";
        $output .= "**Здоровых ресурсов:** {$healthyPercent}% ({$statuses['healthy']}/{$total})\n\n";

        $output .= "| Статус | Количество |\n";
        $output .= "|--------|------------|\n";
        $output .= "| ✅ Healthy | {$statuses['healthy']} |\n";
        $output .= "| 🟡 Degraded | {$statuses['degraded']} |\n";
        $output .= "| 🔴 Unhealthy | {$statuses['unhealthy']} |\n";
        $output .= "| ⚪ Unknown | {$statuses['unknown']} |\n\n";

        // List unhealthy resources
        $unhealthy = array_filter($resources, fn ($r) => $r['health'] === 'unhealthy');
        if (! empty($unhealthy)) {
            $output .= "### 🔴 Проблемные ресурсы\n\n";
            foreach ($unhealthy as $r) {
                $output .= "- **{$r['name']}** ({$r['type']}) - {$r['status']}\n";
            }
            $output .= "\n";
        }

        // List degraded resources
        $degraded = array_filter($resources, fn ($r) => $r['health'] === 'degraded');
        if (! empty($degraded)) {
            $output .= "### 🟡 Ресурсы с предупреждениями\n\n";
            foreach ($degraded as $r) {
                $output .= "- **{$r['name']}** ({$r['type']}) - {$r['status']}\n";
            }
        }

        return CommandResult::success($output, [
            'total' => $total,
            'statuses' => $statuses,
            'healthy_percent' => $healthyPercent,
            'resources' => $resources,
        ]);
    }

    /**
     * Show deployment metrics and statistics.
     */
    private function executeMetricsCommand(ParsedCommand $command): CommandResult
    {
        try {
            $period = $command->timePeriod ?? '7d';
            $days = $this->parsePeriodToDays($period);

            $startDate = now()->subDays($days);

            // Get deployment statistics
            $totalDeployments = ApplicationDeploymentQueue::whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->where('created_at', '>=', $startDate)
                ->count();

            $successfulDeployments = ApplicationDeploymentQueue::whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->where('created_at', '>=', $startDate)
                ->where('status', 'finished')
                ->count();

            $failedDeployments = ApplicationDeploymentQueue::whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->where('created_at', '>=', $startDate)
                ->where('status', 'failed')
                ->count();

            $successRate = $totalDeployments > 0 ? round(($successfulDeployments / $totalDeployments) * 100, 1) : 0;

            // Get deployments by application
            $byApp = ApplicationDeploymentQueue::whereHas('application.environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->where('created_at', '>=', $startDate)
                ->selectRaw('application_id, count(*) as total, sum(case when status = \'finished\' then 1 else 0 end) as success')
                ->groupBy('application_id')
                ->with('application:id,name')
                ->orderByDesc('total')
                ->take(5)
                ->get();

            // Resource counts
            $appCount = Application::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))->count();
            $serviceCount = Service::whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))->count();
            $serverCount = Server::where('team_id', $this->teamId)->count();

            $output = "## 📊 Метрики за {$days} дней\n\n";

            $output .= "### Деплои\n\n";
            $output .= "| Метрика | Значение |\n";
            $output .= "|---------|----------|\n";
            $output .= "| Всего деплоев | {$totalDeployments} |\n";
            $output .= "| Успешных | {$successfulDeployments} |\n";
            $output .= "| Неудачных | {$failedDeployments} |\n";
            $output .= "| Success Rate | {$successRate}% |\n\n";

            if ($byApp->isNotEmpty()) {
                $output .= "### Топ приложений по деплоям\n\n";
                foreach ($byApp as $stat) {
                    $appName = $stat->application->name ?? 'Unknown';
                    $total = (int) $stat->getAttribute('total');
                    $success = (int) $stat->getAttribute('success');
                    $appSuccess = $total > 0 ? round(($success / $total) * 100) : 0;
                    $output .= "- **{$appName}**: {$total} деплоев ({$appSuccess}% успешных)\n";
                }
                $output .= "\n";
            }

            $output .= "### Ресурсы\n\n";
            $output .= "- Приложений: {$appCount}\n";
            $output .= "- Сервисов: {$serviceCount}\n";
            $output .= "- Серверов: {$serverCount}\n";

            return CommandResult::success($output, [
                'period_days' => $days,
                'total_deployments' => $totalDeployments,
                'successful_deployments' => $successfulDeployments,
                'failed_deployments' => $failedDeployments,
                'success_rate' => $successRate,
                'app_count' => $appCount,
                'service_count' => $serviceCount,
                'server_count' => $serverCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Chat metrics failed', ['error' => $e->getMessage()]);

            return CommandResult::failed("Ошибка получения метрик: {$e->getMessage()}");
        }
    }

    /**
     * Parse time period string to days.
     */
    private function parsePeriodToDays(string $period): int
    {
        if (preg_match('/^(\d+)([hdwm])$/i', $period, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);

            return match ($unit) {
                'h' => max(1, (int) ceil($value / 24)),
                'd' => $value,
                'w' => $value * 7,
                'm' => $value * 30,
                default => 7,
            };
        }

        return 7; // Default to 7 days
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
     * Build a not-found result with similar resource suggestions.
     * Instead of a generic "not found", shows the user what was found and asks to clarify.
     */
    private function notFoundWithSuggestions(ParsedCommand $command): CommandResult
    {
        $resourceName = $command->resourceName;
        $resourceType = $command->resourceType ?? 'resource';

        if (! $resourceName) {
            return CommandResult::notFound($resourceType);
        }

        $similar = $this->findSimilarResources($resourceName);

        return CommandResult::notFound($resourceType, $similar);
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

                return CommandResult::success("Restarting database **{$resource->getAttribute('name')}**.");
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

                return CommandResult::success("Stopping database **{$resource->getAttribute('name')}**.");
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

                return CommandResult::success("Starting database **{$resource->getAttribute('name')}**.");
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
                "Logs for **{$resource->getAttribute('name')}**:\n```\n{$logs}\n```",
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
                "Status of **{$resource->getAttribute('name')}**: {$status['status']}",
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
            $resourceName = $resource->getAttribute('name') ?? 'Unknown';
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

        // Support both old (exclude_names) and new (resource_names with target_scope) formats
        $excludeNames = $intent->params['exclude_names'] ?? $intent->params['resource_names'] ?? [];

        // If user wants to delete all projects except some
        if ($this->isDeleteAllExceptRequest($intent)) {
            return $this->executeDeleteAllProjectsExcept($excludeNames);
        }

        if (! $projectName) {
            return $this->listDeletableProjects('Which project do you want to delete?');
        }

        $project = Project::where('team_id', $this->teamId)
            ->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%')
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
                $query->where('name', 'NOT ILIKE', '%'.$this->escapeIlike($name).'%');
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
        // Support both old (exclude_names, delete_all_except) and new (target_scope, resource_names) formats
        $targetScope = $intent->params['target_scope'] ?? null;

        return ! empty($intent->params['exclude_names']) ||
               ! empty($intent->params['delete_all_except']) ||
               $targetScope === 'all' ||
               $targetScope === 'all_except';
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
            ->where('name', 'ILIKE', '%'.$this->escapeIlike($name).'%')
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
**Доступные команды:**

### Управление ресурсами
- **deploy** - Задеплоить приложение
- **rollback** - Откатить приложение до последнего успешного деплоя
- **restart** - Перезапустить приложение, сервис или БД
- **stop** - Остановить ресурс
- **start** - Запустить остановленный ресурс
- **logs** - Показать логи
- **status** - Статус ресурсов
- **delete** - Удалить ресурс

### AI Анализ
- **analyze_errors** - AI анализ ошибок в логах
- **analyze_deployment** - Анализ неудачного деплоя
- **code_review** - Показать code review
- **health_check** - Проверка здоровья ресурсов
- **metrics** - Статистика деплоев

**Примеры:**
- "Задеплой my-app"
- "Откати my-app"
- "Проанализируй ошибки api-service"
- "Почему упал последний деплой?"
- "Покажи code review"
- "Проверь здоровье всех сервисов"
- "Метрики за неделю"
- "Удали проект test-project"

Можете также задавать вопросы о ваших ресурсах!
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
            'server' => $this->findServerByName($name),
            default => $this->findAnyResourceByName($name, $projectName, $envName),
        };
    }

    /**
     * Find server by name with disambiguation.
     */
    private function findServerByName(string $name): ?Server
    {
        $cleanName = $this->cleanResourceName($name);

        $matches = Server::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->where('team_id', $this->teamId)
            ->get();

        return $this->resolveUniqueMatch($matches, $cleanName);
    }

    /**
     * Find application by name with project/environment filter.
     */
    private function findApplicationByName(string $name, ?string $projectName = null, ?string $envName = null): ?Application
    {
        $cleanName = $this->cleanResourceName($name);

        $query = Application::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));

        if ($projectName) {
            $query->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%'));
        }
        if ($envName) {
            $query->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($envName).'%'));
        }

        return $this->resolveUniqueMatch($query->get(), $cleanName);
    }

    /**
     * Find service by name with project/environment filter.
     */
    private function findServiceByName(string $name, ?string $projectName = null, ?string $envName = null): ?Service
    {
        $cleanName = $this->cleanResourceName($name);

        $query = Service::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));

        if ($projectName) {
            $query->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%'));
        }
        if ($envName) {
            $query->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($envName).'%'));
        }

        return $this->resolveUniqueMatch($query->get(), $cleanName);
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
        $cleanName = $this->cleanResourceName($name);
        $allMatches = collect();

        // Collect applications
        $appQuery = Application::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));
        if ($projectName) {
            $appQuery->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%'));
        }
        if ($envName) {
            $appQuery->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($envName).'%'));
        }
        $allMatches = $allMatches->merge($appQuery->get());

        // Collect services
        $serviceQuery = Service::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));
        if ($projectName) {
            $serviceQuery->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%'));
        }
        if ($envName) {
            $serviceQuery->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($envName).'%'));
        }
        $allMatches = $allMatches->merge($serviceQuery->get());

        // Collect servers (no project/environment filter)
        $allMatches = $allMatches->merge(
            Server::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
                ->where('team_id', $this->teamId)
                ->get()
        );

        // Collect databases
        foreach (array_unique(self::DATABASE_MODELS) as $model) {
            $dbQuery = $model::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId));
            if ($projectName) {
                $dbQuery->whereHas('environment.project', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($projectName).'%'));
            }
            if ($envName) {
                $dbQuery->whereHas('environment', fn ($q) => $q->where('name', 'ILIKE', '%'.$this->escapeIlike($envName).'%'));
            }
            $allMatches = $allMatches->merge($dbQuery->get());
        }

        return $this->resolveUniqueMatch($allMatches, $cleanName);
    }

    /**
     * Resolve a unique match from a collection of candidates.
     *
     * Returns the single match if unambiguous, or null if zero/multiple matches.
     * When multiple matches exist, tries exact name match to disambiguate
     * (e.g., "frontend" wins over "frontend (Clone)").
     *
     * @template T of Model
     *
     * @param  Collection<int, T>  $matches
     * @return T|null
     */
    private function resolveUniqueMatch(Collection $matches, string $cleanName): ?Model
    {
        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // Multiple matches — try exact name match to disambiguate
        $exactMatches = $matches->filter(
            fn (Model $m) => mb_strtolower($m->getAttribute('name') ?? '') === mb_strtolower($cleanName)
        );

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        // Still ambiguous
        return null;
    }

    /**
     * Strip environment info and noise from resource name.
     * "PixelAPI (development)" -> "PixelAPI"
     * "PixelAPI development"  -> "PixelAPI"
     * "development PixelAPI"  -> "PixelAPI"
     */
    private function cleanResourceName(string $name): string
    {
        $name = trim($name);

        // Remove parenthesized suffixes: "PixelAPI (development)" -> "PixelAPI"
        $name = preg_replace('/\s*\([^)]+\)\s*$/', '', $name);

        // Remove known environment words from start/end
        $envWords = ['dev', 'development', 'staging', 'stage', 'uat', 'prod', 'production', 'test', 'testing'];
        $pattern = '/^(?:'.implode('|', $envWords).')\s+|\s+(?:'.implode('|', $envWords).')$/i';
        $name = preg_replace($pattern, '', $name);

        return trim($name);
    }

    /**
     * Find similar resources by name across all types.
     * Returns list for "did you mean?" suggestions.
     *
     * @return array<array{name: string, type: string, environment: string, project: string}>
     */
    private function findSimilarResources(string $name): array
    {
        $cleanName = $this->cleanResourceName($name);
        $results = [];

        // Search applications
        $apps = Application::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->with('environment.project')
            ->limit(10)
            ->get();

        foreach ($apps as $app) {
            $results[] = [
                'name' => $app->name,
                'type' => 'application',
                'environment' => $app->environment->name ?? '?',
                'project' => $app->environment?->project->name ?? '?',
                'status' => $app->status ?? 'unknown',
            ];
        }

        // Search services
        $services = Service::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
            ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
            ->with('environment.project')
            ->limit(10)
            ->get();

        foreach ($services as $service) {
            $results[] = [
                'name' => $service->name,
                'type' => 'service',
                'environment' => $service->environment->name ?? '?',
                'project' => $service->environment?->project->name ?? '?',
                'status' => $service->status ?? 'unknown',
            ];
        }

        // Search databases
        foreach (array_unique(self::DATABASE_MODELS) as $model) {
            $dbs = $model::where('name', 'ILIKE', '%'.$this->escapeIlike($cleanName).'%')
                ->whereHas('environment.project.team', fn ($q) => $q->where('id', $this->teamId))
                ->with('environment.project')
                ->limit(5)
                ->get();

            foreach ($dbs as $db) {
                $results[] = [
                    'name' => $db->name,
                    'type' => 'database',
                    'environment' => $db->environment->name ?? '?',
                    'project' => $db->environment?->project->name ?? '?',
                    'status' => $db->status ?? 'unknown',
                ];
            }
        }

        return $results;
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
                $server = $resource->getAttribute('destination')->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                $containerName = $resource->getAttribute('uuid');
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
            'name' => $resource->getAttribute('name') ?? 'Unknown',
            'type' => class_basename($resource),
            'status' => 'unknown',
        ];

        if ($resource instanceof Application) {
            $status['status'] = $resource->status ?? 'unknown';
            $status['uuid'] = $resource->uuid;
            $status['fqdn'] = $resource->fqdn;
        } elseif ($resource instanceof Service) {
            $status['status'] = $resource->status ?? 'unknown';
            $status['uuid'] = $resource->uuid;
        } elseif ($resource instanceof Server) {
            $status['status'] = $resource->isFunctional() ? 'running' : 'not_functional';
            $status['uuid'] = $resource->uuid;
            $status['ip'] = $resource->ip;
        } elseif ($this->isDatabase($resource)) {
            $status['status'] = $resource->getAttribute('status') ?? 'unknown';
            $status['uuid'] = $resource->getAttribute('uuid');
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
                    'project' => $app->environment?->project->name ?? 'Unknown',
                    'environment' => $app->environment->name ?? 'default',
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
                    'project' => $service->environment?->project->name ?? 'Unknown',
                    'environment' => $service->environment->name ?? 'default',
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
                        'project' => $db->environment?->project->name ?? 'Unknown',
                        'environment' => $db->environment->name ?? 'default',
                        'uuid' => $db->uuid,
                    ];
                }
            }
        }

        $displayType = $resourceType ?? 'resource';

        return CommandResult::needsResource($action, $displayType, $resources);
    }
}
