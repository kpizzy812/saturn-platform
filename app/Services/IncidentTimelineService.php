<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for building incident timeline with correlated events, metrics, and alerts.
 *
 * Aggregates data from multiple sources to show a comprehensive view of
 * what happened leading up to and during an incident.
 */
class IncidentTimelineService
{
    /**
     * Event types for categorization
     */
    public const TYPE_STATUS_CHANGE = 'status_change';

    public const TYPE_DEPLOYMENT = 'deployment';

    public const TYPE_ALERT = 'alert';

    public const TYPE_ROLLBACK = 'rollback';

    public const TYPE_ACTION = 'action';

    public const TYPE_METRIC = 'metric';

    /**
     * Severity levels
     */
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_SUCCESS = 'success';

    /**
     * Get incident timeline for an application.
     *
     * @param  Application  $application  The application to get timeline for
     * @param  Carbon|null  $from  Start time (default: 24 hours ago)
     * @param  Carbon|null  $to  End time (default: now)
     * @param  int  $limit  Maximum number of events
     */
    public function getApplicationTimeline(
        Application $application,
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $limit = 100
    ): array {
        $from = $from ?? now()->subHours(24);
        $to = $to ?? now();

        $events = collect();

        // Collect events from all sources
        $events = $events->merge($this->getDeploymentEvents($application, $from, $to));
        $events = $events->merge($this->getAlertEvents($application, $from, $to));
        $events = $events->merge($this->getRollbackEvents($application, $from, $to));
        $events = $events->merge($this->getAuditLogEvents($application, $from, $to));

        // Sort by timestamp descending and limit
        $events = $events
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values();

        // Detect incidents (clusters of critical events)
        $incidents = $this->detectIncidents($events);

        // Calculate root cause suggestions
        $rootCause = $this->analyzeRootCause($events, $incidents);

        return [
            'events' => $events->toArray(),
            'incidents' => $incidents,
            'root_cause' => $rootCause,
            'summary' => $this->generateSummary($events, $incidents),
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
        ];
    }

