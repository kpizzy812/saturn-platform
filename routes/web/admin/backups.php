<?php

/**
 * Admin Backups routes
 *
 * Backup management including listing, viewing, running, restoring, and deletion.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/backups', function () {
    $search = request()->query('search', '');
    $type = request()->query('type', '');
    $page = max(1, (int) request()->query('page', 1));
    $perPage = 50;

    // Load all backups with relations, then filter in PHP
    // (polymorphic `database` relation prevents SQL-level filtering by name/type)
    $allBackups = \App\Models\ScheduledDatabaseBackup::with(['database', 'latest_log', 's3'])->get();

    $mapped = $allBackups->map(function ($backup) {
        $database = $backup->database;

        return [
            'id' => $backup->id,
            'uuid' => $backup->uuid,
            'database_id' => $database?->id,
            'database_uuid' => $database?->uuid,
            'database_name' => $database?->name ?? 'Unknown',
            'database_type' => $database ? class_basename($database) : 'Unknown',
            'team_id' => $backup->team_id,
            'team_name' => $backup->team?->name ?? 'Unknown',
            'frequency' => $backup->frequency,
            'enabled' => $backup->enabled,
            'save_s3' => $backup->save_s3,
            's3_storage_name' => $backup->s3?->name,
            'verify_after_backup' => $backup->verify_after_backup ?? true,
            'restore_test_enabled' => $backup->restore_test_enabled ?? false,
            'restore_test_frequency' => $backup->restore_test_frequency ?? 'weekly',
            'last_restore_test_at' => $backup->last_restore_test_at,
            'last_execution' => $backup->latest_log ? [
                'id' => $backup->latest_log->id,
                'uuid' => $backup->latest_log->uuid ?? '',
                'status' => $backup->latest_log->status,
                'size' => $backup->latest_log->size,
                'filename' => $backup->latest_log->filename,
                'message' => $backup->latest_log->message,
                'verification_status' => $backup->latest_log->verification_status,
                'restore_test_status' => $backup->latest_log->restore_test_status,
                's3_integrity_status' => $backup->latest_log->s3_integrity_status,
                'created_at' => $backup->latest_log->created_at,
            ] : null,
            'executions_count' => $backup->executions()->count(),
            'created_at' => $backup->created_at,
        ];
    });

    // Apply search and type filters BEFORE pagination
    if ($search || $type) {
        $mapped = $mapped->filter(function ($item) use ($search, $type) {
            $matchesSearch = ! $search
                || str_contains(strtolower($item['database_name']), strtolower($search))
                || str_contains(strtolower($item['team_name']), strtolower($search));
            $matchesType = ! $type || strtolower($item['database_type']) === strtolower($type);

            return $matchesSearch && $matchesType;
        });
    }

    // Manual pagination after filtering
    $sorted = $mapped->values();
    $total = $sorted->count();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $paginatedItems = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

    $backups = [
        'data' => $paginatedItems,
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'last_page' => $lastPage,
    ];

    // Calculate stats from DB queries (not from paginated collection)
    $backupModel = \App\Models\ScheduledDatabaseBackup::query();
    $recentExecutions = \App\Models\ScheduledDatabaseBackupExecution::where('created_at', '>=', now()->subDay());
    $allExecutions = \App\Models\ScheduledDatabaseBackupExecution::query();

    // Calculate total storage used
    $totalStorageLocal = (int) \App\Models\ScheduledDatabaseBackupExecution::where('local_storage_deleted', false)
        ->whereNotNull('size')
        ->where('size', '!=', '')
        ->selectRaw('COALESCE(SUM(CAST(size AS BIGINT)), 0) as total')
        ->value('total');
    $totalStorageS3 = (int) \App\Models\ScheduledDatabaseBackupExecution::where('s3_uploaded', true)
        ->where('s3_storage_deleted', false)
        ->whereNotNull('s3_file_size')
        ->sum('s3_file_size');

    $s3CostPerGBMonth = 0.023;
    $estimatedMonthlyCost = $totalStorageS3 > 0
        ? ($totalStorageS3 / (1024 * 1024 * 1024)) * $s3CostPerGBMonth
        : 0;

    $stats = [
        'total' => (clone $backupModel)->count(),
        'enabled' => (clone $backupModel)->where('enabled', true)->count(),
        'with_s3' => (clone $backupModel)->where('save_s3', true)->count(),
        'failed_last_24h' => (clone $recentExecutions)->where('status', 'failed')->count(),
        'verified_last_24h' => (clone $recentExecutions)->where('verification_status', 'verified')->count(),
        'verification_failed_last_24h' => (clone $recentExecutions)->where('verification_status', 'failed')->count(),
        'restore_test_enabled_count' => (clone $backupModel)->where('restore_test_enabled', true)->count(),
        'restore_tests_passed' => (clone $allExecutions)->where('restore_test_status', 'success')->count(),
        'restore_tests_failed' => (clone $allExecutions)->where('restore_test_status', 'failed')->count(),
        'total_storage_local' => $totalStorageLocal,
        'total_storage_s3' => $totalStorageS3,
        'estimated_monthly_cost' => round($estimatedMonthlyCost, 2),
    ];

    // Get unique database types for filter buttons
    $databaseTypes = \App\Models\ScheduledDatabaseBackup::with('database')
        ->get()
        ->map(fn ($b) => $b->database ? class_basename($b->database) : null)
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    return Inertia::render('Admin/Backups/Index', [
        'backups' => $backups,
        'stats' => $stats,
        'databaseTypes' => $databaseTypes,
        'filters' => [
            'search' => $search,
            'type' => $type,
        ],
    ]);
})->name('admin.backups.index');

Route::get('/backups/{uuid}', function (string $uuid) {
    $backup = \App\Models\ScheduledDatabaseBackup::with(['database', 'executions', 's3', 'team'])
        ->where('uuid', $uuid)
        ->firstOrFail();

    $database = $backup->database;
    $server = $backup->server();

    return Inertia::render('Admin/Backups/Show', [
        'backup' => [
            'id' => $backup->id,
            'uuid' => $backup->uuid,
            'database_id' => $database?->id,
            'database_uuid' => $database?->uuid,
            'database_name' => $database?->name ?? 'Unknown',
            'database_type' => $database ? class_basename($database) : 'Unknown',
            'team_id' => $backup->team_id,
            'team_name' => $backup->team?->name ?? 'Unknown',
            'server_name' => $server?->name ?? 'Unknown',
            'frequency' => $backup->frequency,
            'enabled' => $backup->enabled,
            'save_s3' => $backup->save_s3,
            's3_storage_name' => $backup->s3?->name,
            'number_of_backups_locally' => $backup->number_of_backups_locally ?? 7,
            'verify_after_backup' => $backup->verify_after_backup ?? true,
            'restore_test_enabled' => $backup->restore_test_enabled ?? false,
            'restore_test_frequency' => $backup->restore_test_frequency ?? 'weekly',
            'executions' => $backup->executions()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(function ($exec) {
                    return [
                        'id' => $exec->id,
                        'uuid' => $exec->uuid ?? '',
                        'status' => $exec->status,
                        'size' => $exec->size,
                        'filename' => $exec->filename,
                        'message' => $exec->message,
                        's3_uploaded' => $exec->s3_uploaded ?? false,
                        'local_storage_deleted' => $exec->local_storage_deleted ?? false,
                        'verification_status' => $exec->verification_status,
                        'checksum' => $exec->checksum,
                        'verified_at' => $exec->verified_at,
                        'restore_test_status' => $exec->restore_test_status,
                        'restore_test_at' => $exec->restore_test_at,
                        'restore_test_duration_seconds' => $exec->restore_test_duration_seconds,
                        's3_integrity_status' => $exec->s3_integrity_status,
                        's3_file_size' => $exec->s3_file_size,
                        'created_at' => $exec->created_at,
                        'finished_at' => $exec->finished_at,
                    ];
                }),
            'created_at' => $backup->created_at,
            'updated_at' => $backup->updated_at,
        ],
    ]);
})->name('admin.backups.show');

Route::post('/backups/{uuid}/run', function (string $uuid) {
    $backup = \App\Models\ScheduledDatabaseBackup::where('uuid', $uuid)->firstOrFail();

    try {
        \App\Jobs\DatabaseBackupJob::dispatch($backup);

        return back()->with('success', 'Backup job queued successfully');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to queue backup: '.$e->getMessage());
    }
})->name('admin.backups.run');

Route::post('/backups/executions/{id}/restore', function (int $id) {
    $execution = \App\Models\ScheduledDatabaseBackupExecution::findOrFail($id);
    $backup = $execution->scheduledDatabaseBackup;

    try {
        \App\Jobs\DatabaseRestoreJob::dispatch($backup, $execution);

        return back()->with('success', 'Restore job queued successfully');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to queue restore: '.$e->getMessage());
    }
})->name('admin.backups.restore');

Route::delete('/backups/executions/{id}', function (int $id) {
    $execution = \App\Models\ScheduledDatabaseBackupExecution::findOrFail($id);
    $execution->delete();

    return back()->with('success', 'Backup execution deleted');
})->name('admin.backups.executions.delete');
