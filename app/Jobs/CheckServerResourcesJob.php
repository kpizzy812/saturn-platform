<?php

namespace App\Jobs;

use App\Models\InstanceSettings;
use App\Models\Server;
use App\Notifications\Server\HighCpuUsage;
use App\Notifications\Server\HighDiskUsage;
use App\Notifications\Server\HighMemoryUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CheckServerResourcesJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('check-server-resources-'.$this->server->uuid))->expireAfter(120)->dontRelease()];
    }

    public function __construct(public Server $server) {}

    public function handle(): void
    {
        try {
            $settings = InstanceSettings::get();

            // Check if resource monitoring is enabled
            if (! $settings->resource_monitoring_enabled) {
                return;
            }

            // Skip if server is not reachable
            if ($this->server->serverStatus() === false) {
                return;
            }

            // Check CPU usage
            $this->checkCpuUsage($settings);

            // Check Memory usage
            $this->checkMemoryUsage($settings);

            // Check Disk usage (with instance settings thresholds)
            $this->checkDiskUsage($settings);

        } catch (\Throwable $e) {
            ray($e->getMessage());
        }
    }

    /**
     * Check CPU usage and send notifications if thresholds exceeded.
     */
    private function checkCpuUsage(InstanceSettings $settings): void
    {
        if (! $this->server->isMetricsEnabled()) {
            return;
        }

        try {
            $cpuMetrics = $this->server->getCpuMetrics(1);
            if (empty($cpuMetrics)) {
                return;
            }

            // Get the latest CPU value
            $latestCpu = collect($cpuMetrics)->last();
            $cpuUsage = $latestCpu[1] ?? 0;

            $cacheKey = "server-cpu-alert-{$this->server->uuid}";

            // Check critical threshold first
            if ($cpuUsage >= $settings->resource_critical_cpu_threshold) {
                if (! Cache::has($cacheKey.'-critical')) {
                    $this->server->team?->notify(new HighCpuUsage(
                        $this->server,
                        $cpuUsage,
                        $settings->resource_critical_cpu_threshold,
                        'critical'
                    ));
                    // Prevent spam - cache for 15 minutes
                    Cache::put($cacheKey.'-critical', true, now()->addMinutes(15));
                }
            }
            // Check warning threshold
            elseif ($cpuUsage >= $settings->resource_warning_cpu_threshold) {
                if (! Cache::has($cacheKey.'-warning')) {
                    $this->server->team?->notify(new HighCpuUsage(
                        $this->server,
                        $cpuUsage,
                        $settings->resource_warning_cpu_threshold,
                        'warning'
                    ));
                    // Prevent spam - cache for 30 minutes
                    Cache::put($cacheKey.'-warning', true, now()->addMinutes(30));
                }
            }
        } catch (\Throwable $e) {
            // Sentinel might not be running
        }
    }

    /**
     * Check Memory usage and send notifications if thresholds exceeded.
     */
    private function checkMemoryUsage(InstanceSettings $settings): void
    {
        if (! $this->server->isMetricsEnabled()) {
            return;
        }

        try {
            $memoryMetrics = $this->server->getMemoryMetrics(1);
            if (empty($memoryMetrics)) {
                return;
            }

            // Get the latest memory value
            $latestMemory = collect($memoryMetrics)->last();
            $memoryUsage = $latestMemory[1] ?? 0;

            $cacheKey = "server-memory-alert-{$this->server->uuid}";

            // Check critical threshold first
            if ($memoryUsage >= $settings->resource_critical_memory_threshold) {
                if (! Cache::has($cacheKey.'-critical')) {
                    $this->server->team?->notify(new HighMemoryUsage(
                        $this->server,
                        $memoryUsage,
                        $settings->resource_critical_memory_threshold,
                        'critical'
                    ));
                    // Prevent spam - cache for 15 minutes
                    Cache::put($cacheKey.'-critical', true, now()->addMinutes(15));
                }
            }
            // Check warning threshold
            elseif ($memoryUsage >= $settings->resource_warning_memory_threshold) {
                if (! Cache::has($cacheKey.'-warning')) {
                    $this->server->team?->notify(new HighMemoryUsage(
                        $this->server,
                        $memoryUsage,
                        $settings->resource_warning_memory_threshold,
                        'warning'
                    ));
                    // Prevent spam - cache for 30 minutes
                    Cache::put($cacheKey.'-warning', true, now()->addMinutes(30));
                }
            }
        } catch (\Throwable $e) {
            // Sentinel might not be running
        }
    }

    /**
     * Check Disk usage and send notifications if thresholds exceeded.
     */
    private function checkDiskUsage(InstanceSettings $settings): void
    {
        try {
            $diskUsage = $this->server->getDiskUsage();
            if (empty($diskUsage) || ! is_numeric($diskUsage)) {
                return;
            }

            $diskUsage = (int) $diskUsage;
            $cacheKey = "server-disk-alert-{$this->server->uuid}";

            // Check critical threshold first
            if ($diskUsage >= $settings->resource_critical_disk_threshold) {
                if (! Cache::has($cacheKey.'-critical')) {
                    $this->server->team?->notify(new HighDiskUsage(
                        $this->server,
                        $diskUsage,
                        $settings->resource_critical_disk_threshold
                    ));
                    // Prevent spam - cache for 15 minutes
                    Cache::put($cacheKey.'-critical', true, now()->addMinutes(15));
                }
            }
            // Check warning threshold
            elseif ($diskUsage >= $settings->resource_warning_disk_threshold) {
                if (! Cache::has($cacheKey.'-warning')) {
                    $this->server->team?->notify(new HighDiskUsage(
                        $this->server,
                        $diskUsage,
                        $settings->resource_warning_disk_threshold
                    ));
                    // Prevent spam - cache for 30 minutes
                    Cache::put($cacheKey.'-warning', true, now()->addMinutes(30));
                }
            }
        } catch (\Throwable $e) {
            // SSH might not be available
        }
    }
}
