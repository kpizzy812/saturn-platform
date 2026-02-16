<?php

namespace App\Http\Controllers\Inertia;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Project;
use Spatie\Activitylog\Models\Activity;

class ActivityHelper
{
    /**
     * Get activities for the current team formatted for frontend consumption.
     * Combines deployment history and Spatie activity log entries.
     */
    public static function getTeamActivities(int $limit = 50): array
    {
        $user = auth()->user();
        $team = $user?->currentTeam();

        if (! $team) {
            return [];
        }

        $activities = collect();

        // Get deployment activities from deployment queue
        $teamApplicationIds = Application::ownedByCurrentTeam()->pluck('id');
        if ($teamApplicationIds->isNotEmpty()) {
            $deployments = ApplicationDeploymentQueue::whereIn('application_id', $teamApplicationIds)
                ->with('application')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            foreach ($deployments as $deployment) {
                $app = $deployment->application;
                $appName = $app->name ?? 'Unknown';
                $status = $deployment->status;
                $deploymentUser = $deployment->user ?? $user;

                $action = match ($status) {
                    'finished' => 'deployment_completed',
                    'failed' => 'deployment_failed',
                    'in_progress', 'queued' => 'deployment_started',
                    default => 'deployment_started',
                };

                $description = match ($status) {
                    'finished' => "Deployment completed for {$appName}",
                    'failed' => "Deployment failed for {$appName}",
                    'in_progress' => "Deployment in progress for {$appName}",
                    'queued' => "Deployment queued for {$appName}",
                    default => "Deployment for {$appName}",
                };

                $activities->push([
                    'id' => 'deploy-'.$deployment->id,
                    'action' => $action,
                    'description' => $description,
                    'user' => [
                        'name' => $deploymentUser->getAttribute('name') ?? 'System',
                        'email' => $deploymentUser->getAttribute('email') ?? 'system@saturn.local',
                        'avatar' => $deploymentUser->avatar ? '/storage/'.$deploymentUser->avatar : null,
                    ],
                    'resource' => [
                        'type' => 'application',
                        'name' => $appName,
                        'id' => (string) ($app->uuid ?? ''),
                    ],
                    'timestamp' => $deployment->created_at->toIso8601String(),
                ]);
            }
        }

        // Also include Spatie activity log entries with a causer (real user actions)
        $memberIds = $team->members()->pluck('users.id')->toArray();
        $spatieActivities = Activity::query()
            ->where('causer_type', 'App\\Models\\User')
            ->whereIn('causer_id', $memberIds)
            ->with('causer', 'subject')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($spatieActivities as $activity) {
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
                $resourceName = $subject->getAttribute('name') ?? $subject->getAttribute('uuid') ?? class_basename($subject);
                $resourceId = (string) ($subject->getAttribute('uuid') ?? $subject->getAttribute('id') ?? '');
            }

            $event = $activity->event ?? $activity->log_name ?? 'unknown';
            $action = self::mapAction($event, $subject);

            $activities->push([
                'id' => 'activity-'.$activity->id,
                'action' => $action,
                'description' => $activity->description ?? self::buildDescription($action, $resourceName),
                'user' => [
                    'name' => $causer?->getAttribute('name') ?? 'System',
                    'email' => $causer?->getAttribute('email') ?? 'system@saturn.local',
                    'avatar' => $causer?->getAttribute('avatar') ? '/storage/'.$causer->getAttribute('avatar') : null,
                ],
                'resource' => [
                    'type' => $resourceType ?? 'application',
                    'name' => $resourceName ?? 'Unknown',
                    'id' => $resourceId ?? '',
                ],
                'timestamp' => $activity->created_at->toIso8601String(),
            ]);
        }

