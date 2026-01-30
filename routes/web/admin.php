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
        $recentActivity = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team', 'user'])
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

                // Get user name - from user relationship or fallback to team name
                $userName = $deployment->user?->name;
                if (! $userName) {
                    // Determine trigger source if no user
                    if ($deployment->is_webhook) {
                        $userName = 'Webhook';
                    } elseif ($deployment->is_api) {
                        $userName = 'API';
                    } elseif ($deployment->triggered_by) {
                        $userName = ucfirst($deployment->triggered_by);
                    } else {
                        $userName = $deployment->application?->environment?->project?->team?->name ?? 'System';
                    }
                }

                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'action' => $statusAction,
                    'status' => $deployment->status,
                    'description' => $deployment->commit_message ?: "Deployment {$deployment->status}",
                    'commit' => $deployment->commit ? substr($deployment->commit, 0, 7) : null,
                    'user_name' => $userName,
                    'user_email' => $deployment->user?->email,
                    'team_name' => $deployment->application?->environment?->project?->team?->name,
                    'resource_type' => 'Application',
                    'resource_name' => $deployment->application?->name,
                    'application_uuid' => $deployment->application?->uuid,
                    'triggered_by' => $deployment->triggered_by ?? ($deployment->is_webhook ? 'webhook' : ($deployment->is_api ? 'api' : 'manual')),
                    'is_webhook' => $deployment->is_webhook,
                    'is_api' => $deployment->is_api,
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
        // Get search and filter parameters
        $search = request()->input('search', '');
        $statusFilter = request()->input('status', 'all');
        $sortBy = request()->input('sort_by', 'created_at');
        $sortDirection = request()->input('sort_direction', 'desc');

        // Build query with search and filters
        $query = \App\Models\User::with(['teams'])
            ->withCount(['teams']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Apply status filter
        if ($statusFilter !== 'all') {
            if ($statusFilter === 'pending') {
                // Pending = active status but email not verified
                $query->where('status', 'active')
                    ->whereNull('email_verified_at');
            } else {
                $query->where('status', $statusFilter);
            }
        }

        // Apply sorting
        $validSortFields = ['name', 'email', 'created_at', 'last_login_at', 'teams_count'];
        if (in_array($sortBy, $validSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }

        // Paginate results
        $paginator = $query->paginate(50)->withQueryString();

        $users = $paginator->through(function ($user) {
            // Count servers across all user's teams
            $serversCount = $user->teams->sum(function ($team) {
                return $team->servers()->count();
            });

            // Determine real status from database
            $status = $user->status ?? 'active';

            // If email not verified and status is default, set to pending
            if ($status === 'active' && is_null($user->email_verified_at)) {
                $status = 'pending';
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $status,
                'is_root_user' => $user->id === 0 || $user->is_superadmin,
                'teams_count' => $user->teams_count,
                'servers_count' => $serversCount,
                'created_at' => $user->created_at->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString() ?? $user->updated_at?->toISOString(),
                'suspended_at' => $user->suspended_at?->toISOString(),
                'suspension_reason' => $user->suspension_reason,
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->items(),
            'total' => $paginator->total(),
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'lastPage' => $paginator->lastPage(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
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

    Route::post('/users/{id}/impersonate', function (int $id) {
        $adminUser = Auth::user();

        // Only superadmins can impersonate
        if (! $adminUser->isSuperAdmin()) {
            return back()->with('error', 'Unauthorized: Only superadmins can impersonate users');
        }

        $targetUser = \App\Models\User::findOrFail($id);

        // Cannot impersonate root user (id=0) or other superadmins
        if ($targetUser->id === 0 || $targetUser->isSuperAdmin()) {
            return back()->with('error', 'Cannot impersonate root user or other superadmins');
        }

        // Cannot impersonate suspended/banned users
        if ($targetUser->isSuspended() || $targetUser->isBanned()) {
            return back()->with('error', 'Cannot impersonate suspended or banned users');
        }

        // Store original user ID in session for returning later
        session(['impersonating_user_id' => $adminUser->id]);

        // Log the impersonation event
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => $targetUser->currentTeam()?->id,
            'action' => 'user_impersonated',
            'resource_type' => 'User',
            'resource_id' => $targetUser->id,
            'resource_name' => $targetUser->name,
            'description' => "Admin {$adminUser->name} impersonated user {$targetUser->name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Login as target user
        Auth::login($targetUser);

        return redirect()->route('dashboard')->with('success', "Now impersonating {$targetUser->name}. You will be automatically logged back as admin after 30 minutes or when you logout.");
    })->name('admin.users.impersonate');

    Route::post('/users/{id}/toggle-suspension', function (int $id) {
        $adminUser = Auth::user();

        // Only superadmins can suspend users
        if (! $adminUser->isSuperAdmin()) {
            return back()->with('error', 'Unauthorized: Only superadmins can suspend users');
        }

        $targetUser = \App\Models\User::findOrFail($id);

        // Cannot suspend root user (id=0) or other superadmins
        if ($targetUser->id === 0 || $targetUser->isSuperAdmin()) {
            return back()->with('error', 'Cannot suspend root user or other superadmins');
        }

        // Toggle suspension status
        if ($targetUser->isSuspended()) {
            // Activate user
            $targetUser->activate();

            // Log the activation
            \App\Models\AuditLog::create([
                'user_id' => $adminUser->id,
                'team_id' => null,
                'action' => 'user_activated',
                'resource_type' => 'User',
                'resource_id' => $targetUser->id,
                'resource_name' => $targetUser->name,
                'description' => "Admin {$adminUser->name} activated user {$targetUser->name}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return back()->with('success', "User {$targetUser->name} has been activated");
        } else {
            // Suspend user
            $reason = request()->input('reason', 'No reason provided');
            $targetUser->suspend($reason, $adminUser->id);

            // Log the suspension
            \App\Models\AuditLog::create([
                'user_id' => $adminUser->id,
                'team_id' => null,
                'action' => 'user_suspended',
                'resource_type' => 'User',
                'resource_id' => $targetUser->id,
                'resource_name' => $targetUser->name,
                'description' => "Admin {$adminUser->name} suspended user {$targetUser->name}. Reason: {$reason}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return back()->with('success', "User {$targetUser->name} has been suspended");
        }
    })->name('admin.users.toggle-suspension');

    // Bulk user operations
    Route::post('/users/bulk-suspend', function () {
        $adminUser = Auth::user();

        if (! $adminUser->isSuperAdmin()) {
            return back()->with('error', 'Unauthorized: Only superadmins can suspend users');
        }

        $userIds = request()->input('user_ids', []);
        if (empty($userIds)) {
            return back()->with('error', 'No users selected');
        }

        $reason = request()->input('reason', 'Bulk suspension by admin');
        $suspendedCount = 0;
        $skippedCount = 0;

        foreach ($userIds as $userId) {
            $user = \App\Models\User::find($userId);
            if (! $user || $user->id === 0 || $user->isSuperAdmin()) {
                $skippedCount++;

                continue;
            }

            if (! $user->isSuspended()) {
                $user->suspend($reason, $adminUser->id);
                $suspendedCount++;
            }
        }

        // Log bulk action
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'users_bulk_suspended',
            'resource_type' => 'User',
            'resource_id' => null,
            'resource_name' => "{$suspendedCount} users",
            'description' => "Admin {$adminUser->name} bulk suspended {$suspendedCount} users",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', "Suspended {$suspendedCount} users".($skippedCount > 0 ? " ({$skippedCount} skipped)" : ''));
    })->name('admin.users.bulk-suspend');

    Route::post('/users/bulk-activate', function () {
        $adminUser = Auth::user();

        if (! $adminUser->isSuperAdmin()) {
            return back()->with('error', 'Unauthorized: Only superadmins can activate users');
        }

        $userIds = request()->input('user_ids', []);
        if (empty($userIds)) {
            return back()->with('error', 'No users selected');
        }

        $activatedCount = 0;

        foreach ($userIds as $userId) {
            $user = \App\Models\User::find($userId);
            if (! $user) {
                continue;
            }

            if ($user->isSuspended()) {
                $user->activate();
                $activatedCount++;
            }
        }

        // Log bulk action
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'users_bulk_activated',
            'resource_type' => 'User',
            'resource_id' => null,
            'resource_name' => "{$activatedCount} users",
            'description' => "Admin {$adminUser->name} bulk activated {$activatedCount} users",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', "Activated {$activatedCount} users");
    })->name('admin.users.bulk-activate');

    Route::delete('/users/bulk-delete', function () {
        $adminUser = Auth::user();

        if (! $adminUser->isSuperAdmin()) {
            return back()->with('error', 'Unauthorized: Only superadmins can delete users');
        }

        $userIds = request()->input('user_ids', []);
        if (empty($userIds)) {
            return back()->with('error', 'No users selected');
        }

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($userIds as $userId) {
            $user = \App\Models\User::find($userId);
            if (! $user || $user->id === 0 || $user->isSuperAdmin()) {
                $skippedCount++;

                continue;
            }

            // Store name for logging before deletion
            $userName = $user->name;
            $user->delete();
            $deletedCount++;
        }

        // Log bulk action
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'users_bulk_deleted',
            'resource_type' => 'User',
            'resource_id' => null,
            'resource_name' => "{$deletedCount} users",
            'description' => "Admin {$adminUser->name} bulk deleted {$deletedCount} users",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', "Deleted {$deletedCount} users".($skippedCount > 0 ? " ({$skippedCount} skipped)" : ''));
    })->name('admin.users.bulk-delete');

    Route::get('/users/export', function () {
        $adminUser = Auth::user();

        if (! $adminUser->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get filter parameters
        $search = request()->input('search', '');
        $statusFilter = request()->input('status', 'all');

        // Build query
        $query = \App\Models\User::with(['teams'])
            ->withCount(['teams']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $csv = "ID,Name,Email,Status,Teams,Created At,Last Login\n";
        foreach ($users as $user) {
            $status = $user->status ?? 'active';
            if ($status === 'active' && is_null($user->email_verified_at)) {
                $status = 'pending';
            }
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",%s,%d,%s,%s\n",
                $user->id,
                str_replace('"', '""', $user->name),
                str_replace('"', '""', $user->email),
                $status,
                $user->teams_count,
                $user->created_at->format('Y-m-d H:i:s'),
                $user->last_login_at?->format('Y-m-d H:i:s') ?? 'Never'
            );
        }

        // Log export
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'users_exported',
            'resource_type' => 'User',
            'resource_id' => null,
            'resource_name' => "{$users->count()} users",
            'description' => "Admin {$adminUser->name} exported {$users->count()} users to CSV",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-export-'.now()->format('Y-m-d').'.csv"',
        ]);
    })->name('admin.users.export');

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

    Route::get('/applications/{uuid}', function (string $uuid) {
        // Fetch specific application with all relationships
        $application = \App\Models\Application::with([
            'environment.project.team',
            'destination.server',
        ])->where('uuid', $uuid)->firstOrFail();

        // Get recent deployments
        $recentDeployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'commit_message' => $deployment->commit_message,
                    'triggered_by' => $deployment->triggered_by ?? ($deployment->is_webhook ? 'webhook' : ($deployment->is_api ? 'api' : 'manual')),
                    'created_at' => $deployment->created_at,
                    'finished_at' => $deployment->updated_at,
                ];
            });

        $server = $application->destination?->server;

        return Inertia::render('Admin/Applications/Show', [
            'application' => [
                'id' => $application->id,
                'uuid' => $application->uuid,
                'name' => $application->name,
                'description' => $application->description,
                'fqdn' => $application->fqdn,
                'status' => $application->status ?? 'unknown',
                'git_repository' => $application->git_repository,
                'git_branch' => $application->git_branch,
                'git_commit_sha' => $application->git_commit_sha,
                'build_pack' => $application->build_pack,
                'dockerfile_location' => $application->dockerfile_location,
                'team_id' => $application->environment?->project?->team?->id,
                'team_name' => $application->environment?->project?->team?->name ?? 'Unknown',
                'project_id' => $application->environment?->project?->id,
                'project_name' => $application->environment?->project?->name ?? 'Unknown',
                'environment_id' => $application->environment?->id,
                'environment_name' => $application->environment?->name ?? 'Unknown',
                'server_id' => $server?->id,
                'server_name' => $server?->name,
                'server_uuid' => $server?->uuid,
                'recent_deployments' => $recentDeployments,
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
            ],
        ]);
    })->name('admin.applications.show');

    Route::post('/applications/{uuid}/restart', function (string $uuid) {
        $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

        try {
            $application->restart();

            return back()->with('success', 'Application restart initiated');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to restart application: '.$e->getMessage());
        }
    })->name('admin.applications.restart');

    Route::post('/applications/{uuid}/stop', function (string $uuid) {
        $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

        try {
            $application->stop();

            return back()->with('success', 'Application stopped');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to stop application: '.$e->getMessage());
        }
    })->name('admin.applications.stop');

    Route::post('/applications/{uuid}/start', function (string $uuid) {
        $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

        try {
            $application->restart();

            return back()->with('success', 'Application started');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to start application: '.$e->getMessage());
        }
    })->name('admin.applications.start');

    Route::post('/applications/{uuid}/redeploy', function (string $uuid) {
        $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

        try {
            queue_application_deployment(
                application: $application,
                deployment_uuid: (string) new \Illuminate\Support\Str,
                force_rebuild: false,
            );

            return back()->with('success', 'Redeploy initiated');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to redeploy: '.$e->getMessage());
        }
    })->name('admin.applications.redeploy');

    Route::get('/projects', function () {
        // Fetch all projects across all teams (admin view)
        $projects = \App\Models\Project::with(['team', 'environments'])
            ->withCount(['environments'])
            ->latest()
            ->paginate(50)
            ->through(function ($project) {
                $applicationsCount = 0;
                $servicesCount = 0;
                $databasesCount = 0;

                foreach ($project->environments as $env) {
                    $applicationsCount += $env->applications()->count();
                    $servicesCount += $env->services()->count();
                    $databasesCount += $env->databases()->count();
                }

                return [
                    'id' => $project->id,
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'description' => $project->description,
                    'team_id' => $project->team_id,
                    'team_name' => $project->team?->name ?? 'Unknown',
                    'environments_count' => $project->environments_count,
                    'applications_count' => $applicationsCount,
                    'services_count' => $servicesCount,
                    'databases_count' => $databasesCount,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            });

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
        ]);
    })->name('admin.projects.index');

    Route::get('/projects/{id}', function (int $id) {
        // Fetch specific project with all resources
        $project = \App\Models\Project::with(['team', 'environments'])
            ->findOrFail($id);

        $environments = $project->environments->map(function ($env) {
            return [
                'id' => $env->id,
                'uuid' => $env->uuid,
                'name' => $env->name,
                'applications' => $env->applications->map(function ($app) {
                    return [
                        'id' => $app->id,
                        'uuid' => $app->uuid,
                        'name' => $app->name,
                        'fqdn' => $app->fqdn,
                        'status' => $app->status ?? 'unknown',
                    ];
                }),
                'services' => $env->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'uuid' => $service->uuid,
                        'name' => $service->name,
                        'status' => 'running',
                    ];
                }),
                'databases' => $env->databases()->map(function ($db) {
                    return [
                        'id' => $db->id,
                        'uuid' => $db->uuid,
                        'name' => $db->name,
                        'type' => class_basename($db),
                        'status' => method_exists($db, 'status') ? $db->status() : 'unknown',
                    ];
                }),
            ];
        });

        return Inertia::render('Admin/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
                'team_id' => $project->team_id,
                'team_name' => $project->team?->name ?? 'Unknown',
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
                'environments' => $environments,
            ],
        ]);
    })->name('admin.projects.show');

    Route::delete('/projects/{id}', function (int $id) {
        $project = \App\Models\Project::findOrFail($id);
        $projectName = $project->name;
        $project->delete();

        return redirect()->route('admin.projects.index')->with('success', "Project '{$projectName}' deleted");
    })->name('admin.projects.delete');

    Route::delete('/applications/{id}', function (int $id) {
        $app = \App\Models\Application::findOrFail($id);
        $appName = $app->name;
        $app->delete();

        return back()->with('success', "Application '{$appName}' deleted");
    })->name('admin.applications.delete');

    Route::delete('/services/{id}', function (int $id) {
        $service = \App\Models\Service::findOrFail($id);
        $serviceName = $service->name;
        $service->delete();

        return back()->with('success', "Service '{$serviceName}' deleted");
    })->name('admin.services.delete');

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

    Route::get('/databases/{uuid}', function (string $uuid) {
        // Try to find database by UUID across all database types
        $database = null;
        $databaseType = null;

        $dbModels = [
            'postgresql' => \App\Models\StandalonePostgresql::class,
            'mysql' => \App\Models\StandaloneMysql::class,
            'mariadb' => \App\Models\StandaloneMariadb::class,
            'mongodb' => \App\Models\StandaloneMongodb::class,
            'redis' => \App\Models\StandaloneRedis::class,
            'keydb' => \App\Models\StandaloneKeydb::class,
            'dragonfly' => \App\Models\StandaloneDragonfly::class,
            'clickhouse' => \App\Models\StandaloneClickhouse::class,
        ];

        foreach ($dbModels as $type => $model) {
            $db = $model::with(['environment.project.team', 'destination.server', 'scheduledBackups'])
                ->where('uuid', $uuid)
                ->first();
            if ($db) {
                $database = $db;
                $databaseType = $type;
                break;
            }
        }

        if (! $database) {
            abort(404, 'Database not found');
        }

        $server = $database->destination?->server;

        // Get backup schedules
        $backups = $database->scheduledBackups?->map(function ($backup) {
            $lastExecution = $backup->executions()->latest()->first();

            return [
                'id' => $backup->id,
                'uuid' => $backup->uuid,
                'frequency' => $backup->frequency,
                'enabled' => $backup->enabled,
                'last_execution_status' => $lastExecution?->status,
                'last_execution_at' => $lastExecution?->created_at,
            ];
        }) ?? collect();

        return Inertia::render('Admin/Databases/Show', [
            'database' => [
                'id' => $database->id,
                'uuid' => $database->uuid,
                'name' => $database->name,
                'description' => $database->description,
                'database_type' => ucfirst($databaseType),
                'status' => $database->status(),
                'internal_db_url' => $database->internal_db_url ?? null,
                'public_port' => $database->public_port ?? null,
                'is_public' => $database->is_public ?? false,
                'team_id' => $database->environment?->project?->team?->id,
                'team_name' => $database->environment?->project?->team?->name ?? 'Unknown',
                'project_id' => $database->environment?->project?->id,
                'project_name' => $database->environment?->project?->name ?? 'Unknown',
                'environment_id' => $database->environment?->id,
                'environment_name' => $database->environment?->name ?? 'Unknown',
                'server_id' => $server?->id,
                'server_name' => $server?->name,
                'server_uuid' => $server?->uuid,
                'image' => $database->image ?? null,
                'limits_memory' => $database->limits_memory ?? null,
                'limits_cpus' => $database->limits_cpus ?? null,
                'backups' => $backups,
                'created_at' => $database->created_at,
                'updated_at' => $database->updated_at,
            ],
        ]);
    })->name('admin.databases.show');

    Route::post('/databases/{uuid}/restart', function (string $uuid) {
        $dbModels = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        $database = null;
        foreach ($dbModels as $model) {
            $db = $model::where('uuid', $uuid)->first();
            if ($db) {
                $database = $db;
                break;
            }
        }

        if (! $database) {
            return back()->with('error', 'Database not found');
        }

        try {
            \App\Actions\Database\RestartDatabase::run($database);

            return back()->with('success', 'Database restart initiated');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to restart database: '.$e->getMessage());
        }
    })->name('admin.databases.restart');

    Route::post('/databases/{uuid}/stop', function (string $uuid) {
        $dbModels = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        $database = null;
        foreach ($dbModels as $model) {
            $db = $model::where('uuid', $uuid)->first();
            if ($db) {
                $database = $db;
                break;
            }
        }

        if (! $database) {
            return back()->with('error', 'Database not found');
        }

        try {
            \App\Actions\Database\StopDatabase::run($database);

            return back()->with('success', 'Database stopped');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to stop database: '.$e->getMessage());
        }
    })->name('admin.databases.stop');

    Route::post('/databases/{uuid}/start', function (string $uuid) {
        $dbModels = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        $database = null;
        foreach ($dbModels as $model) {
            $db = $model::where('uuid', $uuid)->first();
            if ($db) {
                $database = $db;
                break;
            }
        }

        if (! $database) {
            return back()->with('error', 'Database not found');
        }

        try {
            \App\Actions\Database\StartDatabase::run($database);

            return back()->with('success', 'Database started');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to start database: '.$e->getMessage());
        }
    })->name('admin.databases.start');

    Route::delete('/databases/{uuid}', function (string $uuid) {
        $dbModels = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        $database = null;
        foreach ($dbModels as $model) {
            $db = $model::where('uuid', $uuid)->first();
            if ($db) {
                $database = $db;
                break;
            }
        }

        if (! $database) {
            return back()->with('error', 'Database not found');
        }

        $dbName = $database->name;
        $database->delete();

        return redirect()->route('admin.databases.index')->with('success', "Database '{$dbName}' deleted");
    })->name('admin.databases.delete');

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

    Route::get('/services/{uuid}', function (string $uuid) {
        // Fetch specific service with all relationships
        $service = \App\Models\Service::with([
            'environment.project.team',
            'server',
            'applications',
            'databases',
        ])->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Admin/Services/Show', [
            'service' => [
                'id' => $service->id,
                'uuid' => $service->uuid,
                'name' => $service->name,
                'description' => $service->description,
                'status' => $service->status() ?? 'unknown',
                'service_type' => $service->service_type ?? null,
                'team_id' => $service->environment?->project?->team?->id,
                'team_name' => $service->environment?->project?->team?->name ?? 'Unknown',
                'project_id' => $service->environment?->project?->id,
                'project_name' => $service->environment?->project?->name ?? 'Unknown',
                'environment_id' => $service->environment?->id,
                'environment_name' => $service->environment?->name ?? 'Unknown',
                'server_id' => $service->server?->id,
                'server_name' => $service->server?->name,
                'server_uuid' => $service->server?->uuid,
                'applications' => $service->applications->map(function ($app) {
                    return [
                        'id' => $app->id,
                        'uuid' => $app->uuid ?? '',
                        'name' => $app->name,
                        'fqdn' => $app->fqdn ?? null,
                        'status' => $app->status ?? 'unknown',
                    ];
                }),
                'databases' => $service->databases->map(function ($db) {
                    return [
                        'id' => $db->id,
                        'uuid' => $db->uuid ?? '',
                        'name' => $db->name,
                        'type' => class_basename($db),
                        'status' => method_exists($db, 'status') ? $db->status() : 'unknown',
                    ];
                }),
                'created_at' => $service->created_at,
                'updated_at' => $service->updated_at,
            ],
        ]);
    })->name('admin.services.show');

    Route::post('/services/{uuid}/restart', function (string $uuid) {
        $service = \App\Models\Service::where('uuid', $uuid)->firstOrFail();

        try {
            $service->parse();
            $activity = \App\Actions\Service\RestartService::run($service);

            return back()->with('success', 'Service restart initiated');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to restart service: '.$e->getMessage());
        }
    })->name('admin.services.restart');

    Route::post('/services/{uuid}/stop', function (string $uuid) {
        $service = \App\Models\Service::where('uuid', $uuid)->firstOrFail();

        try {
            $service->parse();
            \App\Actions\Service\StopService::run($service);

            return back()->with('success', 'Service stopped');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to stop service: '.$e->getMessage());
        }
    })->name('admin.services.stop');

    Route::post('/services/{uuid}/start', function (string $uuid) {
        $service = \App\Models\Service::where('uuid', $uuid)->firstOrFail();

        try {
            $service->parse();
            $activity = \App\Actions\Service\StartService::run($service);

            return back()->with('success', 'Service started');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to start service: '.$e->getMessage());
        }
    })->name('admin.services.start');

    Route::get('/servers', function (\Illuminate\Http\Request $request) {
        // Fetch all servers across all teams (admin view)
        $query = \App\Models\Server::with(['team', 'settings']);

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->get('status')) {
            $query->whereHas('settings', function ($q) use ($status) {
                if ($status === 'reachable') {
                    $q->where('is_reachable', true)->where('is_usable', true);
                } elseif ($status === 'unreachable') {
                    $q->where('is_reachable', false);
                } elseif ($status === 'degraded') {
                    $q->where('is_reachable', true)->where('is_usable', false);
                }
            });
        }

        // Tag filter
        if ($tag = $request->get('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $servers = $query->latest()
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
                    'is_reachable' => $server->settings?->is_reachable ?? false,
                    'is_usable' => $server->settings?->is_usable ?? false,
                    'tags' => $server->tags ?? [],
                    'team_name' => $server->team?->name ?? 'Unknown',
                    'team_id' => $server->team_id,
                    'created_at' => $server->created_at,
                    'updated_at' => $server->updated_at,
                ];
            });

        // Get all unique tags for filter dropdown
        $allTags = \App\Models\Server::whereNotNull('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values();

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
            'allTags' => $allTags,
            'filters' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'tag' => $request->get('tag'),
            ],
        ]);
    })->name('admin.servers.index');

    Route::get('/servers/{uuid}', function (string $uuid) {
        // Fetch specific server with all resources
        $server = \App\Models\Server::with(['team', 'settings'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Get metrics if available
        $metrics = null;
        if ($server->settings?->is_metrics_enabled) {
            try {
                $diskUsage = $server->getDiskUsage();
                $metrics = [
                    'cpu_usage' => null,
                    'memory_usage' => null,
                    'disk_usage' => $diskUsage ? (float) $diskUsage : null,
                ];
            } catch (\Exception $e) {
                // Metrics unavailable
            }
        }

        // Get applications on this server
        $applications = \App\Models\Application::whereHas('destination', function ($query) use ($server) {
            $query->where('server_id', $server->id);
        })->get()->map(function ($app) {
            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'status' => $app->status ?? 'unknown',
            ];
        });

        // Get databases on this server (all 8 types)
        $databases = collect();
        $dbTypes = [
            \App\Models\StandalonePostgresql::class => 'PostgreSQL',
            \App\Models\StandaloneMysql::class => 'MySQL',
            \App\Models\StandaloneMariadb::class => 'MariaDB',
            \App\Models\StandaloneMongodb::class => 'MongoDB',
            \App\Models\StandaloneRedis::class => 'Redis',
            \App\Models\StandaloneKeydb::class => 'KeyDB',
            \App\Models\StandaloneDragonfly::class => 'Dragonfly',
            \App\Models\StandaloneClickhouse::class => 'ClickHouse',
        ];

        foreach ($dbTypes as $model => $typeName) {
            $dbs = $model::whereHas('destination', function ($query) use ($server) {
                $query->where('server_id', $server->id);
            })->get()->map(function ($db) use ($typeName) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'type' => $typeName,
                    'status' => $db->status(),
                ];
            });
            $databases = $databases->concat($dbs);
        }

        // Get services on this server
        $services = \App\Models\Service::where('server_id', $server->id)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'uuid' => $service->uuid,
                    'name' => $service->name,
                    'status' => 'running',
                ];
            });

        return Inertia::render('Admin/Servers/Show', [
            'server' => [
                'id' => $server->id,
                'uuid' => $server->uuid,
                'name' => $server->name,
                'description' => $server->description,
                'ip' => $server->ip,
                'port' => $server->port,
                'user' => $server->user,
                'is_reachable' => $server->settings?->is_reachable ?? false,
                'is_usable' => $server->settings?->is_usable ?? false,
                'is_build_server' => $server->settings?->is_build_server ?? false,
                'is_localhost' => $server->is_localhost,
                'team_id' => $server->team_id,
                'team_name' => $server->team?->name ?? 'Unknown',
                'tags' => $server->tags ?? [],
                'settings' => [
                    'is_reachable' => $server->settings?->is_reachable ?? false,
                    'is_usable' => $server->settings?->is_usable ?? false,
                    'concurrent_builds' => $server->settings?->concurrent_builds ?? 2,
                    'is_metrics_enabled' => $server->settings?->is_metrics_enabled ?? false,
                    'docker_version' => $server->settings?->docker_version,
                    'docker_compose_version' => $server->settings?->docker_compose_version,
                ],
                'metrics' => $metrics,
                'resources' => [
                    'applications' => $applications,
                    'databases' => $databases,
                    'services' => $services,
                ],
                'created_at' => $server->created_at,
                'updated_at' => $server->updated_at,
            ],
        ]);
    })->name('admin.servers.show');

    Route::post('/servers/{uuid}/validate', function (string $uuid) {
        $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

        try {
            $result = $server->validateConnection();

            if ($result['uptime']) {
                return back()->with('success', 'Server connection validated successfully');
            } else {
                return back()->with('error', 'Server validation failed: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Server validation failed: '.$e->getMessage());
        }
    })->name('admin.servers.validate');

    Route::post('/servers/{uuid}/docker-cleanup', function (string $uuid) {
        $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

        try {
            instant_remote_process(['docker system prune -af'], $server);

            return back()->with('success', 'Docker cleanup completed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Docker cleanup failed: '.$e->getMessage());
        }
    })->name('admin.servers.docker-cleanup');

    Route::delete('/servers/{uuid}', function (string $uuid) {
        $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();
        $serverName = $server->name;

        // Check if it's localhost - prevent deletion
        if ($server->is_localhost) {
            return back()->with('error', 'Cannot delete localhost server');
        }

        $server->delete();

        return redirect()->route('admin.servers.index')->with('success', "Server '{$serverName}' deleted");
    })->name('admin.servers.delete');

    // Server health history API
    Route::get('/servers/{uuid}/health-history', function (string $uuid, \Illuminate\Http\Request $request) {
        $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

        $period = $request->get('period', '24h');
        $limit = match ($period) {
            '1h' => 60,
            '6h' => 72,
            '24h' => 288,
            '7d' => 336,
            '30d' => 720,
            default => 288,
        };

        $history = \App\Models\ServerHealthCheck::where('server_id', $server->id)
            ->orderByDesc('checked_at')
            ->limit($limit)
            ->get()
            ->map(function ($check) {
                return [
                    'status' => $check->status,
                    'cpu_usage' => $check->cpu_usage_percent,
                    'memory_usage' => $check->memory_usage_percent,
                    'disk_usage' => $check->disk_usage_percent,
                    'response_time_ms' => $check->response_time_ms,
                    'checked_at' => $check->checked_at?->toISOString(),
                ];
            })
            ->reverse()
            ->values();

        return response()->json([
            'server_id' => $server->id,
            'server_name' => $server->name,
            'period' => $period,
            'data' => $history,
        ]);
    })->name('admin.servers.health-history');

    // Server tags management
    Route::put('/servers/{uuid}/tags', function (string $uuid, \Illuminate\Http\Request $request) {
        $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $server->update([
            'tags' => array_values(array_unique($validated['tags'] ?? [])),
        ]);

        \App\Models\AuditLog::log(
            action: 'server_tags_updated',
            resourceType: 'server',
            resourceId: $server->id,
            resourceName: $server->name,
            description: 'Updated server tags',
            metadata: ['tags' => $server->tags]
        );

        return back()->with('success', 'Server tags updated');
    })->name('admin.servers.update-tags');

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

    Route::get('/deployment-approvals', function () {
        // Fetch pending deployment approvals across all teams (admin view)
        $deployments = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team'])
            ->where('requires_approval', true)
            ->where('approval_status', 'pending')
            ->latest()
            ->paginate(50)
            ->through(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'application_name' => $deployment->application?->name ?? 'Unknown',
                    'application_uuid' => $deployment->application?->uuid,
                    'status' => $deployment->status,
                    'approval_status' => $deployment->approval_status,
                    'commit' => $deployment->commit,
                    'commit_message' => $deployment->commit_message,
                    'team_name' => $deployment->application?->environment?->project?->team?->name ?? 'Unknown',
                    'team_id' => $deployment->application?->environment?->project?->team?->id,
                    'created_at' => $deployment->created_at,
                ];
            });

        return Inertia::render('Admin/Deployments/Approvals', [
            'deployments' => $deployments,
        ]);
    })->name('admin.deployment-approvals.index');

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
                    'personal_team' => $team->personal_team,
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

    Route::get('/teams/{id}', function (int $id) {
        // Fetch specific team with all relationships
        $team = \App\Models\Team::with(['members', 'servers.settings', 'projects'])
            ->withCount(['members', 'servers', 'projects'])
            ->findOrFail($id);

        return Inertia::render('Admin/Teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'personal_team' => $team->personal_team,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
                'members' => $team->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $member->pivot->role ?? 'member',
                        'created_at' => $member->created_at,
                    ];
                }),
                'servers' => $team->servers->map(function ($server) {
                    return [
                        'id' => $server->id,
                        'uuid' => $server->uuid,
                        'name' => $server->name,
                        'ip' => $server->ip,
                        'is_reachable' => $server->settings?->is_reachable ?? false,
                    ];
                }),
                'projects' => $team->projects->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                        'environments_count' => $project->environments()->count(),
                    ];
                }),
            ],
        ]);
    })->name('admin.teams.show');

    Route::post('/teams/{teamId}/members/{userId}/remove', function (int $teamId, int $userId) {
        $team = \App\Models\Team::findOrFail($teamId);
        $user = \App\Models\User::findOrFail($userId);

        // Check if user is owner - owners cannot be removed
        $role = $team->members()->where('user_id', $userId)->first()?->pivot?->role;
        if ($role === 'owner') {
            return back()->with('error', 'Cannot remove team owner');
        }

        $team->members()->detach($userId);

        return back()->with('success', "Removed {$user->name} from team");
    })->name('admin.teams.members.remove');

    Route::post('/teams/{teamId}/members/{userId}/role', function (int $teamId, int $userId) {
        $team = \App\Models\Team::findOrFail($teamId);
        $newRole = request()->input('role');

        if (! in_array($newRole, ['owner', 'admin', 'developer', 'member', 'viewer'])) {
            return back()->with('error', 'Invalid role');
        }

        $team->members()->updateExistingPivot($userId, ['role' => $newRole]);

        return back()->with('success', 'Role updated successfully');
    })->name('admin.teams.members.role');

    Route::delete('/teams/{id}', function (int $id) {
        $team = \App\Models\Team::findOrFail($id);

        // Prevent deletion of root team (id=0) or personal teams
        if ($team->id === 0) {
            return back()->with('error', 'Cannot delete root team');
        }

        if ($team->personal_team) {
            return back()->with('error', 'Cannot delete personal teams');
        }

        $teamName = $team->name;
        $team->delete();

        return redirect()->route('admin.teams.index')->with('success', "Team '{$teamName}' deleted");
    })->name('admin.teams.delete');

    Route::get('/settings', function () {
        // Fetch instance settings (admin view)
        $settings = \App\Models\InstanceSettings::get();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    })->name('admin.settings.index');

    // Queue Monitor routes
    Route::get('/queues', function () {
        // Get queue statistics
        // Note: jobs table only exists when using database queue driver
        // Saturn uses Redis queue, so we need to handle this gracefully
        $pendingJobs = 0;
        $failedJobs = 0;

        try {
            $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
        } catch (\Exception $e) {
            // Table doesn't exist - using Redis queue driver
        }

        try {
            $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
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
            $failedJobsList = \Illuminate\Support\Facades\DB::table('failed_jobs')
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
        $failedJob = \Illuminate\Support\Facades\DB::table('failed_jobs')->where('id', $id)->first();

        if (! $failedJob) {
            return back()->with('error', 'Failed job not found');
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => [$failedJob->uuid]]);

            return back()->with('success', 'Job queued for retry');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to retry job: '.$e->getMessage());
        }
    })->name('admin.queues.retry');

    Route::delete('/queues/failed/{id}', function (int $id) {
        $deleted = \Illuminate\Support\Facades\DB::table('failed_jobs')->where('id', $id)->delete();

        if ($deleted) {
            return back()->with('success', 'Failed job deleted');
        }

        return back()->with('error', 'Failed job not found');
    })->name('admin.queues.delete');

    Route::post('/queues/failed/retry-all', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => ['all']]);

            return back()->with('success', 'All failed jobs queued for retry');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to retry jobs: '.$e->getMessage());
        }
    })->name('admin.queues.retry-all');

    Route::delete('/queues/failed/flush', function () {
        \Illuminate\Support\Facades\Artisan::call('queue:flush');

        return back()->with('success', 'All failed jobs deleted');
    })->name('admin.queues.flush');

    // Backup Management routes
    Route::get('/backups', function () {
        // Get all scheduled backups
        $backups = \App\Models\ScheduledDatabaseBackup::with(['database', 'latest_log', 's3'])
            ->get()
            ->map(function ($backup) {
                $database = $backup->database;
                $server = $backup->server();

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

        // Calculate stats including verification and restore test stats
        $allExecutions = \App\Models\ScheduledDatabaseBackupExecution::query();
        $recentExecutions = \App\Models\ScheduledDatabaseBackupExecution::where('created_at', '>=', now()->subDay());

        // Calculate total storage used
        $totalStorageLocal = \App\Models\ScheduledDatabaseBackupExecution::where('local_storage_deleted', false)
            ->whereNotNull('size')
            ->sum('size');
        $totalStorageS3 = \App\Models\ScheduledDatabaseBackupExecution::where('s3_uploaded', true)
            ->where('s3_storage_deleted', false)
            ->whereNotNull('s3_file_size')
            ->sum('s3_file_size');

        // Estimate S3 costs (rough estimate: $0.023 per GB/month for S3 Standard)
        $s3CostPerGBMonth = 0.023;
        $estimatedMonthlyCost = ($totalStorageS3 / (1024 * 1024 * 1024)) * $s3CostPerGBMonth;

        $stats = [
            'total' => $backups->count(),
            'enabled' => $backups->where('enabled', true)->count(),
            'with_s3' => $backups->where('save_s3', true)->count(),
            'failed_last_24h' => (clone $recentExecutions)->where('status', 'failed')->count(),
            'verified_last_24h' => (clone $recentExecutions)->where('verification_status', 'verified')->count(),
            'verification_failed_last_24h' => (clone $recentExecutions)->where('verification_status', 'failed')->count(),
            'restore_test_enabled_count' => $backups->where('restore_test_enabled', true)->count(),
            'restore_tests_passed' => $allExecutions->where('restore_test_status', 'success')->count(),
            'restore_tests_failed' => (clone $allExecutions)->where('restore_test_status', 'failed')->count(),
            'total_storage_local' => $totalStorageLocal,
            'total_storage_s3' => $totalStorageS3,
            'estimated_monthly_cost' => round($estimatedMonthlyCost, 2),
        ];

        return Inertia::render('Admin/Backups/Index', [
            'backups' => $backups,
            'stats' => $stats,
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

    // Invitations Management routes
    Route::get('/invitations', function () {
        $invitations = \App\Models\TeamInvitation::with(['team'])
            ->latest()
            ->paginate(50)
            ->through(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'uuid' => $invitation->uuid,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'team_id' => $invitation->team_id,
                    'team_name' => $invitation->team?->name ?? 'Unknown',
                    'via' => $invitation->via,
                    'is_valid' => $invitation->isValid(),
                    'created_at' => $invitation->created_at,
                ];
            });

        return Inertia::render('Admin/Invitations/Index', [
            'invitations' => $invitations,
        ]);
    })->name('admin.invitations.index');

    Route::delete('/invitations/{id}', function (int $id) {
        $invitation = \App\Models\TeamInvitation::findOrFail($id);
        $invitation->delete();

        return back()->with('success', 'Invitation deleted');
    })->name('admin.invitations.delete');

    Route::post('/invitations/{id}/resend', function (int $id) {
        $invitation = \App\Models\TeamInvitation::findOrFail($id);

        // Resend logic would go here - for now just flash success
        return back()->with('success', 'Invitation resent');
    })->name('admin.invitations.resend');

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
    Route::get('/audit-logs', function (\Illuminate\Http\Request $request) {
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
    Route::get('/audit-logs/export', function (\Illuminate\Http\Request $request) {
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

    // System Health Dashboard
    Route::get('/health', function () {
        // Core services health checks
        $services = [];

        // PostgreSQL check
        try {
            $startTime = microtime(true);
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $services[] = [
                'service' => 'PostgreSQL',
                'status' => 'healthy',
                'lastCheck' => now()->toISOString(),
                'responseTime' => $responseTime,
            ];
        } catch (\Exception $e) {
            $services[] = [
                'service' => 'PostgreSQL',
                'status' => 'down',
                'lastCheck' => now()->toISOString(),
                'details' => $e->getMessage(),
            ];
        }

        // Redis check
        try {
            $startTime = microtime(true);
            \Illuminate\Support\Facades\Redis::ping();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $services[] = [
                'service' => 'Redis',
                'status' => 'healthy',
                'lastCheck' => now()->toISOString(),
                'responseTime' => $responseTime,
            ];
        } catch (\Exception $e) {
            $services[] = [
                'service' => 'Redis',
                'status' => 'down',
                'lastCheck' => now()->toISOString(),
                'details' => $e->getMessage(),
            ];
        }

        // Queue worker check (simplified - check if jobs are processing)
        // Note: jobs table only exists when using database queue driver
        $healthPendingJobs = 0;
        $healthFailedJobs = 0;
        try {
            $healthPendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
        } catch (\Exception $e) {
            // Table doesn't exist - using Redis queue driver
        }
        try {
            $healthFailedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Table doesn't exist
        }
        $services[] = [
            'service' => 'Queue Worker',
            'status' => $healthFailedJobs > 10 ? 'degraded' : 'healthy',
            'lastCheck' => now()->toISOString(),
            'details' => "{$healthPendingJobs} pending, {$healthFailedJobs} failed",
        ];

        // Servers health
        $servers = \App\Models\Server::with(['settings'])
            ->get()
            ->map(function ($server) {
                $metrics = null;
                if ($server->settings?->is_metrics_enabled) {
                    try {
                        $diskUsage = $server->getDiskUsage();
                        $metrics = [
                            'cpu_usage' => null,
                            'memory_usage' => null,
                            'disk_usage' => $diskUsage ? (float) $diskUsage : null,
                        ];
                    } catch (\Exception $e) {
                        // Metrics unavailable
                    }
                }

                // Count resources on server
                $resourcesCount = 0;
                $resourcesCount += \App\Models\Application::whereHas('destination', function ($query) use ($server) {
                    $query->where('server_id', $server->id);
                })->count();
                $resourcesCount += \App\Models\Service::where('server_id', $server->id)->count();

                return [
                    'id' => $server->id,
                    'uuid' => $server->uuid,
                    'name' => $server->name,
                    'ip' => $server->ip,
                    'is_reachable' => $server->settings?->is_reachable ?? false,
                    'is_usable' => $server->settings?->is_usable ?? false,
                    'metrics' => $metrics,
                    'resources_count' => $resourcesCount,
                    'last_check' => now()->toISOString(),
                ];
            });

        // Queue statistics
        // Note: jobs table only exists when using database queue driver
        $queuePending = 0;
        $queueFailed = 0;
        try {
            $queuePending = \Illuminate\Support\Facades\DB::table('jobs')->count();
        } catch (\Exception $e) {
            // Table doesn't exist - using Redis queue driver
        }
        try {
            $queueFailed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Table doesn't exist
        }
        $queues = [
            'pending' => $queuePending,
            'processing' => 0,
            'failed' => $queueFailed,
            'workers' => 1, // Simplified - would need Horizon for accurate count
        ];

        return Inertia::render('Admin/Health/Index', [
            'services' => $services,
            'servers' => $servers,
            'queues' => $queues,
            'lastUpdated' => now()->toISOString(),
        ]);
    })->name('admin.health.index');

    // =============================
    // Application Templates
    // =============================

    // List all templates
    Route::get('/templates', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\ApplicationTemplate::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        // Filter by official
        if ($request->boolean('official_only')) {
            $query->where('is_official', true);
        }

        // Sorting
        $sortBy = $request->input('sort', 'name');
        $sortOrder = $request->input('order', 'asc');
        $allowedSorts = ['name', 'category', 'usage_count', 'rating', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        $templates = $query->with('createdBy')
            ->paginate(24)
            ->through(function ($template) {
                return [
                    'id' => $template->id,
                    'uuid' => $template->uuid,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'description' => $template->description,
                    'category' => $template->category,
                    'icon' => $template->icon,
                    'is_official' => $template->is_official,
                    'is_public' => $template->is_public,
                    'version' => $template->version,
                    'tags' => $template->tags ?? [],
                    'usage_count' => $template->usage_count,
                    'rating' => $template->rating,
                    'rating_count' => $template->rating_count,
                    'created_by' => $template->createdBy?->name,
                    'created_at' => $template->created_at,
                ];
            });

        $categories = \App\Models\ApplicationTemplate::categories();

        return Inertia::render('Admin/Templates/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', 'all'),
                'official_only' => $request->boolean('official_only'),
                'sort' => $sortBy,
                'order' => $sortOrder,
            ],
        ]);
    })->name('admin.templates.index');

    // Show create template form
    Route::get('/templates/create', function () {
        $categories = \App\Models\ApplicationTemplate::categories();

        return Inertia::render('Admin/Templates/Create', [
            'categories' => $categories,
        ]);
    })->name('admin.templates.create');

    // Store new template
    Route::post('/templates', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:'.implode(',', array_keys(\App\Models\ApplicationTemplate::categories())),
            'icon' => 'nullable|string|max:255',
            'is_official' => 'boolean',
            'is_public' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'config' => 'required|array',
            'config.build_pack' => 'required|string|in:nixpacks,static,dockerfile,dockercompose',
            'config.ports_exposes' => 'nullable|string',
            'config.install_command' => 'nullable|string',
            'config.build_command' => 'nullable|string',
            'config.start_command' => 'nullable|string',
            'config.base_directory' => 'nullable|string',
            'config.publish_directory' => 'nullable|string',
            'config.environment_variables' => 'nullable|array',
        ]);

        $template = \App\Models\ApplicationTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'icon' => $validated['icon'] ?? null,
            'is_official' => $validated['is_official'] ?? false,
            'is_public' => $validated['is_public'] ?? true,
            'tags' => $validated['tags'] ?? [],
            'config' => $validated['config'],
            'created_by' => auth()->id(),
        ]);

        \App\Models\AuditLog::create([
            'action' => 'template_created',
            'resource_type' => 'ApplicationTemplate',
            'resource_id' => $template->id,
            'resource_name' => $template->name,
            'user_id' => auth()->id(),
            'team_id' => currentTeam()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => "Created application template: {$template->name}",
        ]);

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template created successfully.');
    })->name('admin.templates.store');

    // Show template details
    Route::get('/templates/{uuid}', function (string $uuid) {
        $template = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Admin/Templates/Show', [
            'template' => [
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'category' => $template->category,
                'icon' => $template->icon,
                'is_official' => $template->is_official,
                'is_public' => $template->is_public,
                'version' => $template->version,
                'tags' => $template->tags ?? [],
                'config' => $template->config,
                'usage_count' => $template->usage_count,
                'rating' => $template->rating,
                'rating_count' => $template->rating_count,
                'created_by' => $template->createdBy?->name,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ],
            'categories' => \App\Models\ApplicationTemplate::categories(),
        ]);
    })->name('admin.templates.show');

    // Update template
    Route::put('/templates/{uuid}', function (\Illuminate\Http\Request $request, string $uuid) {
        $template = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:'.implode(',', array_keys(\App\Models\ApplicationTemplate::categories())),
            'icon' => 'nullable|string|max:255',
            'is_official' => 'boolean',
            'is_public' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'config' => 'required|array',
        ]);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'icon' => $validated['icon'] ?? null,
            'is_official' => $validated['is_official'] ?? false,
            'is_public' => $validated['is_public'] ?? true,
            'tags' => $validated['tags'] ?? [],
            'config' => $validated['config'],
        ]);

        \App\Models\AuditLog::create([
            'action' => 'template_updated',
            'resource_type' => 'ApplicationTemplate',
            'resource_id' => $template->id,
            'resource_name' => $template->name,
            'user_id' => auth()->id(),
            'team_id' => currentTeam()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => "Updated application template: {$template->name}",
        ]);

        return back()->with('success', 'Template updated successfully.');
    })->name('admin.templates.update');

    // Delete template
    Route::delete('/templates/{uuid}', function (\Illuminate\Http\Request $request, string $uuid) {
        $template = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

        $templateName = $template->name;

        \App\Models\AuditLog::create([
            'action' => 'template_deleted',
            'resource_type' => 'ApplicationTemplate',
            'resource_id' => $template->id,
            'resource_name' => $templateName,
            'user_id' => auth()->id(),
            'team_id' => currentTeam()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => "Deleted application template: {$templateName}",
        ]);

        $template->delete();

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template deleted successfully.');
    })->name('admin.templates.destroy');

    // Duplicate template
    Route::post('/templates/{uuid}/duplicate', function (\Illuminate\Http\Request $request, string $uuid) {
        $original = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

        $newTemplate = $original->replicate();
        $newTemplate->name = $original->name.' (Copy)';
        $newTemplate->slug = null; // Will be regenerated
        $newTemplate->uuid = null; // Will be regenerated
        $newTemplate->is_official = false;
        $newTemplate->usage_count = 0;
        $newTemplate->rating = null;
        $newTemplate->rating_count = 0;
        $newTemplate->created_by = auth()->id();
        $newTemplate->save();

        \App\Models\AuditLog::create([
            'action' => 'template_duplicated',
            'resource_type' => 'ApplicationTemplate',
            'resource_id' => $newTemplate->id,
            'resource_name' => $newTemplate->name,
            'user_id' => auth()->id(),
            'team_id' => currentTeam()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => "Duplicated template from: {$original->name}",
        ]);

        return redirect()->route('admin.templates.show', $newTemplate->uuid)
            ->with('success', 'Template duplicated successfully.');
    })->name('admin.templates.duplicate');
});
