<?php

namespace App\Jobs;

use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ResourceMonitoringManagerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $settings = InstanceSettings::get();

        // Check if resource monitoring is enabled
        if (! $settings->resource_monitoring_enabled) {
            return;
        }

        // Get all servers to monitor
        $servers = $this->getServers();

        // Dispatch CheckServerResourcesJob for each server
        foreach ($servers as $server) {
            // Skip localhost/placeholder servers
            if ($server->ip === '1.2.3.4') {
                continue;
            }

            // Skip build servers
            if ($server->isBuildServer()) {
                continue;
            }

            CheckServerResourcesJob::dispatch($server);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ResourceMonitoringManagerJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    private function getServers(): Collection
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
}
