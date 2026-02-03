<?php

namespace App\Services\Transfer;

use App\Actions\Transfer\TransferProject;
use App\Actions\Transfer\TransferTeamOwnership;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamResourceTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UserResourceTransferService
 *
 * Manages the transfer of user resources when deleting a user account.
 * Ensures all projects and teams are properly transferred before deletion.
 */
class UserResourceTransferService
{
    public function __construct(
        private TransferProject $transferProject,
        private TransferTeamOwnership $transferTeamOwnership
    ) {}

    /**
     * Get all resources owned by a user that need to be transferred.
     *
     * @return array{teams: Collection, projects: Collection, servers_count: int, has_resources: bool}
     */
    public function getUserResources(User $user): array
    {
        // Get teams where user is owner
        $ownedTeams = $user->teams()
            ->wherePivot('role', 'owner')
            ->with(['projects.environments.applications', 'servers'])
            ->get();

        // Get projects in owned teams
        $projects = collect();
        $serversCount = 0;

        foreach ($ownedTeams as $team) {
            $projects = $projects->merge($team->projects);
            $serversCount += $team->servers()->count();
        }

        return [
            'teams' => $ownedTeams,
            'projects' => $projects,
            'servers_count' => $serversCount,
            'has_resources' => $ownedTeams->isNotEmpty() || $projects->isNotEmpty(),
        ];
    }

    /**
     * Get detailed resource tree for display in admin UI.
     */
    public function getResourceTree(User $user): array
    {
        $resources = $this->getUserResources($user);
        $tree = [];

        foreach ($resources['teams'] as $team) {
            $teamNode = [
                'id' => $team->id,
                'name' => $team->name,
                'type' => 'team',
                'personal_team' => $team->personal_team,
                'projects' => [],
                'servers_count' => $team->servers()->count(),
                'can_transfer' => true,
            ];

            foreach ($team->projects as $project) {
                $projectNode = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'type' => 'project',
                    'environments' => [],
                ];

                foreach ($project->environments as $environment) {
                    $envNode = [
                        'id' => $environment->id,
                        'name' => $environment->name,
                        'type' => $environment->type,
                        'applications_count' => $environment->applications()->count(),
                        'services_count' => $environment->services()->count(),
                        'databases_count' => $this->countDatabases($environment),
                    ];
                    $projectNode['environments'][] = $envNode;
                }

                $teamNode['projects'][] = $projectNode;
            }

            $tree[] = $teamNode;
        }

        return $tree;
    }

    /**
     * Count all databases in an environment.
     */
    private function countDatabases($environment): int
    {
        return $environment->postgresqls()->count()
            + $environment->mysqls()->count()
            + $environment->mariadbs()->count()
            + $environment->mongodbs()->count()
            + $environment->redis()->count()
            + $environment->keydbs()->count()
            + $environment->dragonflies()->count()
            + $environment->clickhouses()->count();
    }

    /**
     * Transfer all user resources to a target team.
     *
     * @param  User  $user  The user being deleted
     * @param  Team  $targetTeam  The team to transfer resources to
     * @param  User  $initiatedBy  The admin performing the transfer
     * @param  string|null  $reason  Reason for the transfer
     * @return Collection<TeamResourceTransfer> Collection of transfer records
     */
    public function transferAllToTeam(
        User $user,
        Team $targetTeam,
        User $initiatedBy,
        ?string $reason = null
    ): Collection {
        $transfers = collect();
        $resources = $this->getUserResources($user);

        DB::transaction(function () use ($user, $targetTeam, $initiatedBy, $reason, $resources, &$transfers) {
            foreach ($resources['teams'] as $team) {
                // Skip if this is the target team
                if ($team->id === $targetTeam->id) {
                    continue;
                }

                // Transfer each project from the team
                foreach ($team->projects as $project) {
                    $transfer = $this->transferProject->execute(
                        $project,
                        $targetTeam,
                        $initiatedBy,
                        $reason ?? "User deletion: {$user->email}"
                    );
                    $transfers->push($transfer);
                }

                // Remove user from team after projects are transferred
                $team->members()->detach($user->id);

                // If team is now empty, delete it
                if ($team->members()->count() === 0) {
                    $team->delete();
                }
            }
        });

        return $transfers;
    }

    /**
     * Transfer team ownership to another user.
     *
     * @param  User  $fromUser  The current owner
     * @param  User  $toUser  The new owner
     * @param  User  $initiatedBy  The admin performing the transfer
     * @param  string|null  $reason  Reason for the transfer
     * @return Collection<TeamResourceTransfer> Collection of transfer records
     */
    public function transferOwnershipToUser(
        User $fromUser,
        User $toUser,
        User $initiatedBy,
        ?string $reason = null
    ): Collection {
        $transfers = collect();
        $resources = $this->getUserResources($fromUser);

        DB::transaction(function () use ($fromUser, $toUser, $initiatedBy, $reason, $resources, &$transfers) {
            foreach ($resources['teams'] as $team) {
                // Transfer team ownership
                $transfer = $this->transferTeamOwnership->execute(
                    $team,
                    $toUser,
                    $initiatedBy,
                    $reason ?? "User deletion: {$fromUser->email}"
                );
                $transfers->push($transfer);
            }
        });

        return $transfers;
    }

    /**
     * Archive user resources to a system archive team.
     *
     * @param  User  $user  The user being deleted
     * @param  User  $initiatedBy  The admin performing the action
     * @return Collection<TeamResourceTransfer> Collection of transfer records
     */
    public function archiveUserResources(User $user, User $initiatedBy): Collection
    {
        // Get or create archive team
        $archiveTeam = Team::firstOrCreate(
            ['name' => 'Archived Resources'],
            ['personal_team' => false]
        );

        // Ensure superadmin is in archive team
        if (! $archiveTeam->members()->where('user_id', $initiatedBy->id)->exists()) {
            $archiveTeam->members()->attach($initiatedBy->id, ['role' => 'owner']);
        }

        return $this->transferAllToTeam(
            $user,
            $archiveTeam,
            $initiatedBy,
            "Archived from deleted user: {$user->email}"
        );
    }

    /**
     * Get available transfer destinations for a user's resources.
     *
     * @return array{teams: Collection, users: Collection}
     */
    public function getTransferDestinations(User $excludeUser): array
    {
        // Get all teams except user's personal teams
        $teams = Team::where('personal_team', false)
            ->orWhereHas('members', function ($query) use ($excludeUser) {
                $query->where('user_id', '!=', $excludeUser->id)
                    ->whereIn('role', ['owner', 'admin']);
            })
            ->orderBy('name')
            ->get();

        // Get all active users except the one being deleted
        $users = User::where('id', '!=', $excludeUser->id)
            ->where('status', 'active')
            ->where('id', '!=', 0) // Exclude root user
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'teams' => $teams,
            'users' => $users,
        ];
    }

    /**
     * Validate that user can be safely deleted after transfers.
     */
    public function canSafelyDeleteUser(User $user): array
    {
        $resources = $this->getUserResources($user);
        $issues = [];

        foreach ($resources['teams'] as $team) {
            // Check if this is the only member
            if ($team->members()->count() === 1) {
                if ($team->projects()->count() > 0) {
                    $issues[] = "Team '{$team->name}' has projects that need to be transferred";
                }
                if ($team->servers()->count() > 0) {
                    $issues[] = "Team '{$team->name}' has servers that need to be transferred";
                }
            }
        }

        return [
            'can_delete' => empty($issues) || ! $resources['has_resources'],
            'issues' => $issues,
            'has_resources' => $resources['has_resources'],
        ];
    }
}
