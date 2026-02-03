<?php

namespace App\Services\Authorization;

use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for handling environment migration authorization.
 * Centralizes authorization logic for resource migrations between environments.
 *
 * Migration rules:
 * - Only allowed chain: dev -> uat -> prod (no skipping)
 * - Developer: can migrate dev -> uat directly
 * - Developer: needs approval for uat -> prod
 * - Admin/Owner: can migrate without approval
 */
class MigrationAuthorizationService
{
    /**
     * Environment type chain order for migrations.
     * Lower index = earlier in chain.
     */
    private const ENVIRONMENT_CHAIN = [
        'development' => 0,
        'uat' => 1,
        'production' => 2,
    ];

    /**
     * Role hierarchy levels.
     */
    private const ROLE_HIERARCHY = [
        'viewer' => 1,
        'member' => 2,
        'developer' => 3,
        'admin' => 4,
        'owner' => 5,
    ];

    private ?ProjectAuthorizationService $projectAuthService = null;

    /**
     * Get the ProjectAuthorizationService instance (lazy loaded).
     */
    private function getProjectAuthService(): ProjectAuthorizationService
    {
        if ($this->projectAuthService === null) {
            $this->projectAuthService = app(ProjectAuthorizationService::class);
        }

        return $this->projectAuthService;
    }

