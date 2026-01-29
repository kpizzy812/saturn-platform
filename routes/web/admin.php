<?php

/**
 * Admin routes for Saturn Platform
 *
 * These routes handle the admin panel for managing users, servers, deployments, etc.
 * All routes require authentication and admin privileges.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Admin routes
Route::prefix('admin')->group(function () {
    Route::get('/', function () {
        // Fetch actual system stats
        $stats = [
            'totalUsers' => \App\Models\User::count(),
            'activeUsers' => \App\Models\User::where('created_at', '>=', now()->subDays(30))->count(),
            'totalServers' => \App\Models\Server::count(),
            'totalDeployments' => \App\Models\ApplicationDeploymentQueue::count(),
            'failedDeployments' => \App\Models\ApplicationDeploymentQueue::where('status', 'failed')->count(),
            'totalTeams' => \App\Models\Team::count(),
            'totalApplications' => \App\Models\Application::count(),
            'totalServices' => \App\Models\Service::count(),
        ];

        // Recent activity from deployments (primary source of activity)
        $recentActivity = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($deployment) {
                $statusAction = match ($deployment->status) {
                    'finished' => 'deployment_completed',
                    'failed', 'cancelled' => 'deployment_failed',
                    'in_progress', 'queued' => 'deployment_started',
                    default => 'deployment_updated',
                };

                return [
                    'id' => $deployment->id,
                    'action' => $statusAction,
                    'description' => $deployment->commit_message ?: "Deployment {$deployment->status}",
                    'user_name' => $deployment->application?->environment?->project?->team?->name,
                    'team_name' => $deployment->application?->environment?->project?->team?->name,
                    'resource_type' => 'Application',
                    'resource_name' => $deployment->application?->name,
                    'created_at' => $deployment->created_at,
                ];
            });

        // Health checks
        $healthChecks = [];
        try {
            // Check PostgreSQL
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $healthChecks[] = [
                'service' => 'PostgreSQL',
                'status' => 'healthy',
                'lastCheck' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            $healthChecks[] = [
                'service' => 'PostgreSQL',
                'status' => 'down',
                'lastCheck' => now()->toISOString(),
            ];
        }

        try {
            \Illuminate\Support\Facades\Redis::ping();
            $healthChecks[] = [
                'service' => 'Redis',
                'status' => 'healthy',
                'lastCheck' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            $healthChecks[] = [
                'service' => 'Redis',
                'status' => 'down',
                'lastCheck' => now()->toISOString(),
            ];
        }

        return Inertia::render('Admin/Index', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'healthChecks' => $healthChecks,
        ]);
    })->name('admin.index');

    Route::get('/users', function () {
        // Fetch all users with their teams
        $users = \App\Models\User::with(['teams'])
            ->withCount(['teams'])
            ->latest()
            ->paginate(50);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    })->name('admin.users.index');

    Route::get('/users/{id}', function (int $id) {
        // Fetch specific user with all relationships
        $user = \App\Models\User::with(['teams.projects.environments'])
            ->withCount(['teams'])
            ->findOrFail($id);

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
        ]);
    })->name('admin.users.show');

    Route::get('/applications', function () {
        // Fetch all applications across all teams (admin view)
        $applications = \App\Models\Application::with(['environment.project.team', 'destination'])
            ->latest()
            ->paginate(50)
            ->through(function ($app) {
                return [
                    'id' => $app->id,
                    'uuid' => $app->uuid,
                    'name' => $app->name,
                    'description' => $app->description,
                    'fqdn' => $app->fqdn,
                    'status' => $app->status,
                    'team_name' => $app->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $app->environment?->project?->team?->id,
                    'created_at' => $app->created_at,
                    'updated_at' => $app->updated_at,
                ];
            });

        return Inertia::render('Admin/Applications/Index', [
            'applications' => $applications,
        ]);
    })->name('admin.applications.index');

    Route::get('/databases', function () {
        // Fetch all databases across all teams (admin view)
        $databases = collect();

        // Fetch all standalone databases
        $postgresql = \App\Models\StandalonePostgresql::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'postgresql',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $mysql = \App\Models\StandaloneMysql::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'mysql',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $mariadb = \App\Models\StandaloneMariadb::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'mariadb',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $mongodb = \App\Models\StandaloneMongodb::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'mongodb',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $redis = \App\Models\StandaloneRedis::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'redis',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $keydb = \App\Models\StandaloneKeydb::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'keydb',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $dragonfly = \App\Models\StandaloneDragonfly::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'dragonfly',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $clickhouse = \App\Models\StandaloneClickhouse::with(['environment.project.team'])
            ->get()
            ->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'database_type' => 'clickhouse',
                    'status' => $db->status(),
                    'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $db->environment?->project?->team?->id,
                    'created_at' => $db->created_at,
                    'updated_at' => $db->updated_at,
                ];
            });

        $allDatabases = $databases
            ->concat($postgresql)
            ->concat($mysql)
            ->concat($mariadb)
            ->concat($mongodb)
            ->concat($redis)
            ->concat($keydb)
            ->concat($dragonfly)
            ->concat($clickhouse)
            ->sortByDesc('updated_at')
            ->values();

        return Inertia::render('Admin/Databases/Index', [
            'databases' => $allDatabases,
        ]);
    })->name('admin.databases.index');

    Route::get('/services', function () {
        // Fetch all services across all teams (admin view)
        $services = \App\Models\Service::with(['environment.project.team', 'server', 'applications'])
            ->latest()
            ->paginate(50)
            ->through(function ($service) {
                return [
                    'id' => $service->id,
                    'uuid' => $service->uuid,
                    'name' => $service->name,
                    'description' => $service->description,
                    'team_name' => $service->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $service->environment?->project?->team?->id,
                    'server_name' => $service->server?->name,
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                ];
            });

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
        ]);
    })->name('admin.services.index');

    Route::get('/servers', function () {
        // Fetch all servers across all teams (admin view)
        $servers = \App\Models\Server::with(['team'])
            ->latest()
            ->paginate(50)
            ->through(function ($server) {
                return [
                    'id' => $server->id,
                    'uuid' => $server->uuid,
                    'name' => $server->name,
                    'description' => $server->description,
                    'ip' => $server->ip,
                    'port' => $server->port,
                    'user' => $server->user,
                    'team_name' => $server->team?->name ?? 'Unknown',
                    'team_id' => $server->team_id,
                    'created_at' => $server->created_at,
                    'updated_at' => $server->updated_at,
                ];
            });

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
        ]);
    })->name('admin.servers.index');

    Route::get('/deployments', function () {
        // Fetch all deployments across all teams (admin view)
        $deployments = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team'])
            ->latest()
            ->paginate(50)
            ->through(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'application_name' => $deployment->application?->name ?? 'Unknown',
                    'application_uuid' => $deployment->application?->uuid,
                    'status' => $deployment->status,
                    'team_name' => $deployment->application?->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $deployment->application?->environment?->project?->team?->id,
                    'created_at' => $deployment->created_at,
                    'updated_at' => $deployment->updated_at,
                ];
            });

        return Inertia::render('Admin/Deployments/Index', [
            'deployments' => $deployments,
        ]);
    })->name('admin.deployments.index');

    Route::get('/teams', function () {
        // Fetch all teams (admin view)
        $teams = \App\Models\Team::withCount(['members', 'projects', 'servers'])
            ->latest()
            ->paginate(50)
            ->through(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'members_count' => $team->members_count,
                    'projects_count' => $team->projects_count,
                    'servers_count' => $team->servers_count,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                ];
            });

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
        ]);
    })->name('admin.teams.index');

    Route::get('/settings', function () {
        // Fetch instance settings (admin view)
        $settings = \App\Models\InstanceSettings::get();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    })->name('admin.settings.index');

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
});