    /**
     * Get deployment-related events.
     */
    protected function getDeploymentEvents(Application $application, Carbon $from, Carbon $to): Collection
    {
        $deployments = ApplicationDeploymentQueue::where('application_id', $application->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();

        return $deployments->map(function ($deployment) {
            $severity = match ($deployment->status) {
                'failed' => self::SEVERITY_CRITICAL,
                'finished' => self::SEVERITY_SUCCESS,
                'in_progress' => self::SEVERITY_INFO,
                default => self::SEVERITY_INFO,
            };

            return [
                'id' => 'deploy_'.$deployment->id,
                'type' => self::TYPE_DEPLOYMENT,
                'severity' => $severity,
                'timestamp' => $deployment->created_at->toIso8601String(),
                'title' => $this->getDeploymentTitle($deployment),
                'description' => $deployment->commit_message ?? 'Manual deployment',
                'metadata' => [
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'commit_message' => $deployment->commit_message,
                    'force_rebuild' => $deployment->force_rebuild,
                    'rollback' => $deployment->rollback,
                    'duration' => $deployment->updated_at && $deployment->created_at
                        ? $deployment->updated_at->diffInSeconds($deployment->created_at)
                        : null,
                ],
                'actions' => $this->getDeploymentActions($deployment),
            ];
        });
    }

    /**
     * Get alert events from alert history.
     */
    protected function getAlertEvents(Application $application, Carbon $from, Carbon $to): Collection
    {
        // Get alerts for this application's team/server
        $teamId = $application->environment?->project?->team_id;

        if (! $teamId) {
            return collect();
        }

        $alertHistories = AlertHistory::whereHas('alert', function ($query) use ($teamId) {
            $query->where('team_id', $teamId);
        })
            ->whereBetween('triggered_at', [$from, $to])
            ->with('alert')
            ->orderBy('triggered_at', 'desc')
            ->get();

        return $alertHistories->map(function (AlertHistory $history) {
            $alert = $history->alert;
            $alertName = $alert->getAttribute('name');
            $alertMetric = $alert->getAttribute('metric');
            $alertCondition = $alert->getAttribute('condition');
            $alertThreshold = $alert->getAttribute('threshold');
            $alertId = $alert->getAttribute('id');

            return [
                'id' => 'alert_'.$history->id,
                'type' => self::TYPE_ALERT,
                'severity' => self::SEVERITY_WARNING,
                'timestamp' => $history->triggered_at->toIso8601String(),
                'title' => "Alert: {$alertName}",
                'description' => "{$alertMetric} {$alertCondition} {$alertThreshold} (actual: {$history->value})",
                'metadata' => [
                    'alert_id' => $alertId,
                    'metric' => $alertMetric,
                    'condition' => $alertCondition,
                    'threshold' => $alertThreshold,
                    'actual_value' => $history->value,
                    'resolved_at' => $history->resolved_at?->toIso8601String(),
                    'duration' => $history->resolved_at
                        ? $history->resolved_at->diffInSeconds($history->triggered_at)
                        : null,
                ],
                'actions' => [
                    ['label' => 'View Alert', 'action' => 'view_alert', 'params' => ['id' => $alertId]],
                ],
            ];
        });
    }

    /**
     * Get rollback events.
     */
    protected function getRollbackEvents(Application $application, Carbon $from, Carbon $to): Collection
    {
        $rollbacks = ApplicationRollbackEvent::where('application_id', $application->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();

        return $rollbacks->map(function ($rollback) {
            $severity = match ($rollback->status) {
                'failed' => self::SEVERITY_CRITICAL,
                'success' => self::SEVERITY_SUCCESS,
                'triggered', 'in_progress' => self::SEVERITY_WARNING,
                default => self::SEVERITY_INFO,
            };

            return [
                'id' => 'rollback_'.$rollback->id,
                'type' => self::TYPE_ROLLBACK,
                'severity' => $severity,
                'timestamp' => $rollback->created_at->toIso8601String(),
                'title' => "Rollback: {$rollback->trigger_reason}",
                'description' => $this->getRollbackDescription($rollback),
                'metadata' => [
                    'rollback_id' => $rollback->id,
                    'trigger_reason' => $rollback->trigger_reason,
                    'status' => $rollback->status,
                    'from_deployment' => $rollback->from_deployment_id,
                    'to_deployment' => $rollback->to_deployment_id,
                    'metrics_snapshot' => $rollback->metrics_snapshot,
                ],
                'actions' => [],
            ];
        });
    }

    /**
     * Get audit log events related to the application.
     */
    protected function getAuditLogEvents(Application $application, Carbon $from, Carbon $to): Collection
    {
        $logs = AuditLog::where('resource_type', Application::class)
            ->where('resource_id', $application->id)
            ->whereBetween('created_at', [$from, $to])
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return $logs->map(function ($log) {
            $severity = $this->getAuditLogSeverity($log->action);

            return [
                'id' => 'audit_'.$log->id,
                'type' => self::TYPE_ACTION,
                'severity' => $severity,
                'timestamp' => $log->created_at->toIso8601String(),
                'title' => $log->formatted_action,
                'description' => $log->description ?? "Action: {$log->action}",
                'metadata' => [
                    'action' => $log->action,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'extra' => $log->metadata,
                ],
                'actions' => [],
            ];
        });
    }

    /**
     * Detect incidents from event clusters.
     */
    protected function detectIncidents(Collection $events): array
    {
        $incidents = [];
        $currentIncident = null;
        $incidentThreshold = 300; // 5 minutes gap to consider same incident

        $criticalEvents = $events->filter(fn ($e) => $e['severity'] === self::SEVERITY_CRITICAL || $e['severity'] === self::SEVERITY_WARNING);

        foreach ($criticalEvents->sortBy('timestamp') as $event) {
            $eventTime = Carbon::parse($event['timestamp']);

            if ($currentIncident === null) {
                $currentIncident = [
                    'id' => uniqid('incident_'),
                    'started_at' => $event['timestamp'],
                    'ended_at' => $event['timestamp'],
                    'events' => [$event['id']],
                    'severity' => $event['severity'],
                ];
            } else {
                $lastEventTime = Carbon::parse($currentIncident['ended_at']);
                $gap = $eventTime->diffInSeconds($lastEventTime);

                if ($gap <= $incidentThreshold) {
                    // Same incident
                    $currentIncident['ended_at'] = $event['timestamp'];
                    $currentIncident['events'][] = $event['id'];
                    if ($event['severity'] === self::SEVERITY_CRITICAL) {
                        $currentIncident['severity'] = self::SEVERITY_CRITICAL;
                    }
                } else {
                    // New incident
                    $incidents[] = $currentIncident;
                    $currentIncident = [
                        'id' => uniqid('incident_'),
                        'started_at' => $event['timestamp'],
                        'ended_at' => $event['timestamp'],
                        'events' => [$event['id']],
                        'severity' => $event['severity'],
                    ];
                }
            }
        }

        if ($currentIncident !== null) {
            $incidents[] = $currentIncident;
        }

        // Add duration to each incident
        return array_map(function ($incident) {
            $start = Carbon::parse($incident['started_at']);
            $end = Carbon::parse($incident['ended_at']);
            $incident['duration_seconds'] = $end->diffInSeconds($start);

            return $incident;
        }, $incidents);
    }

    /**
     * Analyze root cause based on events.
     */
    protected function analyzeRootCause(Collection $events, array $incidents): ?array
    {
        if (empty($incidents)) {
            return null;
        }

        // Find the most recent incident
        $latestIncident = end($incidents);
        $incidentEvents = $events->filter(fn ($e) => in_array($e['id'], $latestIncident['events']));

        // Look for patterns
        $causes = [];

        // Check for deployment before crash
        $deployments = $incidentEvents->filter(fn ($e) => $e['type'] === self::TYPE_DEPLOYMENT);
        if ($deployments->isNotEmpty()) {
            $failedDeploy = $deployments->firstWhere('metadata.status', 'failed');
            if ($failedDeploy) {
                $causes[] = [
                    'type' => 'deployment_failed',
                    'confidence' => 0.9,
                    'description' => 'Deployment failed, which may have caused the incident',
                    'suggestion' => 'Review deployment logs and rollback if necessary',
                    'related_event' => $failedDeploy['id'],
                ];
            }
        }

        // Check for alerts (resource exhaustion)
        $alerts = $incidentEvents->filter(fn ($e) => $e['type'] === self::TYPE_ALERT);
        foreach ($alerts as $alert) {
            $metric = $alert['metadata']['metric'] ?? 'unknown';
            $value = $alert['metadata']['actual_value'] ?? 0;

            if ($metric === 'memory' && $value > 90) {
                $causes[] = [
                    'type' => 'memory_exhaustion',
                    'confidence' => 0.85,
                    'description' => "Memory usage reached {$value}%, likely causing OOM kill",
                    'suggestion' => 'Increase memory limit or investigate memory leak',
                    'related_event' => $alert['id'],
                ];
            }

            if ($metric === 'cpu' && $value > 95) {
                $causes[] = [
                    'type' => 'cpu_exhaustion',
                    'confidence' => 0.7,
                    'description' => "CPU usage reached {$value}%",
                    'suggestion' => 'Check for infinite loops or increase CPU allocation',
                    'related_event' => $alert['id'],
                ];
            }
        }

        // Check for rollback triggers
        $rollbacks = $incidentEvents->filter(fn ($e) => $e['type'] === self::TYPE_ROLLBACK);
        foreach ($rollbacks as $rollback) {
            $reason = $rollback['metadata']['trigger_reason'] ?? 'unknown';
            $causes[] = [
                'type' => 'auto_rollback_triggered',
                'confidence' => 0.8,
                'description' => "Auto-rollback triggered due to: {$reason}",
                'suggestion' => 'Review the rollback trigger settings and fix the underlying issue',
                'related_event' => $rollback['id'],
            ];
        }

        if (empty($causes)) {
            return null;
        }

        // Sort by confidence
        usort($causes, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return [
            'primary' => $causes[0],
            'contributing' => array_slice($causes, 1),
        ];
    }

    /**
     * Generate summary of the timeline.
     */
    protected function generateSummary(Collection $events, array $incidents): array
    {
        $totalEvents = $events->count();
        $criticalCount = $events->filter(fn ($e) => $e['severity'] === self::SEVERITY_CRITICAL)->count();
        $warningCount = $events->filter(fn ($e) => $e['severity'] === self::SEVERITY_WARNING)->count();

        $deploymentCount = $events->filter(fn ($e) => $e['type'] === self::TYPE_DEPLOYMENT)->count();
        $failedDeployments = $events->filter(fn ($e) => $e['type'] === self::TYPE_DEPLOYMENT && ($e['metadata']['status'] ?? '') === 'failed')->count();

        return [
            'total_events' => $totalEvents,
            'critical_events' => $criticalCount,
            'warning_events' => $warningCount,
            'incidents_count' => count($incidents),
            'deployments' => [
                'total' => $deploymentCount,
                'failed' => $failedDeployments,
            ],
            'health_status' => $this->calculateHealthStatus($criticalCount, $warningCount, count($incidents)),
        ];
    }

    /**
     * Calculate overall health status.
     */
    protected function calculateHealthStatus(int $critical, int $warnings, int $incidents): string
    {
        if ($critical > 0 || $incidents > 0) {
            return 'critical';
        }
        if ($warnings > 2) {
            return 'degraded';
        }
        if ($warnings > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get deployment title based on status.
     */
    protected function getDeploymentTitle(ApplicationDeploymentQueue $deployment): string
    {
        $prefix = match ($deployment->status) {
            'queued' => 'Deployment queued',
            'in_progress' => 'Deployment in progress',
            'finished' => 'Deployment completed',
            'failed' => 'Deployment failed',
            'cancelled' => 'Deployment cancelled',
            default => 'Deployment',
        };

        if ($deployment->rollback) {
            $prefix = 'Rollback '.$prefix;
        }

        return $prefix;
    }

    /**
     * Get actions for deployment event.
     */
    protected function getDeploymentActions(ApplicationDeploymentQueue $deployment): array
    {
        $actions = [
            ['label' => 'View Logs', 'action' => 'view_logs', 'params' => ['uuid' => $deployment->deployment_uuid]],
        ];

        if ($deployment->status === 'finished') {
            $actions[] = ['label' => 'Rollback', 'action' => 'rollback', 'params' => ['uuid' => $deployment->deployment_uuid]];
        }

        return $actions;
    }

    /**
     * Get rollback description.
     */
    protected function getRollbackDescription(ApplicationRollbackEvent $rollback): string
    {
        return match ($rollback->trigger_reason) {
            'crash_loop' => 'Container crashed multiple times in short period',
            'health_check_failed' => 'Health check endpoint returned unhealthy status',
            'container_exited' => 'Container exited unexpectedly',
            'error_rate_exceeded' => 'Error rate exceeded configured threshold',
            'manual' => 'Manual rollback triggered by user',
            default => "Rollback triggered: {$rollback->trigger_reason}",
        };
    }

    /**
     * Get severity for audit log action.
     */
    protected function getAuditLogSeverity(string $action): string
    {
        return match ($action) {
            'delete', 'stop' => self::SEVERITY_WARNING,
            'start', 'restart', 'deploy' => self::SEVERITY_INFO,
            'create' => self::SEVERITY_SUCCESS,
            default => self::SEVERITY_INFO,
        };
    }
}
