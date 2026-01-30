<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SuperAdmin Routes
|--------------------------------------------------------------------------
|
| Routes for Super Admin panel - accessible only by root team admins/owners.
| All routes require authentication and super admin privileges.
|
*/

Route::middleware(['auth', 'verified', 'is.superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', fn () => Inertia::render('Admin/Index'))->name('dashboard');
    Route::get('/users', fn () => Inertia::render('Admin/Users/Index'))->name('users');
    Route::get('/projects', fn () => Inertia::render('Admin/Projects/Index'))->name('projects');
    Route::get('/servers', fn () => Inertia::render('Admin/Servers/Index'))->name('servers');
    Route::get('/audit', fn () => Inertia::render('Admin/Logs/Index'))->name('audit');

    Route::get('/metrics', function () {
        // Calculate system metrics
        $totalDeployments = \App\Models\ApplicationDeploymentQueue::count();
        $successfulDeployments = \App\Models\ApplicationDeploymentQueue::where('status', 'finished')->count();
        $failedDeployments = \App\Models\ApplicationDeploymentQueue::where('status', 'failed')->count();

        // Calculate success rate
        $successRate = $totalDeployments > 0 ? ($successfulDeployments / $totalDeployments) * 100 : 0;

        // Count deployments in last 24h
        $deploymentsLast24h = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subDay())->count();

        // Count deployments in last 7 days
        $deploymentsLast7d = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subDays(7))->count();

        // Calculate average deployment time (finished deployments only)
        $avgDeploymentTime = \App\Models\ApplicationDeploymentQueue::where('status', 'finished')
            ->whereNotNull('created_at')
            ->whereNotNull('updated_at')
            ->get()
            ->avg(function ($deployment) {
                return $deployment->updated_at->diffInSeconds($deployment->created_at);
            }) ?? 0;

        // Count total resources (applications + databases + services)
        $totalResources = \App\Models\Application::count()
            + \App\Models\StandalonePostgresql::count()
            + \App\Models\StandaloneMysql::count()
            + \App\Models\StandaloneMariadb::count()
            + \App\Models\StandaloneMongodb::count()
            + \App\Models\StandaloneRedis::count()
            + \App\Models\StandaloneKeydb::count()
            + \App\Models\StandaloneDragonfly::count()
            + \App\Models\StandaloneClickhouse::count()
            + \App\Models\Service::count();

        // Count active resources (would need status checks - simplified for now)
        $activeResources = $totalResources; // Simplified - would need actual status checks

        $metrics = [
            'totalResources' => $totalResources,
            'activeResources' => $activeResources,
            'totalDeployments' => $totalDeployments,
            'successfulDeployments' => $successfulDeployments,
            'failedDeployments' => $failedDeployments,
            'averageDeploymentTime' => round($avgDeploymentTime, 2),
            'deploymentsLast24h' => $deploymentsLast24h,
            'deploymentsLast7d' => $deploymentsLast7d,
            'successRate' => round($successRate, 2),
        ];

        return Inertia::render('Admin/Metrics/Index', [
            'metrics' => $metrics,
        ]);
    })->name('metrics');
});
