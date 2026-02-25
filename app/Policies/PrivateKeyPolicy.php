<?php

namespace App\Policies;

use App\Models\PrivateKey;
use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;

class PrivateKeyPolicy
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

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
    public function view(User $user, PrivateKey $privateKey): bool
    {
        if ($privateKey->team_id === null) { // @phpstan-ignore identical.alwaysFalse
            return false;
        }

        // System resource (team_id=0): Only root team admins/owners can access
        if ($privateKey->team_id === 0) {
            return $user->canAccessSystemResources();
        }

        return $user->teams->contains('id', $privateKey->team_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = currentTeam();
        if (! $team) {
            return false;
        }

        return $this->authService->hasPermission($user, 'servers.security', $team->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PrivateKey $privateKey): bool
    {
        if ($privateKey->team_id === null) { // @phpstan-ignore identical.alwaysFalse
            return false;
        }

        // System resource (team_id=0)
        if ($privateKey->team_id === 0) {
            return $user->canAccessSystemResources();
        }

        if (! $user->teams->contains('id', $privateKey->team_id)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'servers.security', $privateKey->team_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PrivateKey $privateKey): bool
    {
        if ($privateKey->team_id === null) { // @phpstan-ignore identical.alwaysFalse
            return false;
        }

        // System resource (team_id=0)
        if ($privateKey->team_id === 0) {
            return $user->canAccessSystemResources();
        }

        if (! $user->teams->contains('id', $privateKey->team_id)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'servers.security', $privateKey->team_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PrivateKey $privateKey): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PrivateKey $privateKey): bool
    {
        return false;
    }
}
