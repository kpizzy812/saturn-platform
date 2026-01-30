<?php

namespace App\Policies;

use App\Models\SharedEnvironmentVariable;
use App\Models\User;

class SharedEnvironmentVariablePolicy
{
    /**
     * Check if user belongs to the team that owns the shared variable.
     */
    private function belongsToTeam(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        return $user->teams->contains('id', $sharedEnvironmentVariable->team_id);
    }

    /**
     * Check if user is admin/owner in the team.
     */
    private function isAdminInTeam(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        $membership = $user->teams->find($sharedEnvironmentVariable->team_id);

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
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        return $this->belongsToTeam($user, $sharedEnvironmentVariable);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Creation is controlled at team level
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        // Only team members can update
        return $this->belongsToTeam($user, $sharedEnvironmentVariable);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        // Only team members can delete
        return $this->belongsToTeam($user, $sharedEnvironmentVariable);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        // Only admins can restore
        return $this->isAdminInTeam($user, $sharedEnvironmentVariable);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        // Only admins can permanently delete
        return $this->isAdminInTeam($user, $sharedEnvironmentVariable);
    }

    /**
     * Determine whether the user can manage environment variables.
     */
    public function manageEnvironment(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
    {
        return $this->belongsToTeam($user, $sharedEnvironmentVariable);
    }
}
