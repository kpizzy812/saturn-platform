<?php

namespace App\Policies;

use App\Models\EnvironmentVariable;
use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;

class EnvironmentVariablePolicy
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

    /**
     * Resolve the permission key based on the resourceable type.
     */
    private function resolvePermissionKey(EnvironmentVariable $environmentVariable): ?string
    {
        $resource = $environmentVariable->resourceable;
        if (! $resource) {
            return null;
        }

        $type = $resource->type ?? get_class($resource);

        return match (true) {
            str_contains($type, 'application') || $resource instanceof \App\Models\Application => 'applications.env_vars',
            str_contains($type, 'service') || $resource instanceof \App\Models\Service => 'services.env_vars',
            default => 'databases.env_vars',
        };
    }

    /**
     * Get the team ID from the environment variable's resource.
     */
    private function getTeamId(EnvironmentVariable $environmentVariable): ?int
    {
        $resource = $environmentVariable->resourceable;
        if (! $resource) {
            return null;
        }

        $team = method_exists($resource, 'team') ? $resource->team() : null;
        if ($team instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            $team = $team->first();
        }

        return $team?->id;
    }

    /**
     * Check if user belongs to the team that owns the environment variable's resource.
     */
    private function belongsToResourceTeam(User $user, EnvironmentVariable $environmentVariable): bool
    {
        $teamId = $this->getTeamId($environmentVariable);
        if ($teamId === null) {
            return false;
        }

        return $user->teams->contains('id', $teamId);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EnvironmentVariable $environmentVariable): bool
    {
        if (! $this->belongsToResourceTeam($user, $environmentVariable)) {
            return false;
        }

        $permissionKey = $this->resolvePermissionKey($environmentVariable);
        if (! $permissionKey) {
            return false;
        }

        $teamId = $this->getTeamId($environmentVariable);

        return $this->authService->hasPermission($user, $permissionKey, $teamId);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Creation is controlled at resource level (Application, Service, etc.)
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EnvironmentVariable $environmentVariable): bool
    {
        if (! $this->belongsToResourceTeam($user, $environmentVariable)) {
            return false;
        }

        $permissionKey = $this->resolvePermissionKey($environmentVariable);
        if (! $permissionKey) {
            return false;
        }

        $teamId = $this->getTeamId($environmentVariable);

        return $this->authService->hasPermission($user, $permissionKey, $teamId);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnvironmentVariable $environmentVariable): bool
    {
        if (! $this->belongsToResourceTeam($user, $environmentVariable)) {
            return false;
        }

        $permissionKey = $this->resolvePermissionKey($environmentVariable);
        if (! $permissionKey) {
            return false;
        }

        $teamId = $this->getTeamId($environmentVariable);

        return $this->authService->hasPermission($user, $permissionKey, $teamId);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->update($user, $environmentVariable);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->delete($user, $environmentVariable);
    }

    /**
     * Determine whether the user can manage environment variables.
     */
    public function manageEnvironment(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->update($user, $environmentVariable);
    }
}
