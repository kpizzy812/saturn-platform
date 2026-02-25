<?php

namespace App\Services;

use App\Models\Team;

class TeamQuotaService
{
    /**
     * Get resource usage and limits for a team.
     *
     * @return array{servers: array, applications: array, databases: array, projects: array}
     */
    public function getUsage(Team $team): array
    {
        return [
            'servers' => [
                'current' => $team->servers()->count(),
                'limit' => $team->max_servers,
            ],
            'applications' => [
                'current' => $this->countApplications($team),
                'limit' => $team->max_applications,
            ],
            'databases' => [
                'current' => $this->countDatabases($team),
                'limit' => $team->max_databases,
            ],
            'projects' => [
                'current' => $team->projects()->count(),
                'limit' => $team->max_projects,
            ],
        ];
    }

    /**
     * Check if a team is within a specific quota.
     */
    public function checkQuota(Team $team, string $type): bool
    {
        $usage = $this->getUsage($team);

        if (! isset($usage[$type])) {
            return true;
        }

        $limit = $usage[$type]['limit'];

        // null = unlimited
        if ($limit === null) {
            return true;
        }

        return $usage[$type]['current'] < $limit;
    }

    private function countApplications(Team $team): int
    {
        return \App\Models\Application::whereHas('environment.project', function ($q) use ($team) {
            $q->where('team_id', $team->id);
        })->count();
    }

    private function countDatabases(Team $team): int
    {
        $count = 0;
        $dbModels = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        foreach ($dbModels as $model) {
            $count += $model::whereHas('environment.project', function ($q) use ($team) {
                $q->where('team_id', $team->id);
            })->count();
        }

        return $count;
    }
}
