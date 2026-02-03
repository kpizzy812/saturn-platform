<?php

namespace App\Actions\Transfer;

use App\Models\Team;
use App\Models\TeamResourceTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TransferTeamOwnership Action
 *
 * Transfers team ownership from one user to another.
 * Used when deleting a user who owns teams.
 */
class TransferTeamOwnership
{
    /**
     * Execute the team ownership transfer.
     *
     * @param  Team  $team  The team to transfer ownership
     * @param  User  $newOwner  The new owner
     * @param  User|null  $initiatedBy  The admin performing the transfer
     * @param  string|null  $reason  Reason for the transfer
     * @return TeamResourceTransfer The transfer record
     */
    public function execute(
        Team $team,
        User $newOwner,
        ?User $initiatedBy = null,
        ?string $reason = null
    ): TeamResourceTransfer {
        return DB::transaction(function () use ($team, $newOwner, $initiatedBy, $reason) {
            // Find current owner
            $currentOwner = $team->members()
                ->wherePivot('role', 'owner')
                ->first();

            // Create snapshot
            $snapshot = $this->createSnapshot($team, $currentOwner);

            // Create transfer record
            $transfer = TeamResourceTransfer::create([
                'transferable_type' => Team::class,
                'transferable_id' => $team->id,
                'from_team_id' => $team->id,
                'to_team_id' => $team->id,
                'from_user_id' => $currentOwner?->id,
                'to_user_id' => $newOwner->id,
                'initiated_by' => $initiatedBy?->id,
                'transfer_type' => TeamResourceTransfer::TYPE_TEAM_OWNERSHIP,
                'reason' => $reason,
                'status' => TeamResourceTransfer::STATUS_IN_PROGRESS,
                'resource_snapshot' => $snapshot,
            ]);

            try {
                // Demote current owner to admin (if exists and not being deleted)
                if ($currentOwner && $currentOwner->id !== $newOwner->id) {
                    $team->members()->updateExistingPivot($currentOwner->id, ['role' => 'admin']);
                }

                // Check if new owner is already a member
                $existingMembership = $team->members()->where('user_id', $newOwner->id)->first();

                if ($existingMembership) {
                    // Promote to owner
                    $team->members()->updateExistingPivot($newOwner->id, ['role' => 'owner']);
                } else {
                    // Add as owner
                    $team->members()->attach($newOwner->id, ['role' => 'owner']);
                }

                $transfer->markAsCompleted();

                return $transfer;
            } catch (\Exception $e) {
                // Rollback
                if ($currentOwner) {
                    $team->members()->updateExistingPivot($currentOwner->id, ['role' => 'owner']);
                }
                $team->members()->updateExistingPivot($newOwner->id, ['role' => 'admin']);

                $transfer->markAsFailed($e->getMessage());

                throw $e;
            }
        });
    }

    /**
     * Create a snapshot of the team state.
     */
    private function createSnapshot(Team $team, ?User $currentOwner): array
    {
        return [
            'name' => $team->name,
            'personal_team' => $team->personal_team,
            'current_owner_id' => $currentOwner?->id,
            'current_owner_name' => $currentOwner?->name,
            'current_owner_email' => $currentOwner?->email,
            'members_count' => $team->members()->count(),
            'projects_count' => $team->projects()->count(),
            'servers_count' => $team->servers()->count(),
            'snapshot_at' => now()->toISOString(),
        ];
    }
}