    /**
     * Check if user can initiate a migration from source to target environment.
     *
     * @param  Model  $resource  The resource to migrate (Application, Service, Database)
     */
    public function canInitiateMigration(User $user, Model $resource, Environment $sourceEnv, Environment $targetEnv): bool
    {
        // Platform admins can do anything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // User must have access to the project
        $project = $sourceEnv->project;
        if (! $this->getProjectAuthService()->canViewProject($user, $project)) {
            return false;
        }

        // User must have at least developer role
        if (! $this->hasMinimumRole($user, $project, 'developer')) {
            return false;
        }

        // Validate the migration chain
        if (! $this->isValidMigrationChain($sourceEnv, $targetEnv)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a migration requires approval.
     */
    public function requiresApproval(User $user, Environment $sourceEnv, Environment $targetEnv): bool
    {
        // Platform admins never need approval
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return false;
        }

        $project = $sourceEnv->project;

        // Admin and Owner don't need approval
        if ($this->hasMinimumRole($user, $project, 'admin')) {
            return false;
        }

        // Migrations TO production require approval for non-admin/owner
        if ($targetEnv->isProduction()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can approve a migration.
     */
    public function canApproveMigration(User $user, EnvironmentMigration $migration): bool
    {
        // Platform admins can approve anything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $project = $migration->sourceEnvironment->project;

        // Only admin and owner can approve migrations
        return $this->hasMinimumRole($user, $project, 'admin');
    }

    /**
     * Check if user can reject a migration.
     */
    public function canRejectMigration(User $user, EnvironmentMigration $migration): bool
    {
        // Same rules as approval
        return $this->canApproveMigration($user, $migration);
    }

    /**
     * Check if user can rollback a migration.
     */
    public function canRollbackMigration(User $user, EnvironmentMigration $migration): bool
    {
        // Platform admins can rollback anything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $project = $migration->sourceEnvironment->project;

        // Only admin and owner can rollback
        return $this->hasMinimumRole($user, $project, 'admin');
    }

    /**
     * Check if user can cancel a pending migration.
     */
    public function canCancelMigration(User $user, EnvironmentMigration $migration): bool
    {
        // Platform admins can cancel anything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // The person who requested can cancel
        if ($migration->requested_by === $user->id) {
            return $migration->canBeCancelled();
        }

        // Admin/Owner can also cancel
        $project = $migration->sourceEnvironment->project;

        return $this->hasMinimumRole($user, $project, 'admin');
    }

    /**
     * Check if user can view a migration.
     */
    public function canViewMigration(User $user, EnvironmentMigration $migration): bool
    {
        // Platform admins can view anything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // User must have access to the project
        $project = $migration->sourceEnvironment->project;

        return $this->getProjectAuthService()->canViewProject($user, $project);
    }

    /**
     * Validate that the migration follows the allowed chain (dev -> uat -> prod).
     */
    public function isValidMigrationChain(Environment $sourceEnv, Environment $targetEnv): bool
    {
        // Get environment types (default to development if not set)
        $sourceType = $sourceEnv->type ?? 'development';
        $targetType = $targetEnv->type ?? 'development';

        // Get chain positions
        $sourcePos = self::ENVIRONMENT_CHAIN[$sourceType] ?? 0;
        $targetPos = self::ENVIRONMENT_CHAIN[$targetType] ?? 0;

        // Target must be exactly one step ahead in the chain
        return ($targetPos - $sourcePos) === 1;
    }

    /**
     * Get the next environment in the migration chain.
     *
     * @return string|null The next environment type or null if at end of chain
     */
    public function getNextEnvironmentType(Environment $environment): ?string
    {
        $currentType = $environment->type ?? 'development';
        $currentPos = self::ENVIRONMENT_CHAIN[$currentType] ?? 0;

        $nextPos = $currentPos + 1;

        // Find the environment type at the next position
        foreach (self::ENVIRONMENT_CHAIN as $type => $pos) {
            if ($pos === $nextPos) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Check if an environment can be migrated from (not at end of chain).
     */
    public function canMigrateFrom(Environment $environment): bool
    {
        return $this->getNextEnvironmentType($environment) !== null;
    }

    /**
     * Get all users who can approve migrations for a project.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getApprovers(Project $project)
    {
        $approvers = collect();

        // Project admins and owners
        $projectMembers = $project->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();
        $approvers = $approvers->merge($projectMembers);

        // Team admins and owners
        $teamMembers = $project->team->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();
        $approvers = $approvers->merge($teamMembers);

        // Platform admins
        $platformAdmins = User::where('platform_role', 'admin')
            ->orWhere('is_superadmin', true)
            ->get();
        $approvers = $approvers->merge($platformAdmins);

        return $approvers->unique('id');
    }

    /**
     * Check if user has at least the specified role level in a project.
     */
    private function hasMinimumRole(User $user, Project $project, string $minimumRole): bool
    {
        $userRole = $this->getProjectAuthService()->getUserProjectRole($user, $project);
        if (! $userRole) {
            return false;
        }

        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$minimumRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Get detailed authorization result for UI.
     *
     * @return array{allowed: bool, requires_approval: bool, reason: string|null}
     */
    public function getAuthorizationDetails(User $user, Environment $sourceEnv, Environment $targetEnv): array
    {
        // Platform admins
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return [
                'allowed' => true,
                'requires_approval' => false,
                'reason' => null,
            ];
        }

        $project = $sourceEnv->project;

        // Check project access
        if (! $this->getProjectAuthService()->canViewProject($user, $project)) {
            return [
                'allowed' => false,
                'requires_approval' => false,
                'reason' => 'You do not have access to this project.',
            ];
        }

        // Check minimum role
        if (! $this->hasMinimumRole($user, $project, 'developer')) {
            return [
                'allowed' => false,
                'requires_approval' => false,
                'reason' => 'You need at least Developer role to migrate resources.',
            ];
        }

        // Validate chain
        if (! $this->isValidMigrationChain($sourceEnv, $targetEnv)) {
            $sourceType = $sourceEnv->type ?? 'development';
            $targetType = $targetEnv->type ?? 'development';
            $nextType = $this->getNextEnvironmentType($sourceEnv);

            if ($nextType === null) {
                return [
                    'allowed' => false,
                    'requires_approval' => false,
                    'reason' => "Cannot migrate from {$sourceType}: this is the final environment in the chain.",
                ];
            }

            return [
                'allowed' => false,
                'requires_approval' => false,
                'reason' => "Invalid migration chain. From {$sourceType} you can only migrate to {$nextType}.",
            ];
        }

        // Determine if approval is needed
        $requiresApproval = $this->requiresApproval($user, $sourceEnv, $targetEnv);

        return [
            'allowed' => true,
            'requires_approval' => $requiresApproval,
            'reason' => $requiresApproval
                ? 'Migration to production requires approval from an admin or owner.'
                : null,
        ];
    }
}