        // Sort by timestamp descending and limit
        return $activities
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get a single activity by ID.
     */
    public static function getActivity(string $id): ?array
    {
        // Handle deployment activities
        if (str_starts_with($id, 'deploy-')) {
            $deployId = (int) str_replace('deploy-', '', $id);
            $deployment = ApplicationDeploymentQueue::with('application')->find($deployId);
            if (! $deployment) {
                return null;
            }

            $app = $deployment->application;
            $appName = $app->name ?? 'Unknown';
            $status = $deployment->status;
            $user = auth()->user();

            $action = match ($status) {
                'finished' => 'deployment_completed',
                'failed' => 'deployment_failed',
                default => 'deployment_started',
            };

            $deploymentUser = $deployment->user ?? $user;

            return [
                'id' => $id,
                'action' => $action,
                'description' => self::buildDescription($action, $appName),
                'user' => [
                    'name' => $deploymentUser->getAttribute('name') ?? 'System',
                    'email' => $deploymentUser->getAttribute('email') ?? 'system@saturn.local',
                    'avatar' => $deploymentUser->avatar ? '/storage/'.$deploymentUser->avatar : null,
                ],
                'resource' => [
                    'type' => 'application',
                    'name' => $appName,
                    'id' => (string) ($app->uuid ?? ''),
                ],
                'timestamp' => $deployment->created_at->toIso8601String(),
            ];
        }

        // Handle Spatie activity log entries
        $activityId = str_replace('activity-', '', $id);
        $activity = Activity::with('causer', 'subject')->find($activityId);

        if (! $activity) {
            return null;
        }

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
            $resourceName = $subject->getAttribute('name') ?? $subject->getAttribute('uuid') ?? class_basename($subject);
            $resourceId = (string) ($subject->getAttribute('uuid') ?? $subject->getAttribute('id') ?? '');
        }

        $event = $activity->event ?? $activity->log_name ?? 'unknown';
        $action = self::mapAction($event, $subject);

