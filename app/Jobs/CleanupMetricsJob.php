<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupMetricsJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    // Retention period in days. Old records beyond this threshold are deleted.
    private int $retentionDays = 30;

    public function __construct() {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('cleanup-metrics'))->expireAfter(120)->dontRelease()];
    }

    public function handle(): void
    {
        try {
            $cutoff = now()->subDays($this->retentionDays);

            $dbMetricsDeleted = DB::table('database_metrics')
                ->where('recorded_at', '<', $cutoff)
                ->delete();

            $healthChecksDeleted = DB::table('server_health_checks')
                ->where('checked_at', '<', $cutoff)
                ->delete();

            if ($dbMetricsDeleted > 0 || $healthChecksDeleted > 0) {
                Log::info('CleanupMetricsJob: pruned old metrics', [
                    'database_metrics' => $dbMetricsDeleted,
                    'server_health_checks' => $healthChecksDeleted,
                    'retention_days' => $this->retentionDays,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CleanupMetricsJob failed: '.$e->getMessage());
        }
    }
}
