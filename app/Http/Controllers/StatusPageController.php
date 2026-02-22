<?php

namespace App\Http\Controllers;

use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Service;
use App\Models\StatusPageDailySnapshot;
use App\Models\StatusPageIncident;
use App\Models\StatusPageIncidentUpdate;
use App\Models\StatusPageResource;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class StatusPageController extends Controller
{
    /**
     * Public status page — no authentication required.
     * Supports auto (all resources) and manual (configured resources) modes.
     */
    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        $settings = InstanceSettings::get();

        if (! $settings->is_status_page_enabled) {
            return redirect('/');
        }

        return Cache::remember('status_page_data', 60, function () use ($settings) {
            $title = $settings->status_page_title ?? $settings->instance_name ?? 'Saturn';
            $description = $settings->status_page_description ?? '';
            $mode = $settings->status_page_mode ?? 'auto';

            if ($mode === 'auto') {
                $result = $this->buildAutoData();
            } else {
                $result = $this->buildManualData();
            }

            $services = $result['services'];
            $allStatuses = collect($services)->pluck('status')->all();
            $overallStatus = StatusPageResource::computeOverallStatus($allStatuses);

            // Load snapshots for all services (90 days)
            $services = $this->attachSnapshots($services);

            // Load incidents
            $activeIncidents = StatusPageIncident::with('updates')->active()->get();
            $recentIncidents = StatusPageIncident::with('updates')->recent(7)->get();

            $incidents = $activeIncidents->merge($recentIncidents)->map(function (StatusPageIncident $i) {
                return [
                    'id' => $i->id,
                    'title' => $i->title,
                    'severity' => $i->severity,
                    'status' => $i->status,
                    'startedAt' => $i->started_at->toIso8601String(),
                    'resolvedAt' => $i->resolved_at?->toIso8601String(),
                    'updates' => $i->updates->map(function (StatusPageIncidentUpdate $u) {
                        return [
                            'status' => $u->status,
                            'message' => $u->message,
                            'postedAt' => $u->posted_at->toIso8601String(),
                        ];
                    }),
                ];
            });

            // Override overall status if active incidents exist
            if ($activeIncidents->isNotEmpty()) {
                $worstSeverity = $activeIncidents->pluck('severity')->reduce(function ($carry, $severity) {
                    $priority = ['critical' => 3, 'major' => 2, 'minor' => 1, 'maintenance' => 0];

                    return ($priority[$severity] ?? 0) > ($priority[$carry] ?? 0) ? $severity : $carry;
                }, 'maintenance');

                if (in_array($worstSeverity, ['critical', 'major'])) {
                    $overallStatus = 'major_outage';
                } elseif ($worstSeverity === 'minor') {
                    $overallStatus = 'partial_outage';
                }
            }

            return Inertia::render('StatusPage/Index', [
                'title' => $title,
                'description' => $description,
                'overallStatus' => $overallStatus,
                'services' => $services,
                'incidents' => $incidents->values(),
            ]);
        });
    }

    /**
     * Auto mode: load all servers + their resources automatically.
     */
    private function buildAutoData(): array
    {
        $services = [];

        $servers = Server::with('latestHealthCheck')
            ->whereNull('deleted_at')
            ->get();

        foreach ($servers as $server) {
            $healthCheck = $server->latestHealthCheck;
            $serverStatus = $healthCheck ? StatusPageResource::normalizeStatus($healthCheck->status) : 'unknown';

            $services[] = [
                'name' => $server->name,
                'status' => $serverStatus,
                'group' => 'Servers',
                'resourceType' => 'server',
                'resourceId' => $server->id,
            ];

            // Applications on this server
            foreach ($server->applications() as $app) {
                $services[] = [
                    'name' => $app->name,
                    'status' => StatusPageResource::normalizeStatus($app->status ?? 'unknown'),
                    'group' => $server->name,
                    'resourceType' => 'application',
                    'resourceId' => $app->id,
                ];
            }

            // Services on this server
            foreach ($server->services as $svc) {
                $services[] = [
                    'name' => $svc->name,
                    'status' => StatusPageResource::normalizeStatus($svc->status ?? 'unknown'),
                    'group' => $server->name,
                    'resourceType' => 'service',
                    'resourceId' => $svc->id,
                ];
            }
        }

        return ['services' => $services];
    }

    /**
     * Manual mode: load configured StatusPageResource entries.
     */
    private function buildManualData(): array
    {
        $resources = StatusPageResource::where('is_visible', true)
            ->orderBy('display_order')
            ->orderBy('group_name')
            ->get();

        $services = [];

        foreach ($resources as $spr) {
            $status = $spr->resolveStatus();

            $resourceType = match ($spr->resource_type) {
                'App\\Models\\Application' => 'application',
                'App\\Models\\Service' => 'service',
                default => 'unknown',
            };

            $services[] = [
                'name' => $spr->display_name,
                'status' => $status,
                'group' => $spr->group_name ?? 'Services',
                'resourceType' => $resourceType,
                'resourceId' => $spr->resource_id,
            ];
        }

        return ['services' => $services];
    }

    /**
     * Attach 90-day uptime snapshots to each service.
     */
    private function attachSnapshots(array $services): array
    {
        if (empty($services)) {
            return $services;
        }

        // Load all snapshots for the last 90 days in one query
        $snapshots = StatusPageDailySnapshot::lastDays(90)
            ->orderBy('snapshot_date')
            ->get()
            ->groupBy(fn ($s) => $s->resource_type.':'.$s->resource_id);

        // Build a date range for 90 days
        $dates = collect();
        for ($i = 89; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->toDateString());
        }

        foreach ($services as &$service) {
            $key = $service['resourceType'].':'.$service['resourceId'];
            $resourceSnapshots = $snapshots->get($key, collect())->keyBy(fn ($s) => $s->snapshot_date->toDateString());

            $uptimeDays = [];
            $totalUptime = 0;
            $daysWithData = 0;

            foreach ($dates as $date) {
                $snap = $resourceSnapshots->get($date);
                if ($snap) {
                    $uptimeDays[] = [
                        'date' => $date,
                        'status' => $snap->status,
                        'uptimePercent' => $snap->uptime_percent,
                    ];
                    $totalUptime += $snap->uptime_percent;
                    $daysWithData++;
                } else {
                    $uptimeDays[] = [
                        'date' => $date,
                        'status' => 'no_data',
                        'uptimePercent' => null,
                    ];
                }
            }

            $service['uptimeDays'] = $uptimeDays;
            $service['uptimePercent'] = $daysWithData > 0
                ? round($totalUptime / $daysWithData, 2)
                : null;
        }

        return $services;
    }
}
