<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\ServerHealthCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertEvaluationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 60;

    /**
     * Metric name to ServerHealthCheck column mapping
     */
    private const METRIC_MAP = [
        'cpu' => 'cpu_usage_percent',
        'memory' => 'memory_usage_percent',
        'disk' => 'disk_usage_percent',
        'response_time' => 'response_time_ms',
    ];

    public function handle(): void
    {
        $alerts = Alert::where('enabled', true)->with('team.servers')->get();

        foreach ($alerts as $alert) {
            $this->evaluateAlert($alert);
        }
    }

    private function evaluateAlert(Alert $alert): void
    {
        /** @var \App\Models\Team|null $team */
        $team = $alert->team;
        if (! $team) {
            return;
        }

        $serverIds = $team->servers->pluck('id');
        if ($serverIds->isEmpty()) {
            return;
        }

        $column = self::METRIC_MAP[$alert->metric] ?? null;
        if (! $column) {
            return;
        }

        $duration = max(1, (int) $alert->duration);
        $since = now()->subMinutes($duration);

        $avgValue = ServerHealthCheck::whereIn('server_id', $serverIds)
            ->where('created_at', '>=', $since)
            ->whereNotNull($column)
            ->avg($column);

        if ($avgValue === null) {
            return;
        }

        $avgValue = round((float) $avgValue, 2);
        $threshold = (float) $alert->threshold;
        $condition = $alert->condition ?? '>';
        $isTriggered = $this->checkCondition($avgValue, $condition, $threshold);

        $cacheKey = "alert-triggered-{$alert->id}";

        if ($isTriggered) {
            // Deduplication: don't fire again if already triggered within duration window
            if (Cache::has($cacheKey)) {
                return;
            }

            // Create alert history record
            $alert->histories()->create([
                'triggered_at' => now(),
                'value' => $avgValue,
                'status' => 'triggered',
            ]);

            $alert->increment('triggered_count');
            $alert->update(['last_triggered_at' => now()]);

            // Cache for duration minutes to prevent duplicate alerts
            Cache::put($cacheKey, true, now()->addMinutes($duration));

            Log::info("Alert triggered: {$alert->name} ({$alert->metric} {$condition} {$threshold}, actual: {$avgValue})");
        } else {
            // If previously triggered, mark as resolved
            if (Cache::has($cacheKey)) {
                $lastHistory = $alert->histories()
                    ->where('status', 'triggered')
                    ->whereNull('resolved_at')
                    ->latest('triggered_at')
                    ->first();

                if ($lastHistory) {
                    $lastHistory->update([
                        'resolved_at' => now(),
                        'status' => 'resolved',
                    ]);
                }

                Cache::forget($cacheKey);

                Log::info("Alert resolved: {$alert->name} ({$alert->metric} back to normal: {$avgValue})");
            }
        }
    }

    private function checkCondition(float $value, string $condition, float $threshold): bool
    {
        return match ($condition) {
            '>' => $value > $threshold,
            '<' => $value < $threshold,
            '=' => abs($value - $threshold) < 0.01,
            default => false,
        };
    }
}
