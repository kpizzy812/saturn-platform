<?php

/**
 * Admin Queues routes
 *
 * Queue monitoring including statistics, failed job management, retry, and flush.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/queues', function () {
    // Get queue statistics
    // Note: jobs table only exists when using database queue driver
    // Saturn uses Redis queue, so we need to handle this gracefully
    $pendingJobs = 0;
    $failedJobs = 0;

    try {
        $pendingJobs = DB::table('jobs')->count();
    } catch (\Exception $e) {
        // Table doesn't exist - using Redis queue driver
    }

    try {
        $failedJobs = DB::table('failed_jobs')->count();
    } catch (\Exception $e) {
        // Table doesn't exist
    }

    $stats = [
        'pending' => $pendingJobs,
        'processing' => 0, // Reserved jobs in Horizon
        'completed' => 0, // Would need Horizon metrics
        'failed' => $failedJobs,
    ];

    // Get failed jobs
    $failedJobsList = collect();
    try {
        $failedJobsList = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(100)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'payload' => $job->payload,
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                ];
            });
    } catch (\Exception $e) {
        // Table doesn't exist
    }

    return Inertia::render('Admin/Queues/Index', [
        'stats' => $stats,
        'failedJobs' => $failedJobsList,
    ]);
})->name('admin.queues.index');

Route::post('/queues/failed/{id}/retry', function (int $id) {
    $failedJob = DB::table('failed_jobs')->where('id', $id)->first();

    if (! $failedJob) {
        return back()->with('error', 'Failed job not found');
    }

    try {
        Artisan::call('queue:retry', ['id' => [$failedJob->uuid]]);

        return back()->with('success', 'Job queued for retry');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to retry job: '.$e->getMessage());
    }
})->name('admin.queues.retry');

// Flush must be before {id} route to avoid "flush" being interpreted as an id
Route::delete('/queues/failed/flush', function () {
    Artisan::call('queue:flush');

    return back()->with('success', 'All failed jobs deleted');
})->name('admin.queues.flush');

Route::post('/queues/failed/retry-all', function () {
    try {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', 'All failed jobs queued for retry');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to retry jobs: '.$e->getMessage());
    }
})->name('admin.queues.retry-all');

Route::delete('/queues/failed/{id}', function (string $id) {
    $deleted = DB::table('failed_jobs')->where('id', (int) $id)->delete();

    if ($deleted) {
        return back()->with('success', 'Failed job deleted');
    }

    return back()->with('error', 'Failed job not found');
})->name('admin.queues.delete');
