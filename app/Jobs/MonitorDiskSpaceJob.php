<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerMetric;
use App\Notifications\Server\DiskSpaceCriticalNotification;
use App\Notifications\Server\HighDiskUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Horizon\Contracts\Silenced;

class MonitorDiskSpaceJob implements ShouldBeEncrypted, ShouldQueue, Silenced
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const WARNING_THRESHOLD = 85;

    public const CRITICAL_THRESHOLD = 95;

    public $tries = 1;

    public $timeout = 300;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $servers = Server::where('ip', '!=', '1.2.3.4')
            ->with(['settings', 'team'])
            ->whereRelation('settings', 'is_reachable', true)
            ->whereRelation('settings', 'is_usable', true)
            ->whereRelation('settings', 'force_disabled', false)
            ->get();

        foreach ($servers as $server) {
            try {
                $this->checkServer($server);
            } catch (\Throwable $e) {
                Log::channel('scheduled-errors')->error('MonitorDiskSpaceJob failed for server', [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'error' => get_class($e).': '.$e->getMessage(),
                ]);
            }
        }
    }

    private function checkServer(Server $server): void
    {
        $rawUsage = $server->storageCheck();

        if ($rawUsage === null || $rawUsage === '') {
            return;
        }

        $diskUsage = (int) $rawUsage;

        if ($diskUsage <= 0) {
            return;
        }

        ServerMetric::create([
            'server_id' => $server->id,
            'disk_usage_percent' => $diskUsage,
            'recorded_at' => now(),
        ]);

        $team = $server->team;

        if (! $team) {
            return;
        }

        if ($diskUsage >= self::CRITICAL_THRESHOLD) {
            $this->sendCriticalNotification($server, $team, $diskUsage);
        } elseif ($diskUsage >= self::WARNING_THRESHOLD) {
            $this->sendWarningNotification($server, $team, $diskUsage);
        } else {
            // Reset rate limiters when disk usage returns to normal
            RateLimiter::hit('disk-critical:'.$server->id, 600);
            RateLimiter::hit('disk-warning:'.$server->id, 600);
        }
    }

    private function sendCriticalNotification(Server $server, object $team, int $diskUsage): void
    {
        $executed = RateLimiter::attempt(
            'disk-critical:'.$server->id,
            $maxAttempts = 0,
            function () use ($server, $team, $diskUsage) {
                $team->notify(new DiskSpaceCriticalNotification($server, $diskUsage));
            },
            $decaySeconds = 3600,
        );

        if (! $executed) {
            Log::debug('Disk critical notification suppressed (rate limited)', [
                'server_id' => $server->id,
                'disk_usage' => $diskUsage,
            ]);
        }
    }

    private function sendWarningNotification(Server $server, object $team, int $diskUsage): void
    {
        $threshold = $server->settings->server_disk_usage_notification_threshold ?? self::WARNING_THRESHOLD;

        $executed = RateLimiter::attempt(
            'disk-warning:'.$server->id,
            $maxAttempts = 0,
            function () use ($server, $team, $diskUsage, $threshold) {
                $team->notify(new HighDiskUsage($server, $diskUsage, $threshold));
            },
            $decaySeconds = 3600,
        );

        if (! $executed) {
            Log::debug('Disk warning notification suppressed (rate limited)', [
                'server_id' => $server->id,
                'disk_usage' => $diskUsage,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MonitorDiskSpaceJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
