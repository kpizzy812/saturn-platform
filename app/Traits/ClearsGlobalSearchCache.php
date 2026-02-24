<?php

namespace App\Traits;

use App\Livewire\GlobalSearch;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

trait ClearsGlobalSearchCache
{
    protected static function bootClearsGlobalSearchCache(): void
    {
        static::saving(function ($model) {
            try {
                // Only clear cache if searchable fields are being changed
                if ($model->hasSearchableChanges()) {
                    $teamId = $model->getTeamIdForCache();
                    if (filled($teamId)) {
                        GlobalSearch::clearTeamCache($teamId);
                    }
                }
            } catch (\Throwable $e) {
                // Silently fail cache clearing - don't break the save operation
                Log::warning('Failed to clear global search cache on saving: '.$e->getMessage());
            }
        });

        static::created(function ($model) {
            try {
                // Always clear cache when model is created
                $teamId = $model->getTeamIdForCache();
                if (filled($teamId)) {
                    GlobalSearch::clearTeamCache($teamId);
                }
            } catch (\Throwable $e) {
                // Silently fail cache clearing - don't break the create operation
                Log::warning('Failed to clear global search cache on creation: '.$e->getMessage());
            }
        });

        static::deleted(function ($model) {
            try {
                // Always clear cache when model is deleted
                $teamId = $model->getTeamIdForCache();
                if (filled($teamId)) {
                    GlobalSearch::clearTeamCache($teamId);
                }
            } catch (\Throwable $e) {
                // Silently fail cache clearing - don't break the delete operation
                Log::warning('Failed to clear global search cache on deletion: '.$e->getMessage());
            }
        });
    }

    private function hasSearchableChanges(): bool
    {
        try {
            // Define searchable fields based on model type.
            // Use get_class() + match to avoid PHPStan type narrowing through
            // if/elseif chain which causes *NEVER* type errors.
            $searchableFields = match (get_class($this)) {
                Application::class => ['name', 'description', 'fqdn', 'docker_compose_domains'],
                Server::class => ['name', 'description', 'ip'],
                default => ['name', 'description'],
            };

            // Check if any searchable field is dirty
            foreach ($searchableFields as $field) {
                // Check if attribute exists before checking if dirty
                if (array_key_exists($field, $this->getAttributes()) && $this->isDirty($field)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            // If checking changes fails, assume changes exist to be safe
            Log::warning('Failed to check searchable changes: '.$e->getMessage());

            return true;
        }
    }

    private function getTeamIdForCache(): ?int
    {
        try {
            // Resolve team using get_class() + match to avoid PHPStan type
            // narrowing issues with if/elseif instanceof chains.
            $team = match (get_class($this)) {
                Project::class => $this->team,
                Environment::class => $this->project?->team,
                Server::class => $this->team,
                // Application, Service, and all Standalone* models have a
                // team() accessor returning Team|null via data_get().
                default => call_user_func([$this, 'team']),
            };

            if ($team instanceof Team) {
                return $team->id;
            }

            return null;
        } catch (\Throwable $e) {
            // If we can't determine team ID, return null
            Log::warning('Failed to get team ID for cache: '.$e->getMessage());

            return null;
        }
    }
}
