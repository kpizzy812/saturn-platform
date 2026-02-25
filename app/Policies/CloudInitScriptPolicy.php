<?php

namespace App\Policies;

use App\Models\CloudInitScript;
use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;

class CloudInitScriptPolicy
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = currentTeam();

        return $team ? $this->authService->canManageIntegrations($user, $team->id) : false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CloudInitScript $cloudInitScript): bool
    {
        return $user->teams->contains('id', $cloudInitScript->team_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = currentTeam();

        return $team ? $this->authService->canManageIntegrations($user, $team->id) : false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CloudInitScript $cloudInitScript): bool
    {
        if (! $user->teams->contains('id', $cloudInitScript->team_id)) {
            return false;
        }

        return $this->authService->canManageIntegrations($user, $cloudInitScript->team_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CloudInitScript $cloudInitScript): bool
    {
        if (! $user->teams->contains('id', $cloudInitScript->team_id)) {
            return false;
        }

        return $this->authService->canManageIntegrations($user, $cloudInitScript->team_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CloudInitScript $cloudInitScript): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CloudInitScript $cloudInitScript): bool
    {
        return false;
    }
}
