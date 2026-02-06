<?php

/**
 * Admin Scheduled Tasks overview routes
 *
 * All cron jobs / scheduled tasks across all teams.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/scheduled-tasks', function (Request $request) {
    $query = \App\Models\ScheduledTask::with([
        'application.environment.project.team',
        'service.environment.project.team',
        'latest_log',
    ]);

    // Filter by status (enabled/disabled)
    if ($request->filled('enabled')) {
        $query->where('enabled', $request->boolean('enabled'));
    }

    // Search by name or command
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('command', 'ilike', "%{$search}%");
        });
    }

    $tasks = $query->orderByDesc('updated_at')
        ->paginate(50)
        ->through(function ($task) {
            $resource = $task->application ?? $task->service;
            $team = $resource?->environment?->project?->team;

            return [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'name' => $task->name,
                'command' => $task->command,
                'frequency' => $task->frequency,
                'enabled' => $task->enabled,
                'timeout' => $task->timeout,
                'container' => $task->container,
                'resource_type' => $task->application_id ? 'Application' : ($task->service_id ? 'Service' : 'Unknown'),
                'resource_name' => $resource?->name ?? 'Unknown',
                'team_name' => $team?->name ?? 'Unknown',
                'last_execution' => $task->latest_log ? [
                    'status' => $task->latest_log->status,
                    'started_at' => $task->latest_log->started_at,
                    'finished_at' => $task->latest_log->finished_at,
                    'duration' => $task->latest_log->duration,
                ] : null,
                'updated_at' => $task->updated_at,
            ];
        });

    // Stats
    $totalTasks = \App\Models\ScheduledTask::count();
    $enabledTasks = \App\Models\ScheduledTask::where('enabled', true)->count();
    $failedRecent = \App\Models\ScheduledTaskExecution::where('status', 'failed')
        ->where('created_at', '>=', now()->subHours(24))
        ->count();

    return Inertia::render('Admin/ScheduledTasks/Index', [
        'tasks' => $tasks,
        'stats' => [
            'total' => $totalTasks,
            'enabled' => $enabledTasks,
            'disabled' => $totalTasks - $enabledTasks,
            'failedLast24h' => $failedRecent,
        ],
        'filters' => $request->only(['enabled', 'search']),
    ]);
})->name('admin.scheduled-tasks');
