<?php

namespace App\Http\Controllers\Inertia;

use Spatie\Activitylog\Models\Activity;

class ActivityHelper
{
    /**
     * Get activities for the current team formatted for frontend consumption.
     */
    public static function getTeamActivities(int $limit = 50): array
    {
        $user = auth()->user();
        $team = $user?->currentTeam();

        if (! $team) {
            return [];
        }

        $memberIds = $team->members()->pluck('users.id')->toArray();

        $activities = Activity::query()
            ->where('causer_type', 'App\\Models\\User')
            ->whereIn('causer_id', $memberIds)
            ->with('causer', 'subject')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $activities->map(function ($activity) {
            $causer = $activity->causer;
            $subject = $activity->subject;

            // Determine resource type from subject
            $resourceType = null;
            $resourceName = null;
            $resourceId = null;

            if ($subject) {
                $className = class_basename($subject);
                $resourceType = match (true) {
                    str_contains($className, 'Application') => 'application',
                    str_contains($className, 'Service') => 'service',
                    str_contains($className, 'Standalone') || str_contains($className, 'Database') => 'database',
                    str_contains($className, 'Server') => 'server',
                    str_contains($className, 'Project') => 'project',
                    str_contains($className, 'Team') => 'team',
                    default => strtolower($className),
                };
                $resourceName = $subject->name ?? $subject->uuid ?? class_basename($subject);
                $resourceId = (string) ($subject->uuid ?? $subject->id ?? '');
            }

            // Map Spatie events to our frontend action types
            $event = $activity->event ?? $activity->log_name ?? 'unknown';
            $action = self::mapAction($event, $subject);

            return [
                'id' => (string) $activity->id,
                'action' => $action,
                'description' => $activity->description ?? self::buildDescription($action, $resourceName),
                'user' => [
                    'name' => $causer?->name ?? 'System',
                    'email' => $causer?->email ?? 'system@saturn.local',
                    'avatar' => null,
                ],
                'resource' => [
                    'type' => $resourceType ?? 'application',
                    'name' => $resourceName ?? 'Unknown',
                    'id' => $resourceId ?? '',
                ],
                'timestamp' => $activity->created_at->toIso8601String(),
            ];
        })->values()->toArray();
    }

    /**
     * Get a single activity by ID.
     */
    public static function getActivity(string $id): ?array
    {
        $activity = Activity::with('causer', 'subject')->find($id);

        if (! $activity) {
            return null;
        }

        $activities = collect([$activity]);
        $result = self::getTeamActivities(1);

        // Re-fetch with the proper logic
        $causer = $activity->causer;
        $subject = $activity->subject;

        $resourceType = null;
        $resourceName = null;
        $resourceId = null;

        if ($subject) {
            $className = class_basename($subject);
            $resourceType = match (true) {
                str_contains($className, 'Application') => 'application',
                str_contains($className, 'Service') => 'service',
                str_contains($className, 'Standalone') || str_contains($className, 'Database') => 'database',
                str_contains($className, 'Server') => 'server',
                str_contains($className, 'Project') => 'project',
                str_contains($className, 'Team') => 'team',
                default => strtolower($className),
            };
            $resourceName = $subject->name ?? $subject->uuid ?? class_basename($subject);
            $resourceId = (string) ($subject->uuid ?? $subject->id ?? '');
        }

        $event = $activity->event ?? $activity->log_name ?? 'unknown';
        $action = self::mapAction($event, $subject);

        return [
            'id' => (string) $activity->id,
            'action' => $action,
            'description' => $activity->description ?? self::buildDescription($action, $resourceName),
            'user' => [
                'name' => $causer?->name ?? 'System',
                'email' => $causer?->email ?? 'system@saturn.local',
                'avatar' => null,
            ],
            'resource' => [
                'type' => $resourceType ?? 'application',
                'name' => $resourceName ?? 'Unknown',
                'id' => $resourceId ?? '',
            ],
            'timestamp' => $activity->created_at->toIso8601String(),
        ];
    }

    /**
     * Get related activities for a resource.
     */
    public static function getRelatedActivities(string $activityId, int $limit = 10): array
    {
        $activity = Activity::find($activityId);

        if (! $activity || ! $activity->subject_type || ! $activity->subject_id) {
            return [];
        }

        $related = Activity::query()
            ->where('subject_type', $activity->subject_type)
            ->where('subject_id', $activity->subject_id)
            ->where('id', '!=', $activityId)
            ->with('causer', 'subject')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $related->map(function ($rel) {
            $causer = $rel->causer;
            $event = $rel->event ?? $rel->log_name ?? 'unknown';

            return [
                'id' => (string) $rel->id,
                'action' => self::mapAction($event, $rel->subject),
                'description' => $rel->description ?? 'Activity',
                'user' => [
                    'name' => $causer?->name ?? 'System',
                    'email' => $causer?->email ?? 'system@saturn.local',
                ],
                'resource' => [
                    'type' => 'application',
                    'name' => $rel->subject?->name ?? 'Unknown',
                    'id' => (string) ($rel->subject?->uuid ?? $rel->subject?->id ?? ''),
                ],
                'timestamp' => $rel->created_at->toIso8601String(),
            ];
        })->values()->toArray();
    }

    /**
     * Map Spatie event names to frontend-compatible action types.
     */
    protected static function mapAction(string $event, mixed $subject): string
    {
        $subjectClass = $subject ? class_basename($subject) : '';

        return match ($event) {
            'created' => match (true) {
                str_contains($subjectClass, 'Database') || str_contains($subjectClass, 'Standalone') => 'database_created',
                str_contains($subjectClass, 'Server') => 'server_connected',
                default => 'settings_updated',
            },
            'updated' => match (true) {
                str_contains($subjectClass, 'Application') => 'settings_updated',
                default => 'settings_updated',
            },
            'deleted' => match (true) {
                str_contains($subjectClass, 'Database') || str_contains($subjectClass, 'Standalone') => 'database_deleted',
                str_contains($subjectClass, 'Server') => 'server_disconnected',
                default => 'settings_updated',
            },
            'deployed' => 'deployment_started',
            'deployment_completed' => 'deployment_completed',
            'deployment_failed' => 'deployment_failed',
            'started' => 'application_started',
            'stopped' => 'application_stopped',
            'restarted' => 'application_restarted',
            default => 'settings_updated',
        };
    }

    /**
     * Build a description from the action and resource name.
     */
    protected static function buildDescription(string $action, ?string $resourceName): string
    {
        $name = $resourceName ?? 'resource';

        return match ($action) {
            'deployment_started' => "Started deployment for {$name}",
            'deployment_completed' => "Deployment completed for {$name}",
            'deployment_failed' => "Deployment failed for {$name}",
            'settings_updated' => "Updated settings for {$name}",
            'database_created' => "Created database {$name}",
            'database_deleted' => "Deleted database {$name}",
            'server_connected' => "Connected server {$name}",
            'server_disconnected' => "Disconnected server {$name}",
            'application_started' => "Started {$name}",
            'application_stopped' => "Stopped {$name}",
            'application_restarted' => "Restarted {$name}",
            'team_member_added' => 'Added team member',
            'team_member_removed' => 'Removed team member',
            default => "Action on {$name}",
        };
    }
}
