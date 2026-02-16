<?php

namespace App\Actions\Transfer;

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamResourceTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TransferProject Action
 *
 * Transfers a project from one team to another.
 * All related resources (environments, applications, services, databases) stay with the project.
 */
class TransferProject
{
    /**
     * Execute the project transfer.
     *
     * @param  Project  $project  The project to transfer
     * @param  Team  $targetTeam  The destination team
     * @param  User|null  $initiatedBy  The admin performing the transfer
     * @param  string|null  $reason  Reason for the transfer
     * @return TeamResourceTransfer The transfer record
     */
    public function execute(
        Project $project,
        Team $targetTeam,
        ?User $initiatedBy = null,
        ?string $reason = null
    ): TeamResourceTransfer {
        return DB::transaction(function () use ($project, $targetTeam, $initiatedBy, $reason) {
            $sourceTeam = $project->team;

            // Create snapshot of the project state
            $snapshot = $this->createSnapshot($project);

            // Create transfer record
            $transfer = TeamResourceTransfer::create([
                'transferable_type' => Project::class,
                'transferable_id' => $project->id,
                'from_team_id' => $sourceTeam->id,
                'to_team_id' => $targetTeam->id,
                'initiated_by' => $initiatedBy?->id,
                'transfer_type' => TeamResourceTransfer::TYPE_PROJECT_TRANSFER,
                'reason' => $reason,
                'status' => TeamResourceTransfer::STATUS_IN_PROGRESS,
                'resource_snapshot' => $snapshot,
            ]);

            try {
                // Perform the transfer
                $project->update(['team_id' => $targetTeam->id]);

                // Update project_user pivot - add team admins as project admins
                $this->syncProjectUsers($project, $targetTeam);

                // Record related resources
                $relatedTransfers = $this->recordRelatedResources($project);
                $transfer->update(['related_transfers' => $relatedTransfers]);

                // Mark as completed
                $transfer->markAsCompleted();

                return $transfer;
            } catch (\Exception $e) {
                // Rollback project team
                $project->update(['team_id' => $sourceTeam->id]);

                $transfer->markAsFailed($e->getMessage());

                throw $e;
            }
        });
    }

    /**
     * Create a snapshot of the project state for audit/rollback.
     */
    private function createSnapshot(Project $project): array
    {
        $project->load(['environments.applications', 'environments.services', 'settings']);

        return [
            'name' => $project->name,
            'description' => $project->description,
            'team_id' => $project->team_id,
            'team_name' => $project->team?->name,
            'environments_count' => $project->environments->count(),
            'applications_count' => $project->environments->sum(fn ($env) => $env->applications->count()),
            'services_count' => $project->environments->sum(fn ($env) => $env->services->count()),
            'environments' => $project->environments->map(fn ($env) => [
                'id' => $env->id,
                'name' => $env->name,
                'type' => $env->type,
            ])->toArray(),
            'snapshot_at' => now()->toISOString(),
        ];
    }

    /**
     * Sync project users - add target team admins as project admins.
     */
    private function syncProjectUsers(Project $project, Team $targetTeam): void
    {
        // Get team admins and owners
        $teamAdmins = $targetTeam->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();

        foreach ($teamAdmins as $admin) {
            // Add as project admin if not already exists
            if (! $project->members()->where('user_id', $admin->id)->exists()) {
                $project->members()->attach($admin->id, ['role' => 'admin']);
            }
        }
    }

    /**
     * Record related resources that were transferred.
     */
    private function recordRelatedResources(Project $project): array
    {
        $related = [];

        foreach ($project->environments as $environment) {
            $related['environments'][] = [
                'id' => $environment->id,
                'name' => $environment->name,
            ];

            foreach ($environment->applications as $app) {
                $related['applications'][] = [
                    'id' => $app->id,
                    'name' => $app->name,
                    'environment' => $environment->name,
                ];
            }

            foreach ($environment->services as $service) {
                $related['services'][] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'environment' => $environment->name,
                ];
            }

            // Add databases (all types)
            $databaseTypes = [
                'postgresqls', 'mysqls', 'mariadbs', 'mongodbs',
                'redis', 'keydbs', 'dragonflies', 'clickhouses',
            ];

            foreach ($databaseTypes as $dbType) {
                foreach ($environment->$dbType ?? [] as $db) {
                    $related['databases'][] = [
                        'id' => $db->id,
                        'name' => $db->name,
                        'type' => class_basename($db),
                        'environment' => $environment->name,
                    ];
                }
            }
        }

        return $related;
    }
}
