<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * GlobalSearch Livewire component for searching and resource creation.
 */
class GlobalSearch extends Component
{
    /**
     * Cache key prefix for team search caches.
     */
    private const CACHE_PREFIX = 'global-search-team-';

    public string $searchQuery = '';

    /**
     * Get the available resource types for quick creation.
     */
    public function getResourceTypes(): array
    {
        return [
            [
                'name' => 'Docker Image',
                'type' => 'docker-image',
                'quickcommand' => '(type: new image)',
                'icon' => 'docker',
            ],
            [
                'name' => 'Git Repository',
                'type' => 'git',
                'quickcommand' => '(type: new git)',
                'icon' => 'git',
            ],
            [
                'name' => 'Docker Compose',
                'type' => 'docker-compose',
                'quickcommand' => '(type: new compose)',
                'icon' => 'docker',
            ],
        ];
    }

    /**
     * Navigate to resource creation with selected type.
     */
    public function navigateToResourceCreation(string $type): void
    {
        $this->searchQuery = '';
    }

    /**
     * Complete resource creation and redirect.
     */
    public function completeResourceCreation(string $projectUuid, string $environmentName, string $type): void
    {
        $this->searchQuery = '';
        $this->redirect(route('project.resource.create', [
            'project_uuid' => $projectUuid,
            'environment_name' => $environmentName,
            'type' => $type,
        ]));
    }

    /**
     * Clear the global search cache for a specific team.
     *
     * @param  int|null  $teamId  The team ID to clear cache for
     */
    public static function clearTeamCache(?int $teamId = null): void
    {
        if ($teamId === null) {
            return;
        }

        try {
            Cache::forget(self::CACHE_PREFIX.$teamId);
        } catch (\Throwable $e) {
            // Silently fail - cache clearing should not break application flow
            Log::warning('GlobalSearch::clearTeamCache failed: '.$e->getMessage());
        }
    }

    /**
     * Get cached search results for a team.
     *
     * @param  int  $teamId  The team ID
     * @return array|null Cached results or null if not cached
     */
    public static function getTeamCache(int $teamId): ?array
    {
        try {
            return Cache::get(self::CACHE_PREFIX.$teamId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Set cached search results for a team.
     *
     * @param  int  $teamId  The team ID
     * @param  array  $results  Search results to cache
     * @param  int  $ttl  Cache TTL in seconds (default: 5 minutes)
     */
    public static function setTeamCache(int $teamId, array $results, int $ttl = 300): void
    {
        try {
            Cache::put(self::CACHE_PREFIX.$teamId, $results, $ttl);
        } catch (\Throwable $e) {
            // Silently fail
            Log::warning('GlobalSearch::setTeamCache failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.global-search');
    }
}
