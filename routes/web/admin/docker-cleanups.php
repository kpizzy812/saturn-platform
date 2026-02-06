<?php

/**
 * Admin Docker Cleanup History routes
 *
 * Docker cleanup execution history across all servers.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/docker-cleanups', function (Request $request) {
    $query = \App\Models\DockerCleanupExecution::with('server')
        ->orderByDesc('created_at');

    // Filter by server
    if ($request->filled('server_id')) {
        $query->where('server_id', $request->integer('server_id'));
    }

    // Filter by status
    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    $cleanups = $query->paginate(50)->through(function ($cleanup) {
        return [
            'id' => $cleanup->id,
            'server_id' => $cleanup->server_id,
            'server_name' => $cleanup->server?->name ?? 'Unknown',
            'server_ip' => $cleanup->server?->ip ?? null,
            'status' => $cleanup->status,
            'message' => $cleanup->message,
            'created_at' => $cleanup->created_at,
        ];
    });

    // Stats
    $totalLast7d = \App\Models\DockerCleanupExecution::where('created_at', '>=', now()->subDays(7))->count();
    $failedLast7d = \App\Models\DockerCleanupExecution::where('created_at', '>=', now()->subDays(7))
        ->where('status', 'failed')->count();
    $serversWithCleanup = \App\Models\DockerCleanupExecution::where('created_at', '>=', now()->subDays(7))
        ->distinct('server_id')->count('server_id');

    // Server list for filter
    $servers = \App\Models\Server::select('id', 'name')->orderBy('name')->get();

    return Inertia::render('Admin/DockerCleanups/Index', [
        'cleanups' => $cleanups,
        'stats' => [
            'totalLast7d' => $totalLast7d,
            'failedLast7d' => $failedLast7d,
            'serversWithCleanup' => $serversWithCleanup,
        ],
        'servers' => $servers,
        'filters' => $request->only(['server_id', 'status']),
    ]);
})->name('admin.docker-cleanups');
