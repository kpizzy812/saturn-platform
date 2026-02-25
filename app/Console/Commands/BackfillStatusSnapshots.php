<?php

namespace App\Console\Commands;

use App\Models\StatusPageDailySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStatusSnapshots extends Command
{
    protected $signature = 'status-page:backfill {--days=30 : Number of days to backfill}';

    protected $description = 'Backfill status page snapshots from existing server_health_checks data';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Backfilling status page snapshots for the last {$days} days...");

        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->subDay()->toDateString();

        $aggregates = DB::table('server_health_checks')
            ->select(
                'server_id',
                DB::raw('DATE(checked_at) as check_date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as healthy"),
                DB::raw("SUM(CASE WHEN status = 'degraded' THEN 1 ELSE 0 END) as degraded"),
                DB::raw("SUM(CASE WHEN status IN ('down', 'unreachable') THEN 1 ELSE 0 END) as down"),
            )
            ->whereDate('checked_at', '>=', $startDate)
            ->whereDate('checked_at', '<=', $endDate)
            ->groupBy('server_id', DB::raw('DATE(checked_at)'))
            ->orderBy('server_id')
            ->orderBy('check_date')
            ->get();

        $count = 0;

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
                    'snapshot_date' => $agg->check_date,
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

            $count++;
        }

        $this->info("Created/updated {$count} snapshot entries.");

        return self::SUCCESS;
    }
}
