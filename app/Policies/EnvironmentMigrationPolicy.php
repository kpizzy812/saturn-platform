<?php

namespace App\Policies;

use App\Models\EnvironmentMigration;
use App\Models\User;
use App\Services\Authorization\MigrationAuthorizationService;

/**
 * Policy for environment migration authorization.
 */
class EnvironmentMigrationPolicy
{
    public function __construct(
        protected MigrationAuthorizationService $authService
    ) {}

    /**
     * Determine whether the user can view any migrations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the migration.
     */
    public function view(User $user, EnvironmentMigration $migration): bool
    {
        return $this->authService->canViewMigration($user, $migration);
    }

    /**
     * Determine whether the user can create migrations.
     */
    public function create(User $user): bool
    {
        // Anyone with at least developer role in any project can initiate migrations
        // Specific checks happen in the action based on the resource
        return true;
    }

    /**
     * Determine whether the user can approve the migration.
     */
    public function approve(User $user, EnvironmentMigration $migration): bool
    {
        if (! $migration->isAwaitingApproval()) {
            return false;
        }

        return $this->authService->canApproveMigration($user, $migration);
    }

    /**
     * Determine whether the user can reject the migration.
     */
    public function reject(User $user, EnvironmentMigration $migration): bool
    {
        if (! $migration->isAwaitingApproval()) {
            return false;
        }

        return $this->authService->canRejectMigration($user, $migration);
    }

    /**
     * Determine whether the user can cancel the migration.
     */
    public function cancel(User $user, EnvironmentMigration $migration): bool
    {
        return $this->authService->canCancelMigration($user, $migration);
    }

    /**
     * Determine whether the user can rollback the migration.
     */
    public function rollback(User $user, EnvironmentMigration $migration): bool
    {
        if (! $migration->canBeRolledBack()) {
            return false;
        }

        return $this->authService->canRollbackMigration($user, $migration);
    }

    /**
     * Determine whether the user can delete the migration.
     */
    public function delete(User $user, EnvironmentMigration $migration): bool
    {
        // Only completed, failed, rejected, or rolled back migrations can be deleted
        $deletableStatuses = [
            EnvironmentMigration::STATUS_COMPLETED,
            EnvironmentMigration::STATUS_FAILED,
            EnvironmentMigration::STATUS_REJECTED,
            EnvironmentMigration::STATUS_ROLLED_BACK,
        ];

        if (! in_array($migration->status, $deletableStatuses)) {
            return false;
        }

        // Only admin/owner can delete
        return $this->authService->canRollbackMigration($user, $migration);
    }
}
