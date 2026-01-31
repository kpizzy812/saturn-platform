<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks & Cron Jobs Routes
|--------------------------------------------------------------------------
|
| Routes for managing scheduled tasks and cron jobs for applications and services.
|
*/

// Cron Jobs routes
Route::get('/cron-jobs', function () {
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
    $serviceIds = \App\Models\Service::ownedByCurrentTeam()->pluck('id');

    $cronJobs = \App\Models\ScheduledTask::where(function ($q) use ($applicationIds, $serviceIds) {
        $q->whereIn('application_id', $applicationIds)
            ->orWhereIn('service_id', $serviceIds);
    })->with(['latest_log', 'application:id,name,uuid', 'service:id,name,uuid'])
        ->get()
        ->map(fn ($task) => [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'name' => $task->name,
            'command' => $task->command,
            'schedule' => $task->frequency,
            'status' => $task->enabled ? ($task->latest_log?->status ?? 'scheduled') : 'disabled',
            'lastRun' => $task->latest_log?->created_at?->toISOString(),
            'nextRun' => null,
            'resource' => $task->application?->name ?? $task->service?->name ?? 'Unknown',
        ]);

    return Inertia::render('CronJobs/Index', [
        'cronJobs' => $cronJobs,
    ]);
})->name('cron-jobs.index');

Route::get('/cron-jobs/create', function () {
    $applications = \App\Models\Application::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name')->get();
    $services = \App\Models\Service::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name')->get();

    return Inertia::render('CronJobs/Create', [
        'applications' => $applications,
        'services' => $services,
    ]);
})->name('cron-jobs.create');

Route::get('/cron-jobs/{uuid}', function (string $uuid) {
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
    $serviceIds = \App\Models\Service::ownedByCurrentTeam()->pluck('id');

    $task = \App\Models\ScheduledTask::where('uuid', $uuid)
        ->where(function ($q) use ($applicationIds, $serviceIds) {
            $q->whereIn('application_id', $applicationIds)
                ->orWhereIn('service_id', $serviceIds);
        })->with(['executions' => fn ($q) => $q->limit(20), 'application:id,name,uuid', 'service:id,name,uuid'])
        ->firstOrFail();

    return Inertia::render('CronJobs/Show', [
        'cronJob' => [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'name' => $task->name,
            'command' => $task->command,
            'schedule' => $task->frequency,
            'status' => $task->enabled ? ($task->latest_log?->status ?? 'scheduled') : 'disabled',
            'lastRun' => $task->latest_log?->created_at?->toISOString(),
            'resource' => $task->application?->name ?? $task->service?->name ?? 'Unknown',
            'enabled' => $task->enabled,
            'timeout' => $task->timeout,
        ],
        'executions' => $task->executions->map(fn ($e) => [
            'id' => $e->id,
            'status' => $e->status,
            'message' => $e->message,
            'created_at' => $e->created_at?->toISOString(),
        ]),
    ]);
})->name('cron-jobs.show');

// Scheduled Tasks routes
Route::get('/scheduled-tasks', function () {
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
    $serviceIds = \App\Models\Service::ownedByCurrentTeam()->pluck('id');

    $tasks = \App\Models\ScheduledTask::where(function ($q) use ($applicationIds, $serviceIds) {
        $q->whereIn('application_id', $applicationIds)
            ->orWhereIn('service_id', $serviceIds);
    })->with(['latest_log', 'application:id,name,uuid', 'service:id,name,uuid'])
        ->get()
        ->map(fn ($task) => [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'name' => $task->name,
            'command' => $task->command,
            'frequency' => $task->frequency,
            'enabled' => $task->enabled,
            'status' => $task->latest_log?->status ?? 'unknown',
            'lastRun' => $task->latest_log?->created_at?->toISOString(),
            'resource' => $task->application?->name ?? $task->service?->name ?? 'Unknown',
            'resourceType' => $task->application_id ? 'application' : 'service',
        ]);

    // Get available resources for the create task modal
    $applications = \App\Models\Application::ownedByCurrentTeam()
        ->select('id', 'name', 'uuid')
        ->get()
        ->map(fn ($app) => ['id' => $app->id, 'name' => $app->name, 'uuid' => $app->uuid, 'type' => 'application']);

    $services = \App\Models\Service::ownedByCurrentTeam()
        ->select('id', 'name', 'uuid')
        ->get()
        ->map(fn ($svc) => ['id' => $svc->id, 'name' => $svc->name, 'uuid' => $svc->uuid, 'type' => 'service']);

    return Inertia::render('ScheduledTasks/Index', [
        'tasks' => $tasks,
        'resources' => $applications->merge($services)->values(),
    ]);
})->name('scheduled-tasks.index');

Route::get('/scheduled-tasks/history', function () {
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
    $serviceIds = \App\Models\Service::ownedByCurrentTeam()->pluck('id');

    $tasks = \App\Models\ScheduledTask::where(function ($q) use ($applicationIds, $serviceIds) {
        $q->whereIn('application_id', $applicationIds)
            ->orWhereIn('service_id', $serviceIds);
    })->with(['executions' => fn ($q) => $q->limit(10), 'application:id,name,uuid', 'service:id,name,uuid'])
        ->get()
        ->map(fn ($task) => [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'name' => $task->name,
            'command' => $task->command,
            'frequency' => $task->frequency,
            'enabled' => $task->enabled,
            'resource' => $task->application?->name ?? $task->service?->name ?? 'Unknown',
            'executions' => $task->executions->map(fn ($e) => [
                'id' => $e->id,
                'status' => $e->status,
                'message' => $e->message,
                'created_at' => $e->created_at?->toISOString(),
            ]),
        ]);

    return Inertia::render('ScheduledTasks/History', [
        'history' => $tasks,
    ]);
})->name('scheduled-tasks.history');
