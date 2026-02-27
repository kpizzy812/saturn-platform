<?php

namespace App\Jobs;

use App\Models\ScheduledDatabaseBackup;
use App\Notifications\Database\BackupStale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Monitors all enabled backup schedules for staleness.
 *
 * A backup is considered stale when no successful execution has been recorded
 * within 2× the backup's expected run interval. Runs daily at 09:00 to notify
 * teams during business hours.
 *
 * Stale thresholds by cron pattern:
 *  - Hourly   (0 * * * *)      → stale after 2 hours
 *  - Every 6h (0 *\/6 * * *)   → stale after 12 hours
 *  - Daily    (0 0 * * *)      → stale after 48 hours
 *  - Weekly   (0 0 * * 0)      → stale after 14 days (336 hours)
 *  - Custom   (anything else)  → stale after 48 hours
 */
class MonitorBackupStalenessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(): void
    {
        $backups = ScheduledDatabaseBackup::where('enabled', true)->with(['database', 'executions'])->get();

        foreach ($backups as $backup) {
            $this->checkBackupStaleness($backup);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MonitorBackupStalenessJob failed', ['error' => $exception->getMessage()]);
    }

    private function checkBackupStaleness(ScheduledDatabaseBackup $backup): void
    {
        try {
            $database = $backup->database;
            if (! $database) {
                return;
            }

            $staleHours = $this->getStaleThresholdHours($backup->frequency);

            $lastSuccess = $backup->executions()
                ->where('status', 'success')
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $lastSuccess) {
                // Never succeeded — only alert if backup is older than threshold
                $backupCreatedHoursAgo = $backup->created_at->diffInHours(now());
                if ($backupCreatedHoursAgo < $staleHours) {
                    // Too new to be considered stale yet
                    return;
                }
                // Treat "created_at" as pseudo last-success for alert message
                $lastSuccessAt = $backup->created_at;
            } else {
                $lastSuccessAt = $lastSuccess->created_at;
            }

            if ($lastSuccessAt->diffInHours(now()) < $staleHours) {
                // Backup is fresh — no alert needed
                return;
            }

            $team = $backup->team;
            if (! $team) {
                return;
            }

            Log::warning('Stale backup detected', [
                'backup_id' => $backup->id,
                'database' => $database->name ?? 'unknown',
                'last_success' => $lastSuccessAt->toIso8601String(),
                'stale_hours' => $staleHours,
            ]);

            $team->notify(new BackupStale($backup, $database, $lastSuccessAt, $staleHours));
        } catch (\Throwable $e) {
            Log::error('Error checking backup staleness', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine stale threshold in hours based on backup cron frequency.
     *
     * Returns 2× the expected interval so one missed run triggers an alert
     * on the second miss.
     */
    private function getStaleThresholdHours(string $frequency): int
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $frequency));

        // Hourly: "0 * * * *" or "* * * * *" (every minute = treat as hourly)
        if (preg_match('/^[\d*] \* \* \* \*$/', $normalized)) {
            return 2;
        }

        // Every N hours: "0 */N * * *"
        if (preg_match('/^\d+ \*\/(\d+) \* \* \*$/', $normalized, $matches)) {
            return (int) $matches[1] * 2;
        }

        // Every N hours fixed: "0 0,6,12,18 * * *" etc — estimate from count
        if (preg_match('/^\d+ ([\d,]+) \* \* \*$/', $normalized, $matches)) {
            $hours = explode(',', $matches[1]);
            $intervalHours = max(1, (int) (24 / count($hours)));

            return $intervalHours * 2;
        }

        // Weekly: "0 0 * * 0" or any day-of-week restriction
        if (preg_match('/^\d+ \d+ \* \* \d+$/', $normalized)) {
            return 336; // 14 days
        }

        // Monthly: "0 0 1 * *"
        if (preg_match('/^\d+ \d+ \d+ \* \*$/', $normalized)) {
            return 1488; // 62 days
        }

        // Daily or custom: default to 48 hours
        return 48;
    }
}
