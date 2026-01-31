<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Storage Routes
|--------------------------------------------------------------------------
|
| Routes for S3 storage management, backups, and snapshots.
|
*/

// Backups and Snapshots
Route::get('/storage/backups', function () {
    $backups = \App\Models\ScheduledDatabaseBackup::ownedByCurrentTeam()
        ->with(['database', 's3', 'latest_log'])
        ->get()
        ->map(fn ($backup) => [
            'id' => $backup->id,
            'uuid' => $backup->uuid,
            'databaseName' => $backup->database?->name ?? 'Unknown',
            'databaseType' => class_basename($backup->database_type ?? ''),
            'frequency' => $backup->frequency,
            'enabled' => $backup->enabled ?? true,
            's3StorageName' => $backup->s3?->name,
            'lastStatus' => $backup->latest_log?->status ?? 'unknown',
            'lastRun' => $backup->latest_log?->created_at?->toISOString(),
            'created_at' => $backup->created_at?->toISOString(),
        ]);

    return Inertia::render('Storage/Backups', [
        'backups' => $backups,
    ]);
})->name('storage.backups');

Route::get('/storage/snapshots', function () {
    // Use ScheduledDatabaseBackupExecution as snapshot data
    $backupIds = \App\Models\ScheduledDatabaseBackup::ownedByCurrentTeam()->pluck('id');

    $snapshots = \App\Models\ScheduledDatabaseBackupExecution::whereIn('scheduled_database_backup_id', $backupIds)
        ->with('scheduledDatabaseBackup.database')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get()
        ->map(fn ($exec) => [
            'id' => $exec->id,
            'name' => $exec->filename ?? ('backup-'.$exec->id),
            'size' => $exec->size ?? '—',
            'source_volume' => $exec->scheduledDatabaseBackup?->database?->name ?? 'Unknown',
            'status' => $exec->status,
            'created_at' => $exec->created_at?->toISOString(),
        ]);

    return Inertia::render('Storage/Snapshots', [
        'snapshots' => $snapshots,
    ]);
})->name('storage.snapshots');

// S3 Storage Management
Route::get('/storage', function () {
    $storages = \App\Models\S3Storage::ownedByCurrentTeam()->get()->map(fn ($s) => [
        'id' => $s->id,
        'uuid' => $s->uuid,
        'name' => $s->name,
        'description' => $s->description,
        'endpoint' => $s->endpoint,
        'bucket' => $s->bucket,
        'region' => $s->region,
        'is_usable' => $s->is_usable,
        'created_at' => $s->created_at?->toISOString(),
        'updated_at' => $s->updated_at?->toISOString(),
    ]);

    return Inertia::render('Storage/Index', [
        'storages' => $storages,
    ]);
})->name('storage.index');

Route::get('/storage/create', fn () => Inertia::render('Storage/Create'))->name('storage.create');

Route::post('/storage', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'key' => 'required|string',
        'secret' => 'required|string',
        'bucket' => 'required|string',
        'region' => 'required|string',
        'endpoint' => 'nullable|string',
        'path' => 'nullable|string',
    ]);

    $team = auth()->user()->currentTeam();

    \App\Models\S3Storage::create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'key' => $validated['key'],
        'secret' => $validated['secret'],
        'bucket' => $validated['bucket'],
        'region' => $validated['region'],
        'endpoint' => $validated['endpoint'] ?? null,
        'team_id' => $team->id,
    ]);

    return redirect()->route('storage.index')->with('success', 'Storage created successfully');
})->name('storage.store');

Route::post('/storage/test-connection', function (Request $request) {
    $validated = $request->validate([
        'key' => 'required|string',
        'secret' => 'required|string',
        'bucket' => 'required|string',
        'region' => 'required|string',
        'endpoint' => 'nullable|string',
    ]);

    $team = auth()->user()->currentTeam();

    // Create a temporary S3Storage to test
    $storage = new \App\Models\S3Storage([
        'key' => $validated['key'],
        'secret' => $validated['secret'],
        'bucket' => $validated['bucket'],
        'region' => $validated['region'],
        'endpoint' => $validated['endpoint'] ?? null,
        'team_id' => $team->id,
    ]);

    try {
        $result = $storage->testConnection();

        return response()->json([
            'success' => (bool) $result,
            'message' => $result ? 'Connection successful! Storage is ready to use.' : 'Connection failed. Please check your credentials and try again.',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Connection failed: '.$e->getMessage(),
        ]);
    }
})->name('storage.test-connection');

Route::get('/storage/{uuid}', fn (string $uuid) => Inertia::render('Storage/Show', ['uuid' => $uuid]))->name('storage.show');

Route::get('/storage/{uuid}/settings', fn (string $uuid) => Inertia::render('Storage/Settings', ['uuid' => $uuid]))->name('storage.settings');

Route::get('/storage/{uuid}/backups', fn (string $uuid) => Inertia::render('Storage/Backups', ['uuid' => $uuid]))->name('storage.backups.show');

Route::get('/storage/{uuid}/snapshots', fn (string $uuid) => Inertia::render('Storage/Snapshots', ['uuid' => $uuid]))->name('storage.snapshots.show');
