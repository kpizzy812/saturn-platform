<?php

/**
 * Admin Logs routes
 *
 * System logs and audit logs management including viewing and export.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// System Logs
Route::get('/logs', function () {
    // Fetch system logs (admin view)
    $logPath = storage_path('logs/laravel.log');
    $logs = [];
    $id = 1;

    if (file_exists($logPath)) {
        $logContent = file_get_contents($logPath);
        $logLines = array_filter(explode("\n", $logContent));

        // Get last 100 log lines
        $logLines = array_slice($logLines, -100);

        foreach ($logLines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
                // Map Laravel log levels to frontend expected levels
                $levelMap = [
                    'DEBUG' => 'debug',
                    'INFO' => 'info',
                    'NOTICE' => 'info',
                    'WARNING' => 'warning',
                    'ERROR' => 'error',
                    'CRITICAL' => 'critical',
                    'ALERT' => 'critical',
                    'EMERGENCY' => 'critical',
                ];

                // Detect category from message content
                $message = $matches[4];
                $category = 'system';
                if (stripos($message, 'auth') !== false || stripos($message, 'login') !== false) {
                    $category = 'auth';
                } elseif (stripos($message, 'deploy') !== false) {
                    $category = 'deployment';
                } elseif (stripos($message, 'server') !== false || stripos($message, 'ssh') !== false) {
                    $category = 'server';
                } elseif (stripos($message, 'api') !== false) {
                    $category = 'api';
                } elseif (stripos($message, 'security') !== false || stripos($message, 'permission') !== false) {
                    $category = 'security';
                }

                $logs[] = [
                    'id' => $id++,
                    'timestamp' => $matches[1],
                    'level' => $levelMap[strtoupper($matches[3])] ?? 'info',
                    'category' => $category,
                    'message' => $message,
                ];
            } else {
                // If line doesn't match pattern, add to previous log entry or create new one
                if (! empty($logs)) {
                    $logs[count($logs) - 1]['message'] .= "\n".$line;
                }
            }
        }

        $logs = array_reverse($logs);
    }

    return Inertia::render('Admin/Logs/Index', [
        'logs' => $logs,
        'total' => count($logs),
    ]);
})->name('admin.logs.index');

// Audit Logs - User activity tracking
Route::get('/audit-logs', function (Request $request) {
    $query = \App\Models\AuditLog::with(['user', 'team']);

    // Search filter
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
                ->orWhere('resource_name', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }

    // Action filter
    if ($action = $request->get('action')) {
        $query->where('action', $action);
    }

    // Resource type filter
    if ($resourceType = $request->get('resource_type')) {
        $query->where('resource_type', 'like', "%{$resourceType}%");
    }

    // User filter
    if ($userId = $request->get('user_id')) {
        $query->where('user_id', $userId);
    }

    // Date range filter
    if ($dateFrom = $request->get('date_from')) {
        $query->whereDate('created_at', '>=', $dateFrom);
    }
    if ($dateTo = $request->get('date_to')) {
        $query->whereDate('created_at', '<=', $dateTo);
    }

    $logs = $query->latest()
        ->paginate(50)
        ->through(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'formatted_action' => $log->formatted_action,
                'resource_type' => $log->resource_type_name,
                'resource_id' => $log->resource_id,
                'resource_name' => $log->resource_name,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'user_email' => $log->user?->email,
                'team_id' => $log->team_id,
                'team_name' => $log->team?->name,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
            ];
        });

    // Get unique actions for filter dropdown
    $actions = \App\Models\AuditLog::distinct()
        ->pluck('action')
        ->filter()
        ->sort()
        ->values();

    // Get unique resource types for filter dropdown
    $resourceTypes = \App\Models\AuditLog::distinct()
        ->pluck('resource_type')
        ->filter()
        ->map(fn ($type) => class_basename($type))
        ->unique()
        ->sort()
        ->values();

    // Get users who have audit logs
    $users = \App\Models\User::whereIn('id', \App\Models\AuditLog::distinct()->pluck('user_id'))
        ->select('id', 'name', 'email')
        ->orderBy('name')
        ->get();

    return Inertia::render('Admin/AuditLogs/Index', [
        'logs' => $logs,
        'actions' => $actions,
        'resourceTypes' => $resourceTypes,
        'users' => $users,
        'filters' => [
            'search' => $request->get('search'),
            'action' => $request->get('action'),
            'resource_type' => $request->get('resource_type'),
            'user_id' => $request->get('user_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ],
    ]);
})->name('admin.audit-logs.index');

// Audit Logs Export
Route::get('/audit-logs/export', function (Request $request) {
    $query = \App\Models\AuditLog::with(['user', 'team']);

    // Apply same filters as index
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
                ->orWhere('resource_name', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%");
        });
    }
    if ($action = $request->get('action')) {
        $query->where('action', $action);
    }
    if ($resourceType = $request->get('resource_type')) {
        $query->where('resource_type', 'like', "%{$resourceType}%");
    }
    if ($userId = $request->get('user_id')) {
        $query->where('user_id', $userId);
    }
    if ($dateFrom = $request->get('date_from')) {
        $query->whereDate('created_at', '>=', $dateFrom);
    }
    if ($dateTo = $request->get('date_to')) {
        $query->whereDate('created_at', '<=', $dateTo);
    }

    $format = $request->get('format', 'csv');
    $logs = $query->latest()->limit(10000)->get();

    if ($format === 'json') {
        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'resource_type' => $log->resource_type_name,
                'resource_name' => $log->resource_name,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'user' => $log->user?->name,
                'user_email' => $log->user?->email,
                'team' => $log->team?->name,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ];
        });

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="audit-logs-'.now()->format('Y-m-d').'.json"');
    }

    // CSV export
    $csv = "ID,Action,Resource Type,Resource Name,Description,User,Email,Team,IP Address,Created At\n";
    foreach ($logs as $log) {
        $csv .= implode(',', [
            $log->id,
            '"'.str_replace('"', '""', $log->action ?? '').'"',
            '"'.str_replace('"', '""', $log->resource_type_name ?? '').'"',
            '"'.str_replace('"', '""', $log->resource_name ?? '').'"',
            '"'.str_replace('"', '""', $log->description ?? '').'"',
            '"'.str_replace('"', '""', $log->user?->name ?? '').'"',
            '"'.str_replace('"', '""', $log->user?->email ?? '').'"',
            '"'.str_replace('"', '""', $log->team?->name ?? '').'"',
            '"'.str_replace('"', '""', $log->ip_address ?? '').'"',
            $log->created_at?->toISOString() ?? '',
        ])."\n";
    }

    return response($csv)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="audit-logs-'.now()->format('Y-m-d').'.csv"');
})->name('admin.audit-logs.export');
