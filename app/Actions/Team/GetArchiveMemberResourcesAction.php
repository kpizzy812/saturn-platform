<?php

namespace App\Actions\Team;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class GetArchiveMemberResourcesAction
{
    // Resource types that can be managed (move/delete)
    private const ALLOWED_RESOURCE_TYPES = [
        'App\Models\Application',
        'App\Models\Service',
        'App\Models\StandalonePostgresql',
        'App\Models\StandaloneMysql',
        'App\Models\StandaloneMariadb',
        'App\Models\StandaloneRedis',
        'App\Models\StandaloneKeydb',
        'App\Models\StandaloneDragonfly',
        'App\Models\StandaloneClickhouse',
        'App\Models\StandaloneMongodb',
    ];

    /**
     * Get all resources a member interacted with, enriched with live metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $teamId, int $userId): array
    {
        $rows = AuditLog::forTeam($teamId)
            ->byUser($userId)
            ->whereNotNull('resource_type')
            ->whereNotNull('resource_id')
            ->selectRaw('resource_type, resource_id, MAX(resource_name) as resource_name, COUNT(*) as action_count')
            ->groupBy('resource_type', 'resource_id')
            ->orderByDesc('action_count')
            ->limit(200)
            ->get();

        // Group rows by type for batch loading
        $rowsByType = [];
        foreach ($rows as $row) {
            $type = $row->resource_type;
            if (! in_array($type, self::ALLOWED_RESOURCE_TYPES, true) || ! class_exists($type)) {
                continue;
            }
            $rowsByType[$type][] = $row;
        }

        // Batch load models per type (fixes N+1)
        $modelsCache = [];
        foreach ($rowsByType as $type => $typeRows) {
            $ids = array_map(fn ($r) => $r->resource_id, $typeRows);
            $models = $type::with(['environment.project', 'destination.server'])
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');
            $modelsCache[$type] = $models;
        }

        // Batch load created actions (fixes N+1 for interaction check)
        $createdPairs = AuditLog::forTeam($teamId)
            ->byUser($userId)
            ->byAction('create')
            ->whereNotNull('resource_type')
            ->whereNotNull('resource_id')
            ->selectRaw('DISTINCT resource_type, resource_id')
            ->get()
            ->map(fn ($r) => $r->resource_type.':'.$r->resource_id)
            ->flip()
            ->all();

        $results = [];

        foreach ($rows as $row) {
            $type = $row->resource_type;
            $id = $row->resource_id;

            if (! isset($modelsCache[$type])) {
                continue;
            }

            $model = $modelsCache[$type][$id] ?? null;
            if (! $model) {
                continue;
            }

            // Verify resource belongs to this team
            $project = $model->environment?->project;
            if (! $project || $project->team_id !== $teamId) {
                continue;
            }

            $basename = class_basename($type);
            $serverName = $model->destination?->server?->name;

            $results[] = [
                'id' => $model->id,
                'uuid' => $model->uuid,
                'type' => $this->getTypeLabel($basename),
                'full_type' => $type,
                'name' => $model->name ?? $row->resource_name ?? 'Unknown',
                'status' => $model->status ?? null,
                'project_name' => $project->name ?? null,
                'project_id' => $project->id ?? null,
                'environment_name' => $model->environment->name ?? null,
                'environment_id' => $model->environment_id ?? null,
                'server_name' => $serverName,
                'action_count' => (int) data_get($row, 'action_count', 0),
                'url' => $this->getResourceUrl($basename, $model->uuid),
                'interaction' => isset($createdPairs[$type.':'.$id]) ? 'created' : 'modified',
            ];
        }

        return $results;
    }

    /**
     * Move resources to a target environment. Returns count of moved resources.
     *
     * @param  array<int, array{resource_type: string, resource_id: int}>  $resources
     */
    public function moveResources(int $teamId, array $resources, int $targetEnvironmentId): int
    {
        $targetEnv = \App\Models\Environment::ownedByCurrentTeam()->findOrFail($targetEnvironmentId);
        $moved = 0;

        foreach ($resources as $resource) {
            $model = $this->resolveAndVerify($teamId, $resource['resource_type'], $resource['resource_id']);
            if (! $model) {
                continue;
            }

            $model->update(['environment_id' => $targetEnv->id]);
            $moved++;
        }

        return $moved;
    }

    /**
     * Delete resources via DeleteResourceJob. Returns count of dispatched deletions.
     *
     * @param  array<int, array{resource_type: string, resource_id: int}>  $resources
     */
    public function deleteResources(int $teamId, array $resources): int
    {
        $deleted = 0;

        foreach ($resources as $resource) {
            $model = $this->resolveAndVerify($teamId, $resource['resource_type'], $resource['resource_id']);
            if (! $model) {
                continue;
            }

            /** @phpstan-ignore argument.type (resolveAndVerify guarantees type is from ALLOWED_RESOURCE_TYPES) */
            \App\Jobs\DeleteResourceJob::dispatch($model);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Resolve a resource model and verify it belongs to the team.
     */
    private function resolveAndVerify(int $teamId, string $type, int $id): ?Model
    {
        if (! in_array($type, self::ALLOWED_RESOURCE_TYPES, true) || ! class_exists($type)) {
            return null;
        }

        $model = $type::find($id);
        if (! $model) {
            return null;
        }

        $project = $model->environment?->project;
        if (! $project || $project->team_id !== $teamId) {
            return null;
        }

        return $model;
    }

    /**
     * Get a human-readable type label.
     */
    private function getTypeLabel(string $basename): string
    {
        return match ($basename) {
            'Application' => 'App',
            'Service' => 'Service',
            'StandalonePostgresql' => 'PostgreSQL',
            'StandaloneMysql' => 'MySQL',
            'StandaloneMariadb' => 'MariaDB',
            'StandaloneMongodb' => 'MongoDB',
            'StandaloneRedis' => 'Redis',
            'StandaloneKeydb' => 'KeyDB',
            'StandaloneDragonfly' => 'Dragonfly',
            'StandaloneClickhouse' => 'ClickHouse',
            default => $basename,
        };
    }

    /**
     * Get the frontend URL for a resource.
     */
    private function getResourceUrl(string $basename, string $uuid): string
    {
        return match (true) {
            $basename === 'Application' => "/applications/{$uuid}",
            $basename === 'Service' => "/services/{$uuid}",
            default => "/databases/{$uuid}",
        };
    }

    /**
     * Get the list of allowed resource types (for validation in routes).
     *
     * @return array<int, string>
     */
    public static function allowedResourceTypes(): array
    {
        return self::ALLOWED_RESOURCE_TYPES;
    }
}
