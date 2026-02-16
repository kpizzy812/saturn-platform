<?php

namespace App\Actions\Team;

use App\Models\AuditLog;
use App\Models\MemberArchive;
use App\Models\Team;
use App\Models\TeamResourceTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveAndKickMemberAction
{
    /**
     * Get contributions summary for a team member (used for preview in modal).
     */
    public function getContributions(Team $team, User $member): array
    {
        $query = AuditLog::forTeam($team->id)->byUser($member->id);

        $totalActions = (clone $query)->count();
        $deployCount = (clone $query)->byAction('deploy')->count();

        // Aggregate by action type
        $byAction = (clone $query)
            ->selectRaw('action, count(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->pluck('count', 'action')
            ->toArray();

        // Aggregate by resource type
        $byResourceType = (clone $query)
            ->whereNotNull('resource_type')
            ->selectRaw('resource_type, count(*) as count')
            ->groupBy('resource_type')
            ->orderByDesc('count')
            ->pluck('count', 'resource_type')
            ->map(fn ($count, $type) => [
                'type' => class_basename($type),
                'full_type' => $type,
                'count' => $count,
            ])
            ->values()
            ->toArray();

        // Top 10 resources by action count
        $topResourceRows = (clone $query)
            ->whereNotNull('resource_type')
            ->whereNotNull('resource_id')
            ->selectRaw('resource_type, resource_id, resource_name, count(*) as action_count')
            ->groupBy('resource_type', 'resource_id', 'resource_name')
            ->orderByDesc('action_count')
            ->limit(10)
            ->get();

        $topResources = [];
        foreach ($topResourceRows as $row) {
            $name = $row->resource_name;

            // Resolve name from actual model if audit log has no resource_name
            if (! $name && $row->resource_type && $row->resource_id) {
                $name = $this->resolveResourceName($row->resource_type, $row->resource_id);
            }

            $topResources[] = [
                'type' => class_basename((string) $row->resource_type),
                'full_type' => $row->resource_type,
                'id' => $row->resource_id,
                'name' => $name ?? 'Deleted resource',
                'action_count' => (int) data_get($row, 'action_count', 0),
            ];
        }

        // Date range
        $firstAction = (clone $query)->orderBy('created_at', 'asc')->value('created_at');
        $lastAction = (clone $query)->orderBy('created_at', 'desc')->value('created_at');

        // Recent activities for timeline
        $recentActivities = (clone $query)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (AuditLog $log) {
                $name = $log->resource_name;

                // Resolve name from actual model if audit log has no resource_name
                if (! $name && $log->resource_type && $log->resource_id) {
                    $name = $this->resolveResourceName($log->resource_type, $log->resource_id);
                }

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'formatted_action' => $log->formatted_action,
                    'resource_type' => $log->resource_type_name,
                    'resource_name' => $name,
                    'description' => $log->description,
                    'created_at' => $log->created_at->toISOString(),
                ];
            })
            ->toArray();

        // Count created resources
        $createdCount = (clone $query)->byAction('create')->count();

        return [
            'total_actions' => $totalActions,
            'deploy_count' => $deployCount,
            'created_count' => $createdCount,
            'by_action' => $byAction,
            'by_resource_type' => $byResourceType,
            'top_resources' => $topResources,
            'first_action' => $firstAction?->toISOString(),
            'last_action' => $lastAction?->toISOString(),
            'recent_activities' => $recentActivities,
        ];
    }

    /**
     * Resolve a human-readable name from an actual model record.
     */
    private function resolveResourceName(string $resourceType, int $resourceId): ?string
    {
        try {
            if (! class_exists($resourceType)) {
                return null;
            }

            $model = $resourceType::find($resourceId);
            if (! $model) {
                return null;
            }

            return $model->getAttribute('name')
                ?? $model->getAttribute('title')
                ?? $model->getAttribute('key')
                ?? (method_exists($model, 'getName') ? $model->getName() : null)
                ?? (method_exists($model, 'getTitle') ? $model->getTitle() : null);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Archive member data, create transfers, and kick from team.
     *
     * @param  array  $transfers  Array of transfer definitions: [{resource_type, resource_id, resource_name, to_user_id}]
     */
    public function execute(
        Team $team,
        User $member,
        User $kickedBy,
        ?string $reason = null,
        array $transfers = []
    ): MemberArchive {
        return DB::transaction(function () use ($team, $member, $kickedBy, $reason, $transfers) {
            // Get pivot data before detach
            $pivot = $team->members()
                ->where('user_id', $member->id)
                ->first()
                ?->pivot;

            // Build contribution summary (without recent_activities to save space)
            $contributions = $this->getContributions($team, $member);
            unset($contributions['recent_activities']);

            // Build access snapshot
            $accessSnapshot = [
                'role' => $pivot ? $pivot->getAttribute('role') : 'unknown',
                'allowed_projects' => $pivot?->getAttribute('allowed_projects'),
                'permission_set_id' => $pivot?->getAttribute('permission_set_id'),
            ];

            // Create transfer records
            $transferIds = [];
            foreach ($transfers as $transfer) {
                $record = TeamResourceTransfer::create([
                    'transfer_type' => TeamResourceTransfer::TYPE_ARCHIVE,
                    'transferable_type' => $transfer['resource_type'],
                    'transferable_id' => $transfer['resource_id'],
                    'from_team_id' => $team->id,
                    'to_team_id' => $team->id,
                    'from_user_id' => $member->id,
                    'to_user_id' => $transfer['to_user_id'],
                    'initiated_by' => $kickedBy->id,
                    'reason' => "Member kick: attribution transfer for {$transfer['resource_name']}",
                    'resource_snapshot' => [
                        'name' => $transfer['resource_name'],
                        'type' => class_basename($transfer['resource_type']),
                    ],
                ]);
                $record->markAsCompleted();
                $transferIds[] = $record->id;
            }

            // Determine status
            $status = 'completed';
            if (! empty($transfers) && count($transferIds) < count($transfers)) {
                $status = 'partial_transfer';
            }

            // Create archive record
            $archive = MemberArchive::create([
                'team_id' => $team->id,
                'user_id' => $member->id,
                'member_name' => $member->name,
                'member_email' => $member->email,
                'member_role' => $pivot ? $pivot->getAttribute('role') : 'unknown',
                'member_joined_at' => $pivot?->getAttribute('created_at'),
                'kicked_by' => $kickedBy->id,
                'kicked_by_name' => $kickedBy->name,
                'kick_reason' => $reason,
                'contribution_summary' => $contributions,
                'access_snapshot' => $accessSnapshot,
                'transfer_ids' => $transferIds,
                'status' => $status,
            ]);

            // Log the kick action
            AuditLog::log(
                'member_kicked',
                $archive,
                "Removed {$member->name} ({$member->email}) from team",
                [
                    'member_id' => $member->id,
                    'member_email' => $member->email,
                    'reason' => $reason,
                    'transfers_count' => count($transferIds),
                ]
            );

            // Actually detach the member
            $team->members()->detach($member->id);

            return $archive;
        });
    }
}
