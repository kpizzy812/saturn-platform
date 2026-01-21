<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;

/**
 * GlobalSearch stub class for cache management.
 *
 * This class provides cache clearing functionality for the global search feature.
 * The actual search UI component is not yet implemented - this stub exists to
 * support the ClearsGlobalSearchCache trait used by models.
 */
class GlobalSearch
{
    /**
     * Cache key prefix for team search caches.
     */
    private const CACHE_PREFIX = 'global-search-team-';

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
            ray('GlobalSearch::clearTeamCache failed: '.$e->getMessage());
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
            ray('GlobalSearch::setTeamCache failed: '.$e->getMessage());
        }
    }
}
