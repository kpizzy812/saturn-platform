<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;
use Illuminate\Auth\Access\Response;

class DatabasePolicy
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
    public function view(User $user, $database): bool
    {
        return $this->authService->canViewDatabase($user, $database);
    }

    /**
     * Determine whether the user can view database credentials.
     * Requires: admin+ role (sensitive data)
     */
    public function viewCredentials(User $user, $database): bool
    {
        return $this->authService->canViewDatabaseCredentials($user, $database);
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

        return $this->authService->canCreateDatabase($user, $team->id);
    }

    /**
     * Determine whether the user can update the model.
     * Requires: admin+ role
     */
    public function update(User $user, $database): Response|bool
    {
        if ($this->authService->canUpdateDatabase($user, $database)) {
            return Response::allow();
        }

        return Response::deny('You need at least admin or owner role to update this database.');
    }

    /**
     * Determine whether the user can delete the model.
     * Requires: owner role (critical operation - data loss)
     */
    public function delete(User $user, $database): bool
    {
        return $this->authService->canDeleteDatabase($user, $database);
    }

    /**
     * Determine whether the user can restore the model.
     * Requires: owner role
     */
    public function restore(User $user, $database): bool
    {
        return $this->authService->canDeleteDatabase($user, $database);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Requires: owner role
     */
    public function forceDelete(User $user, $database): bool
    {
        return $this->authService->canDeleteDatabase($user, $database);
    }

    /**
     * Determine whether the user can start/stop the database.
     * Requires: admin+ role
     */
    public function manage(User $user, $database): bool
    {
        return $this->authService->canManageDatabase($user, $database);
    }

    /**
     * Determine whether the user can manage database backups.
     * Requires: admin+ role
     */
    public function manageBackups(User $user, $database): bool
    {
        return $this->authService->canManageDatabaseBackups($user, $database);
    }

    /**
     * Determine whether the user can manage environment variables.
     * Requires: admin+ role
     */
    public function manageEnvironment(User $user, $database): bool
    {
        return $this->authService->canManageDatabaseEnvironment($user, $database);
    }
}
