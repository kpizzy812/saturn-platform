<?php

namespace App\Services;

use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\Server;

/**
 * Smart server selection for application deployment.
 *
 * Selects the optimal server based on project affinity and resource scores.
 */
class ServerSelectionService
{
    // Scoring weights
    private const WEIGHT_CPU = 0.30;

    private const WEIGHT_MEMORY = 0.30;

    private const WEIGHT_DISK = 0.20;

    private const WEIGHT_CONTAINERS = 0.10;

    private const WEIGHT_QUEUED = 0.10;

    /**
     * Select the optimal server for deployment.
     *
     * Priority:
     * 1. Environment default server (if set & functional)
     * 2. Project default server (if set & functional)
     * 3. Localhost (platform master, id=0) — always preferred while it has resources
     * 4. Score-based selection from all usable servers (when localhost is overloaded)
     */
    public function selectOptimalServer(Environment $env): ?Server
    {
        // 1. Check environment-level server affinity
        if ($env->default_server_id) {
            $envServer = Server::find($env->default_server_id);
            if ($envServer && $envServer->isFunctional()) {
                return $envServer;
            }
        }

        // 2. Check project-level server affinity
        $project = $env->project;
        if ($project?->settings?->default_server_id) {
            $affinityServer = Server::find($project->settings->default_server_id);
            if ($affinityServer && $affinityServer->isFunctional()) {
                return $affinityServer;
            }
        }

        // 3. Localhost (platform master) is the default — use while it has resources
        $localhost = Server::with(['settings', 'latestHealthCheck'])->find(0);
        if ($localhost && $localhost->isFunctional() && ! $this->isCriticallyOverloaded($localhost)) {
            return $localhost;
        }

        // 4. Score remaining servers when localhost is overloaded or unavailable
        $servers = $this->getUsableServers($env)->filter(fn (Server $s) => $s->id !== 0);
        if ($servers->isEmpty()) {
            // Even overloaded localhost is better than nothing
            return ($localhost && $localhost->isFunctional()) ? $localhost : null;
        }

        return $servers->count() === 1 ? $servers->first() : $this->selectByScore($servers);
    }

    /**
     * Get all usable servers for the team.
     */
    private function getUsableServers(Environment $env): \Illuminate\Support\Collection
    {
        $teamId = $env->project?->team_id;
        if (! $teamId) {
            return collect();
        }

        // Get team servers + platform server (id=0)
        return Server::where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId)->orWhere('id', 0);
        })
            ->whereRelation('settings', 'is_reachable', true)
            ->whereRelation('settings', 'is_usable', true)
            ->whereRelation('settings', 'is_build_server', false)
            ->whereRelation('settings', 'force_disabled', false)
            ->with('settings', 'latestHealthCheck')
            ->get()
            ->filter(fn (Server $s) => $s->isFunctional());
    }

    /**
     * Select server with the best (highest) score.
     */
    private function selectByScore(\Illuminate\Support\Collection $servers): Server
    {
        $scored = $servers->map(function (Server $server) {
            return [
                'server' => $server,
                'score' => $this->calculateScore($server),
            ];
        });

        // Higher score = better
        return $scored->sortByDesc('score')->first()['server'];
    }

    /**
     * Calculate a server's availability score (0-100).
     *
     * Higher = more available resources = better target.
     */
    public function calculateScore(Server $server): float
    {
        $health = $server->latestHealthCheck;

        // Default values when no health data is available
        $cpuUsage = $health?->cpu_usage_percent ?? 0;
        $memoryUsage = $health?->memory_usage_percent ?? 0;
        $diskUsage = $health?->disk_usage_percent ?? 0;
        $containerCounts = $health?->container_counts ?? [];
        $runningContainers = $containerCounts['running'] ?? 0;
        $queuedDeployments = $this->getQueuedDeployments($server);

        // Invert: lower usage = higher score
        $cpuScore = max(0, 100 - $cpuUsage);
        $memoryScore = max(0, 100 - $memoryUsage);
        $diskScore = max(0, 100 - $diskUsage);

        // Normalize containers (fewer = better, cap at 50)
        $containerScore = max(0, 100 - ($runningContainers * 2));

        // Normalize queued deployments (fewer = better, cap at 10)
        $queueScore = max(0, 100 - ($queuedDeployments * 10));

        return ($cpuScore * self::WEIGHT_CPU)
            + ($memoryScore * self::WEIGHT_MEMORY)
            + ($diskScore * self::WEIGHT_DISK)
            + ($containerScore * self::WEIGHT_CONTAINERS)
            + ($queueScore * self::WEIGHT_QUEUED);
    }

    /**
     * Check if a server is critically overloaded (any resource > 90%).
     */
    private function isCriticallyOverloaded(Server $server): bool
    {
        $health = $server->latestHealthCheck;
        if (! $health) {
            return false; // No health data = assume OK
        }

        return ($health->cpu_usage_percent ?? 0) > 90
            || ($health->memory_usage_percent ?? 0) > 90
            || ($health->disk_usage_percent ?? 0) > 90;
    }

    /**
     * Get number of queued deployments for a server.
     */
    protected function getQueuedDeployments(Server $server): int
    {
        return ApplicationDeploymentQueue::where('server_id', $server->id)
            ->whereIn('status', ['queued', 'in_progress'])
            ->count();
    }
}
