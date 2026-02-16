<?php

namespace App\Jobs;

use App\Models\ScheduledDatabaseBackup;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackupRestoreTestManagerJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 60;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        // Get all backups with restore testing enabled
        $backups = ScheduledDatabaseBackup::query()
            ->where('enabled', true)
            ->where('restore_test_enabled', true)
            ->with(['database', 'executions' => function ($query) {
                $query->where('status', 'success')->latest()->limit(1);
            }])
            ->get();

        foreach ($backups as $backup) {
            if ($this->shouldRunRestoreTest($backup)) {
                $this->dispatchRestoreTest($backup);
            }
        }
    }

    private function shouldRunRestoreTest(ScheduledDatabaseBackup $backup): bool
    {
        $frequency = $backup->restore_test_frequency ?? 'weekly';
        $lastTest = $backup->last_restore_test_at;

        if (! $lastTest) {
            // Never tested, should run
            return true;
        }

        $now = Carbon::now();

        return match ($frequency) {
            'daily' => $lastTest->diffInHours($now) >= 24,
            'weekly' => $lastTest->diffInDays($now) >= 7,
            'monthly' => $lastTest->diffInDays($now) >= 30,
            default => $lastTest->diffInDays($now) >= 7,
        };
    }

    private function dispatchRestoreTest(ScheduledDatabaseBackup $backup): void
    {
        // Get latest successful execution
        $execution = $backup->executions()
            ->where('status', 'success')
            ->latest()
            ->first();

        if (! $execution instanceof \App\Models\ScheduledDatabaseBackupExecution) {
            Log::info('No successful backup found for restore test', [
                'backup_id' => $backup->id,
                'database' => $backup->database?->getAttribute('name'),
            ]);

            return;
        }

        Log::info('Dispatching backup restore test', [
            'backup_id' => $backup->id,
            'execution_id' => $execution->getAttribute('id'),
            'database' => $backup->database?->getAttribute('name'),
        ]);

        BackupRestoreTestJob::dispatch($backup, $execution);
    }
}
