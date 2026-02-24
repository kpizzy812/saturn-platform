<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Server;
use App\Models\Service;
use App\Models\StatusPageDailySnapshot;
use App\Models\StatusPageResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StatusPageSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function handle(): void
    {
        $date = now()->subDay()->toDateString();

        $this->snapshotServers($date);
        $this->snapshotApplications($date);
        $this->snapshotServices($date);
    }

    /**
     * Aggregate server_health_checks for each server for the given date.
     */
    private function snapshotServers(string $date): void
    {
        $aggregates = DB::table('server_health_checks')
            ->select(
                'server_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as healthy"),
                DB::raw("SUM(CASE WHEN status = 'degraded' THEN 1 ELSE 0 END) as degraded"),
                DB::raw("SUM(CASE WHEN status IN ('down', 'unreachable') THEN 1 ELSE 0 END) as down"),
            )
            ->whereDate('checked_at', $date)
            ->groupBy('server_id')
            ->get();

        foreach ($aggregates as $agg) {
            $total = (int) $agg->total;
            $healthy = (int) $agg->healthy;
            $degraded = (int) $agg->degraded;
            $down = (int) $agg->down;

            $uptimePercent = $total > 0
                ? round(($healthy + $degraded) / $total * 100, 2)
                : 0;

            $worstStatus = 'operational';
            if ($down > 0) {
                $worstStatus = 'outage';
            } elseif ($degraded > 0) {
                $worstStatus = 'degraded';
            }

            StatusPageDailySnapshot::updateOrCreate(
                [
                    'resource_type' => 'server',
                    'resource_id' => $agg->server_id,
                    'snapshot_date' => $date,
                ],
                [
                    'status' => $worstStatus,
                    'uptime_percent' => $uptimePercent,
                    'total_checks' => $total,
                    'healthy_checks' => $healthy,
                    'degraded_checks' => $degraded,
                    'down_checks' => $down,
                ]
            );
        }
    }

    /**
     * Snapshot current status for all applications.
     */
    private function snapshotApplications(string $date): void
    {
        Application::select('id', 'status')->cursor()->each(function ($app) use ($date) {
            $normalized = StatusPageResource::normalizeStatus($app->status ?? 'unknown');
            $this->upsertResourceSnapshot('application', $app->id, $date, $normalized);
        });
    }

    /**
     * Snapshot current status for all services.
     */
    private function snapshotServices(string $date): void
    {
        Service::select('id', 'name', 'environment_id')->cursor()->each(function ($service) use ($date) {
            $normalized = StatusPageResource::normalizeStatus($service->status ?? 'unknown');
            $this->upsertResourceSnapshot('service', $service->id, $date, $normalized);
        });
    }

    /**
     * Upsert a snapshot for a non-server resource (no historical checks available).
     */
    private function upsertResourceSnapshot(string $type, int $id, string $date, string $normalizedStatus): void
    {
        $isUp = in_array($normalizedStatus, ['operational', 'degraded']);

        StatusPageDailySnapshot::updateOrCreate(
            [
                'resource_type' => $type,
                'resource_id' => $id,
                'snapshot_date' => $date,
            ],
            [
                'status' => $normalizedStatus === 'operational' ? 'operational'
                    : ($normalizedStatus === 'degraded' ? 'degraded'
                        : ($normalizedStatus === 'maintenance' ? 'operational'
                            : 'outage')),
                'uptime_percent' => $isUp ? 100 : 0,
                'total_checks' => 1,
                'healthy_checks' => $isUp ? 1 : 0,
                'degraded_checks' => $normalizedStatus === 'degraded' ? 1 : 0,
                'down_checks' => ! $isUp && $normalizedStatus !== 'maintenance' ? 1 : 0,
            ]
        );
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error('StatusPageSnapshotJob failed: '.$exception->getMessage());
    }
}
