<?php

namespace App\Jobs;

use App\Models\Team;
use App\Notifications\Internal\GeneralNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monitors the failed_jobs table and alerts all teams when the count exceeds a threshold.
 *
 * Runs hourly. Uses a cache lock to prevent alert spam — at most one alert
 * per ALERT_COOLDOWN_HOURS regardless of how many consecutive checks fail.
 *
 * Failed jobs accumulate when workers crash, SSH errors cause cascading failures,
 * or bugs are deployed. An unmonitored queue can silently lose critical work.
 */
class MonitorFailedJobsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    /** Minimum jobs in failed_jobs table before alerting. */
    private const ALERT_THRESHOLD = 5;

    /** Hours between repeated alerts for the same condition. */
    private const ALERT_COOLDOWN_HOURS = 6;

    private const CACHE_KEY = 'monitor_failed_jobs_last_alert';

    public function handle(): void
    {
        $failedCount = DB::table('failed_jobs')->count();

        if ($failedCount < self::ALERT_THRESHOLD) {
            return;
        }

        // Check cooldown — avoid alerting more than once per 6 hours.
        // Use $lastAlert->diffInHours(now()) to get positive hours-since-alert.
        $lastAlert = Cache::get(self::CACHE_KEY);
        if ($lastAlert && $lastAlert->diffInHours(now()) < self::ALERT_COOLDOWN_HOURS) {
            Log::debug("Failed jobs alert suppressed by cooldown ({$failedCount} failed jobs)");

            return;
        }

        // Sample most recent failures for context
        $recentFailures = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(3)
            ->pluck('exception')
            ->map(fn ($e) => str($e)->before("\n")->limit(120)->value())
            ->filter()
            ->implode("\n• ");

        $message = "⚠️ *Failed Jobs Alert*: {$failedCount} jobs have failed and are accumulating in the queue.\n\n"
            .'Failure threshold: '.self::ALERT_THRESHOLD." jobs\n\n"
            .($recentFailures ? "Recent errors:\n• {$recentFailures}\n\n" : '')
            .'Run `php artisan horizon:clear` to retry or inspect failed jobs in Laravel Horizon.';

        Log::warning("Failed jobs threshold exceeded: {$failedCount} jobs", [
            'count' => $failedCount,
            'threshold' => self::ALERT_THRESHOLD,
        ]);

        // Notify all teams that have notification channels configured
        Team::each(function (Team $team) use ($message) {
            try {
                $team->notify(new GeneralNotification($message));
            } catch (\Throwable $e) {
                Log::error("Could not send failed jobs alert to team {$team->id}: {$e->getMessage()}");
            }
        });

        Cache::put(self::CACHE_KEY, now(), now()->addHours(self::ALERT_COOLDOWN_HOURS));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MonitorFailedJobsJob itself failed', ['error' => $exception->getMessage()]);
    }
}
