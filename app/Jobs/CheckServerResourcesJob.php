<?php

namespace App\Jobs;

use App\Models\AutoProvisioningEvent;
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

    // Track current metrics for auto-provisioning trigger
    private ?float $currentCpuUsage = null;

    private ?float $currentMemoryUsage = null;

    private ?int $currentDiskUsage = null;

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
            $cpuCritical = $this->checkCpuUsage($settings);

            // Check Memory usage
            $memoryCritical = $this->checkMemoryUsage($settings);

            // Check Disk usage (with instance settings thresholds)
            $diskCritical = $this->checkDiskUsage($settings);

            // Trigger auto-provisioning if critical thresholds exceeded
            if ($cpuCritical || $memoryCritical || $diskCritical) {
                $reason = $cpuCritical ? 'cpu_critical' : ($memoryCritical ? 'memory_critical' : 'disk_critical');
                $this->triggerAutoProvisioning($settings, $reason);
            }

        } catch (\Throwable $e) {
            ray($e->getMessage());
        }
    }

    /**
     * Check CPU usage and send notifications if thresholds exceeded.
     *
     * @return bool True if critical threshold exceeded
     */
    private function checkCpuUsage(InstanceSettings $settings): bool
    {
        if (! $this->server->isMetricsEnabled()) {
            return false;
        }

        try {
            $cpuMetrics = $this->server->getCpuMetrics(1);
            if (empty($cpuMetrics)) {
                return false;
            }

            // Get the latest CPU value
            $latestCpu = collect($cpuMetrics)->last();
            $cpuUsage = $latestCpu[1] ?? 0;
            $this->currentCpuUsage = $cpuUsage;

            $cacheKey = "server-cpu-alert-{$this->server->uuid}";
            $isCritical = false;

            // Check critical threshold first
            if ($cpuUsage >= $settings->resource_critical_cpu_threshold) {
                $isCritical = true;
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

            return $isCritical;
        } catch (\Throwable $e) {
            // Sentinel might not be running
            return false;
        }
    }

    /**
     * Check Memory usage and send notifications if thresholds exceeded.
     *
     * @return bool True if critical threshold exceeded
     */
    private function checkMemoryUsage(InstanceSettings $settings): bool
    {
        if (! $this->server->isMetricsEnabled()) {
            return false;
        }

        try {
            $memoryMetrics = $this->server->getMemoryMetrics(1);
            if (empty($memoryMetrics)) {
                return false;
            }

            // Get the latest memory value
            $latestMemory = collect($memoryMetrics)->last();
            $memoryUsage = $latestMemory[1] ?? 0;
            $this->currentMemoryUsage = $memoryUsage;

            $cacheKey = "server-memory-alert-{$this->server->uuid}";
            $isCritical = false;

            // Check critical threshold first
            if ($memoryUsage >= $settings->resource_critical_memory_threshold) {
                $isCritical = true;
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

            return $isCritical;
        } catch (\Throwable $e) {
            // Sentinel might not be running
            return false;
        }
    }

    /**
     * Check Disk usage and send notifications if thresholds exceeded.
     *
     * @return bool True if critical threshold exceeded
     */
    private function checkDiskUsage(InstanceSettings $settings): bool
    {
        try {
            $diskUsage = $this->server->getDiskUsage();
            if (empty($diskUsage) || ! is_numeric($diskUsage)) {
                return false;
            }

            $diskUsage = (int) $diskUsage;
            $this->currentDiskUsage = $diskUsage;
            $cacheKey = "server-disk-alert-{$this->server->uuid}";
            $isCritical = false;

            // Check critical threshold first
            if ($diskUsage >= $settings->resource_critical_disk_threshold) {
                $isCritical = true;
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

            return $isCritical;
        } catch (\Throwable $e) {
            // SSH might not be available
            return false;
        }
    }

    /**
     * Trigger auto-provisioning if enabled and conditions are met.
     */
    private function triggerAutoProvisioning(InstanceSettings $settings, string $triggerReason): void
    {
        // Check if auto-provisioning is enabled
        if (! $settings->auto_provision_enabled) {
            return;
        }

        // Check cooldown to prevent rapid provisioning
        $cooldownKey = "auto-provision-triggered-{$this->server->uuid}";
        if (Cache::has($cooldownKey)) {
            return;
        }

        // Check daily limit
        $provisionedToday = AutoProvisioningEvent::countProvisionedToday();
        if ($provisionedToday >= $settings->auto_provision_max_servers_per_day) {
            ray('Daily auto-provisioning limit reached, skipping trigger');

            return;
        }

        // Check if there's already an active provisioning
        if (AutoProvisioningEvent::hasActiveProvisioning()) {
            return;
        }

        // Collect current metrics
        $triggerMetrics = [];
        if ($this->currentCpuUsage !== null) {
            $triggerMetrics['cpu'] = $this->currentCpuUsage;
        }
        if ($this->currentMemoryUsage !== null) {
            $triggerMetrics['memory'] = $this->currentMemoryUsage;
        }
        if ($this->currentDiskUsage !== null) {
            $triggerMetrics['disk'] = $this->currentDiskUsage;
        }

        // Set cooldown to prevent rapid triggering (6 hours)
        Cache::put($cooldownKey, true, now()->addHours(6));

        // Dispatch auto-provisioning job
        AutoProvisionServerJob::dispatch(
            $this->server,
            $triggerReason,
            $triggerMetrics
        );

        ray('Auto-provisioning triggered', [
            'server' => $this->server->name,
            'reason' => $triggerReason,
            'metrics' => $triggerMetrics,
        ]);
    }
}
