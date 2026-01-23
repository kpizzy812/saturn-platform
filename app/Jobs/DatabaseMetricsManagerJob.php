<?php

namespace App\Jobs;

use App\Models\DatabaseMetric;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DatabaseMetricsManagerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // Get all functional servers with databases
        $servers = $this->getServersWithDatabases();

        foreach ($servers as $server) {
            try {
                if ($server->isFunctional() && $server->databases()->isNotEmpty()) {
                    CollectDatabaseMetricsJob::dispatch($server);
                }
            } catch (\Exception $e) {
                Log::channel('scheduled-errors')->error('Failed to dispatch CollectDatabaseMetricsJob', [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'error' => get_class($e).': '.$e->getMessage(),
                ]);
            }
        }

        // Cleanup old metrics (older than 30 days)
        $this->cleanupOldMetrics();
    }

    private function getServersWithDatabases(): Collection
    {
        $allServers = Server::where('ip', '!=', '1.2.3.4');

        if (isCloud()) {
            $servers = $allServers->whereRelation('team.subscription', 'stripe_invoice_paid', true)->get();
            $own = Team::find(0)->servers;

            return $servers->merge($own);
        } else {
            return $allServers->get();
        }
    }

    private function cleanupOldMetrics(): void
    {
        try {
            $deleted = DatabaseMetric::cleanupOldMetrics(30);
            if ($deleted > 0) {
                Log::info('Cleaned up old database metrics', ['deleted_count' => $deleted]);
            }
        } catch (\Exception $e) {
            Log::channel('scheduled-errors')->error('Failed to cleanup old database metrics', [
                'error' => get_class($e).': '.$e->getMessage(),
            ]);
        }
    }
}
