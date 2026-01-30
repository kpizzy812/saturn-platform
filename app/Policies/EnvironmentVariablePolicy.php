<?php

namespace App\Policies;

use App\Models\EnvironmentVariable;
use App\Models\User;

class EnvironmentVariablePolicy
{
    /**
     * Check if user belongs to the team that owns the environment variable's resource.
     */
    private function belongsToResourceTeam(User $user, EnvironmentVariable $environmentVariable): bool
    {
        // Load the resourceable (Application, Service, or Database)
        $resource = $environmentVariable->resourceable;

        if (! $resource) {
            return false;
        }

        // Get the team that owns the resource
        $team = method_exists($resource, 'team') ? $resource->team() : null;

        if (! $team) {
            return false;
        }

        // Check if user belongs to this team
        return $user->teams->contains('id', $team->id);
    }

    /**
     * Check if user has admin/owner role in the resource's team.
     */
    private function isAdminInResourceTeam(User $user, EnvironmentVariable $environmentVariable): bool
    {
        $resource = $environmentVariable->resourceable;

        if (! $resource) {
            return false;
        }

        $team = method_exists($resource, 'team') ? $resource->team() : null;

        if (! $team) {
            return false;
        }

        // Check if user is admin or owner in this team
        $membership = $user->teams->find($team->id);
        if (! $membership) {
            return false;
        }

        $role = $membership->pivot->role ?? 'member';

        return in_array($role, ['admin', 'owner']);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Anyone can list, filtering happens at query level
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->belongsToResourceTeam($user, $environmentVariable);
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
        return $this->belongsToResourceTeam($user, $environmentVariable);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->belongsToResourceTeam($user, $environmentVariable);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->isAdminInResourceTeam($user, $environmentVariable);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->isAdminInResourceTeam($user, $environmentVariable);
    }

    /**
     * Determine whether the user can manage environment variables.
     */
    public function manageEnvironment(User $user, EnvironmentVariable $environmentVariable): bool
    {
        return $this->belongsToResourceTeam($user, $environmentVariable);
    }
}