        return [
            'id' => $id,
            'action' => $action,
            'description' => $activity->description ?? self::buildDescription($action, $resourceName),
            'user' => [
                'name' => $causer?->getAttribute('name') ?? 'System',
                'email' => $causer?->getAttribute('email') ?? 'system@saturn.local',
                'avatar' => $causer?->getAttribute('avatar') ? '/storage/'.$causer->getAttribute('avatar') : null,
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
        // For deployment activities, find other deployments for the same app
        if (str_starts_with($activityId, 'deploy-')) {
            $deployId = (int) str_replace('deploy-', '', $activityId);
            $deployment = ApplicationDeploymentQueue::find($deployId);

            if (! $deployment) {
                return [];
            }

            $related = ApplicationDeploymentQueue::where('application_id', $deployment->application_id)
                ->where('id', '!=', $deployId)
                ->with('application')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $user = auth()->user();

            return $related->map(function ($rel) use ($user) {
                $app = $rel->application;
                $appName = $app->name ?? 'Unknown';
                $action = match ($rel->status) {
                    'finished' => 'deployment_completed',
                    'failed' => 'deployment_failed',
                    default => 'deployment_started',
                };

                return [
                    'id' => 'deploy-'.$rel->id,
                    'action' => $action,
                    'description' => self::buildDescription($action, $appName),
                    'user' => [
                        'name' => $user->name ?? 'System',
                        'email' => $user->email ?? 'system@saturn.local',
                    ],
                    'resource' => [
                        'type' => 'application',
                        'name' => $appName,
                        'id' => (string) ($app->uuid ?? ''),
                    ],
                    'timestamp' => $rel->created_at->toIso8601String(),
                ];
            })->values()->toArray();
        }

        // For Spatie activities
        $realId = str_replace('activity-', '', $activityId);
        $activity = Activity::find($realId);

        if (! $activity || ! $activity->subject_type || ! $activity->subject_id) {
            return [];
        }

        $related = Activity::query()
            ->where('subject_type', $activity->subject_type)
            ->where('subject_id', $activity->subject_id)
            ->where('id', '!=', $realId)
            ->with('causer', 'subject')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $related->map(function ($rel) {
            $causer = $rel->causer;
            $event = $rel->event ?? $rel->log_name ?? 'unknown';

            return [
                'id' => 'activity-'.$rel->id,
                'action' => self::mapAction($event, $rel->subject),
                'description' => $rel->description ?? 'Activity',
                'user' => [
                    'name' => $causer?->getAttribute('name') ?? 'System',
                    'email' => $causer?->getAttribute('email') ?? 'system@saturn.local',
                ],
                'resource' => [
                    'type' => 'application',
                    'name' => $rel->subject?->getAttribute('name') ?? 'Unknown',
                    'id' => (string) ($rel->subject?->getAttribute('uuid') ?? $rel->subject?->getAttribute('id') ?? ''),
                ],
                'timestamp' => $rel->created_at->toIso8601String(),
            ];
        })->values()->toArray();
    }

    /**
     * Get activities scoped to a specific project and its children.
     * Combines deployment history and audit log entries for the project's resources.
     */
    public static function getProjectActivities(Project $project, int $limit = 30, int $offset = 0, ?string $actionFilter = null): array
    {
        $activities = collect();

        // Get project application IDs for deployment queries
        $applicationIds = $project->applications()->pluck('applications.id');

        // Get deployment activities for project's applications
        if ($applicationIds->isNotEmpty()) {
            $deploymentsQuery = ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
                ->with('application')
                ->orderBy('created_at', 'desc')
                ->limit($limit + $offset);

            $deployments = $deploymentsQuery->get();

            foreach ($deployments as $deployment) {
                $app = $deployment->application;
                $appName = $app->name ?? 'Unknown';
                $status = $deployment->status;
                $deploymentUser = $deployment->user ?? auth()->user();

                $action = match ($status) {
                    'finished' => 'deployment_completed',
                    'failed' => 'deployment_failed',
                    'in_progress', 'queued' => 'deployment_started',
                    default => 'deployment_started',
                };

                if ($actionFilter && $action !== $actionFilter) {
                    continue;
                }

                $activities->push([
                    'id' => 'deploy-'.$deployment->id,
                    'action' => $action,
                    'description' => self::buildDescription($action, $appName),
                    'user' => [
                        'name' => $deploymentUser->getAttribute('name') ?? 'System',
                        'email' => $deploymentUser->getAttribute('email') ?? 'system@saturn.local',
                    ],
                    'resource' => [
                        'type' => 'application',
                        'name' => $appName,
                        'id' => (string) ($app->uuid ?? ''),
                    ],
                    'timestamp' => $deployment->created_at->toIso8601String(),
                ]);
            }
        }

        // Collect all resource types and IDs belonging to this project
        $resourceScopes = [
            Project::class => [$project->id],
        ];

        // Environments
        $envIds = $project->environments()->pluck('id')->toArray();
        if (! empty($envIds)) {
            $resourceScopes[\App\Models\Environment::class] = $envIds;
        }

        // Applications
        if ($applicationIds->isNotEmpty()) {
            $resourceScopes[\App\Models\Application::class] = $applicationIds->toArray();
        }

        // Services
        $serviceIds = $project->services()->pluck('services.id')->toArray();
        if (! empty($serviceIds)) {
            $resourceScopes[\App\Models\Service::class] = $serviceIds;
        }

        // Project settings
        $settingsId = $project->settings?->id;
        if ($settingsId) {
            $resourceScopes[\App\Models\ProjectSetting::class] = [$settingsId];
        }

        // Query AuditLog for project-scoped resources
        $auditQuery = \App\Models\AuditLog::query()
            ->where(function ($query) use ($resourceScopes) {
                foreach ($resourceScopes as $type => $ids) {
                    $query->orWhere(function ($q) use ($type, $ids) {
                        $q->where('resource_type', $type)
                            ->whereIn('resource_id', $ids);
                    });
                }
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit + $offset);

        if ($actionFilter) {
            $auditQuery->where('action', $actionFilter);
        }

        $auditLogs = $auditQuery->get();

        foreach ($auditLogs as $log) {
            $action = $log->action;
            $resourceTypeName = $log->resource_type_name;

            $activities->push([
                'id' => 'audit-'.$log->id,
                'action' => $action,
                'description' => $log->description ?? "{$log->formatted_action} {$resourceTypeName}",
                'user' => [
                    'name' => $log->user->name ?? 'System',
                    'email' => $log->user->email ?? 'system@saturn.local',
                ],
                'resource' => [
                    'type' => strtolower($resourceTypeName ?? 'unknown'),
                    'name' => $log->resource_name ?? 'Unknown',
                    'id' => (string) ($log->resource_id ?? ''),
                ],
                'timestamp' => $log->created_at->toIso8601String(),
            ]);
        }

        // Sort by timestamp descending, apply offset and limit
        return $activities
            ->sortByDesc('timestamp')
            ->skip($offset)
            ->take($limit)
            ->values()
            ->toArray();
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
