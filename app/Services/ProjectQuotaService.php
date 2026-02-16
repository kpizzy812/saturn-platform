<?php

namespace App\Services;

use App\Models\Project;

class ProjectQuotaService
{
    /**
     * Check if a new resource of the given type can be created in this project.
     */
    public function canCreate(Project $project, string $type): bool
    {
        $settings = $project->settings;
        if (! $settings) {
            return true;
        }

        $limit = match ($type) {
            'application' => $settings->max_applications,
            'service' => $settings->max_services,
            'database' => $settings->max_databases,
            'environment' => $settings->max_environments,
            default => null,
        };

        // null = unlimited
        if ($limit === null) {
            return true;
        }

        $current = $this->getCurrentCount($project, $type);

        return $current < $limit;
    }

    /**
     * Get usage breakdown: current counts vs limits.
     */
    public function getUsage(Project $project): array
    {
        $settings = $project->settings;

        $types = ['application', 'service', 'database', 'environment'];
        $usage = [];

        foreach ($types as $type) {
            $limit = match ($type) {
                'application' => $settings?->max_applications,
                'service' => $settings?->max_services,
                'database' => $settings?->max_databases,
                'environment' => $settings?->max_environments,
                default => null,
            };

            $usage[$type] = [
                'current' => $this->getCurrentCount($project, $type),
                'limit' => $limit,
            ];
        }

        return $usage;
    }

    protected function getCurrentCount(Project $project, string $type): int
    {
        return match ($type) {
            'application' => $project->applications()->count(),
            'service' => $project->services()->count(),
            'database' => $project->postgresqls()->count()
                + $project->mysqls()->count()
                + $project->mariadbs()->count()
                + $project->mongodbs()->count()
                + $project->redis()->count()
                + $project->keydbs()->count()
                + $project->dragonflies()->count()
                + $project->clickhouses()->count(),
            'environment' => $project->environments()->count(),
            default => 0,
        };
    }
}
