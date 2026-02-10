<?php

/**
 * Admin Metrics routes
 *
 * System performance metrics and deployment statistics.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/metrics', function () {
    $totalApplications = \App\Models\Application::count();
    $totalServices = \App\Models\Service::count();
    $totalDatabases = \App\Models\StandalonePostgresql::count()
        + \App\Models\StandaloneMysql::count()
        + \App\Models\StandaloneMariadb::count()
        + \App\Models\StandaloneMongodb::count()
        + \App\Models\StandaloneRedis::count()
        + \App\Models\StandaloneKeydb::count()
        + \App\Models\StandaloneDragonfly::count()
        + \App\Models\StandaloneClickhouse::count();

    $totalResources = $totalApplications + $totalServices + $totalDatabases;

    // Count "active" resources (applications with recent deployments)
    // Note: Application::deployments() is not an Eloquent relationship, so use whereIn with subquery
    $activeResources = \App\Models\Application::whereIn('id', function ($query) {
        $query->select('application_id')
            ->from('application_deployment_queues')
            ->where('created_at', '>=', now()->subDays(7));
    })->count();

    $totalDeployments = \App\Models\ApplicationDeploymentQueue::count();
    $successfulDeployments = \App\Models\ApplicationDeploymentQueue::where('status', 'finished')->count();
    $failedDeployments = \App\Models\ApplicationDeploymentQueue::where('status', 'failed')->count();

    $deploymentsLast24h = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subHours(24))->count();
    $deploymentsLast7d = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subDays(7))->count();

    $successRate = $totalDeployments > 0
        ? round(($successfulDeployments / $totalDeployments) * 100, 1)
        : 0;

    // Average deployment time (only finished deployments with both timestamps)
    $avgDeployTime = \App\Models\ApplicationDeploymentQueue::where('status', 'finished')
        ->whereNotNull('created_at')
        ->whereNotNull('updated_at')
        ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at))) as avg_seconds')
        ->value('avg_seconds');

    return Inertia::render('Admin/Metrics/Index', [
        'metrics' => [
            'totalResources' => $totalResources,
            'activeResources' => $activeResources,
            'totalDeployments' => $totalDeployments,
            'successfulDeployments' => $successfulDeployments,
            'failedDeployments' => $failedDeployments,
            'averageDeploymentTime' => round((float) ($avgDeployTime ?? 0)),
            'deploymentsLast24h' => $deploymentsLast24h,
            'deploymentsLast7d' => $deploymentsLast7d,
            'successRate' => $successRate,
        ],
    ]);
})->name('admin.metrics.index');
