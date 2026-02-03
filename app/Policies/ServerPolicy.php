<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;

class ServerPolicy
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
     * Requires: team membership
     */
    public function view(User $user, Server $server): bool
    {
        return $this->authService->canViewServer($user, $server);
    }

    /**
     * Determine whether the user can create models.
     * Requires: admin+ role in current team
     */
    public function create(User $user): bool
    {
        $team = currentTeam();
        if (! $team) {
            return false;
        }

        return $this->authService->canCreateServer($user, $team->id);
    }

    /**
     * Determine whether the user can update the model.
     * Requires: admin+ role in server's team
     */
    public function update(User $user, Server $server): bool
    {
        return $this->authService->canUpdateServer($user, $server);
    }

    /**
     * Determine whether the user can delete the model.
     * Requires: owner role in server's team (critical operation)
     */
    public function delete(User $user, Server $server): bool
    {
        return $this->authService->canDeleteServer($user, $server);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Server $server): bool
    {
        return $this->authService->canDeleteServer($user, $server);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Server $server): bool
    {
        return $this->authService->canDeleteServer($user, $server);
    }

    /**
     * Determine whether the user can manage proxy (start/stop/restart).
     * Requires: admin+ role
     */
    public function manageProxy(User $user, Server $server): bool
    {
        return $this->authService->canManageServerProxy($user, $server);
    }

    /**
     * Determine whether the user can manage sentinel (start/stop).
     * Requires: admin+ role
     */
    public function manageSentinel(User $user, Server $server): bool
    {
        return $this->authService->canManageServerSentinel($user, $server);
    }

    /**
     * Determine whether the user can manage CA certificates.
     * Requires: admin+ role
     */
    public function manageCaCertificate(User $user, Server $server): bool
    {
        return $this->authService->canManageServerCaCertificate($user, $server);
    }

    /**
     * Determine whether the user can view security views.
     * Requires: admin+ role (contains sensitive information)
     */
    public function viewSecurity(User $user, Server $server): bool
    {
        return $this->authService->canViewServerSecurity($user, $server);
    }
}
