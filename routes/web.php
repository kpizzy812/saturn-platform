<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\UploadController;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

// SuperAdmin routes
require __DIR__.'/superadmin.php';

// Admin routes - now using Inertia
Route::get('/admin', fn () => Inertia::render('Admin/Index'))->name('admin.dashboard')->middleware(['auth', 'verified']);

// Auth controller routes
Route::post('/forgot-password', [Controller::class, 'forgot_password'])->name('password.forgot')->middleware('throttle:forgot-password');
Route::get('/realtime', [Controller::class, 'realtime_test'])->middleware('auth');
Route::get('/verify', [Controller::class, 'verify'])->middleware('auth')->name('verify.email');
Route::get('/email/verify/{id}/{hash}', [Controller::class, 'email_verify'])->middleware(['auth'])->name('verify.verify');
Route::middleware(['throttle:login'])->group(function () {
    Route::get('/auth/link', [Controller::class, 'link'])->name('auth.link');
});

Route::get('/auth/{provider}/redirect', [OauthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/{provider}/callback', [OauthController::class, 'callback'])->name('auth.callback');

// Legacy routes - redirect to new Inertia routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Root redirect to dashboard
    Route::get('/', fn () => redirect()->route('dashboard'));

    // Force password reset
    Route::middleware(['throttle:force-password-reset'])->group(function () {
        Route::get('/force-password-reset', fn () => Inertia::render('Auth/ForcePasswordReset'))
            ->name('auth.force-password-reset');
    });

    // Legacy onboarding redirect
    Route::get('/onboarding', fn () => redirect()->route('boarding.index'))->name('onboarding');

    // Legacy subscription redirects
    Route::get('/subscription', fn () => redirect()->route('settings.billing'))->name('subscription.show');
    Route::get('/subscription/new', fn () => redirect()->route('settings.billing'))->name('subscription.legacy');

    // Legacy settings redirects
    Route::get('/settings', fn () => redirect()->route('settings.index'))->name('settings.legacy');
    Route::get('/settings/advanced', fn () => redirect()->route('settings.advanced'))->name('settings.advanced.legacy');
    Route::get('/settings/updates', fn () => redirect()->route('settings.updates'))->name('settings.updates.legacy');
    Route::get('/settings/backup', fn () => redirect()->route('settings.backup'))->name('settings.backup.legacy');
    Route::get('/settings/email', fn () => redirect()->route('settings.email'))->name('settings.email.legacy');
    Route::get('/settings/oauth', fn () => redirect()->route('settings.oauth'))->name('settings.oauth.legacy');

    // Legacy team redirects
    Route::get('/team', fn () => redirect()->route('settings.team'))->name('team.index');
    Route::get('/team/members', fn () => redirect()->route('settings.team.members'))->name('team.member.index');
    Route::prefix('invitations')->group(function () {
        Route::get('/{uuid}', [Controller::class, 'acceptInvitation'])->name('team.invitation.accept');
        Route::get('/{uuid}/revoke', [Controller::class, 'revokeInvitation'])->name('team.invitation.revoke');
    });

    // Legacy project redirects
    Route::get('/projects', fn () => redirect()->route('projects.index'))->name('project.index');
    Route::prefix('project/{project_uuid}')->group(function () {
        Route::get('/', fn (string $project_uuid) => redirect()->route('projects.show', $project_uuid))->name('project.show');
        Route::get('/edit', fn (string $project_uuid) => redirect()->route('projects.edit', $project_uuid))->name('project.edit');
    });

    // Legacy server redirects
    Route::get('/servers', fn () => redirect()->route('servers.index'))->name('server.index');
    Route::prefix('server/{server_uuid}')->group(function () {
        Route::get('/', fn (string $server_uuid) => redirect()->route('servers.show', $server_uuid))->name('server.show');
        Route::get('/proxy', fn (string $server_uuid) => redirect()->route('servers.proxy.index', $server_uuid))->name('server.proxy');
        Route::get('/terminal', fn (string $server_uuid) => redirect()->route('servers.terminal', $server_uuid))->name('server.command');
    });

    // Legacy security redirects
    Route::get('/security/private-key', fn () => redirect()->route('settings.ssh-keys'))->name('security.private-key.index');
    Route::get('/security/api-tokens', fn () => redirect()->route('settings.api-tokens'))->name('security.api-tokens');
});

// File upload/download routes
Route::middleware(['auth'])->group(function () {
    Route::post('/upload/backup/{databaseUuid}', [UploadController::class, 'upload'])->name('upload.backup');
    Route::get('/download/backup/{executionId}', function () {
        try {
            $user = auth()->user();
            $team = $user->currentTeam();
            if (is_null($team)) {
                return response()->json(['message' => 'Team not found.'], 404);
            }
            if ($user->isAdminFromSession() === false) {
                return response()->json(['message' => 'Only team admins/owners can download backups.'], 403);
            }
            $exeuctionId = request()->route('executionId');
            $execution = ScheduledDatabaseBackupExecution::where('id', $exeuctionId)->firstOrFail();
            $execution_team_id = $execution->scheduledDatabaseBackup->database->team()?->id;
            if ($team->id !== 0) {
                if (is_null($execution_team_id)) {
                    return response()->json(['message' => 'Team not found.'], 404);
                }
                if ($team->id !== $execution_team_id) {
                    return response()->json(['message' => 'Permission denied.'], 403);
                }
                if (is_null($execution)) {
                    return response()->json(['message' => 'Backup not found.'], 404);
                }
            }
            $filename = data_get($execution, 'filename');
            if ($execution->scheduledDatabaseBackup->database->getMorphClass() === \App\Models\ServiceDatabase::class) {
                $server = $execution->scheduledDatabaseBackup->database->service->destination->server;
            } else {
                $server = $execution->scheduledDatabaseBackup->database->destination->server;
            }
            if (is_null($server)) {
                return response()->json(['message' => 'Server not found.'], 404);
            }

            $privateKeyLocation = $server->privateKey->getKeyLocation();
            $disk = Storage::build([
                'driver' => 'sftp',
                'host' => $server->ip,
                'port' => (int) $server->port,
                'username' => $server->user,
                'privateKey' => $privateKeyLocation,
                'root' => '/',
            ]);
            if (! $disk->exists($filename)) {
                if ($execution->scheduledDatabaseBackup->disable_local_backup === true && $execution->scheduledDatabaseBackup->save_s3 === true) {
                    return response()->json(['message' => 'Backup not available locally, but available on S3.'], 404);
                }

                return response()->json(['message' => 'Backup not found locally on the server.'], 404);
            }

            return new StreamedResponse(function () use ($disk, $filename) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                $stream = $disk->readStream($filename);
                if ($stream === false || is_null($stream)) {
                    abort(500, 'Failed to open stream for the requested file.');
                }
                while (! feof($stream)) {
                    echo fread($stream, 2048);
                    flush();
                }

                fclose($stream);
            }, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.basename($filename).'"',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    })->name('download.backup');

});
// React/Inertia Auth Routes (new frontend - public)
Route::prefix('auth')->middleware(['web'])->group(function () {
    // Guest routes (login, register, password reset)
    Route::middleware(['guest'])->group(function () {
        Route::get('/login', function () {
            return \Inertia\Inertia::render('Auth/Login');
        })->name('auth.login');

        Route::get('/register', function () {
            return \Inertia\Inertia::render('Auth/Register');
        })->name('auth.register');

        Route::get('/forgot-password', function () {
            return \Inertia\Inertia::render('Auth/ForgotPassword', [
                'status' => session('status'),
            ]);
        })->name('auth.forgot-password');

        Route::get('/reset-password/{token}', function (string $token) {
            return \Inertia\Inertia::render('Auth/ResetPassword', [
                'token' => $token,
                'email' => request()->query('email'),
            ]);
        })->name('auth.reset-password');
    });

    Route::get('/verify-email', function () {
        return \Inertia\Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
            'email' => auth()->user()?->email,
        ]);
    })->name('auth.verify-email');

    Route::get('/invitations/{uuid}', function (string $uuid) {
        // Find the invitation - implement your invitation logic here
        $invitation = [
            'id' => $uuid,
            'team_name' => 'Example Team',
            'inviter_name' => 'John Doe',
            'inviter_email' => 'john@example.com',
            'role' => 'Member',
            'expires_at' => now()->addDays(7)->toISOString(),
        ];

        return \Inertia\Inertia::render('Auth/AcceptInvite', [
            'invitation' => $invitation,
            'isAuthenticated' => auth()->check(),
        ]);
    })->name('auth.accept-invite');
});

// React/Inertia Routes (new frontend)
Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $projects = \App\Models\Project::ownedByCurrentTeam()
            ->with(['environments.applications', 'environments.services'])
            ->get();

        return \Inertia\Inertia::render('Dashboard', [
            'projects' => $projects,
        ]);
    })->name('dashboard');

    // Auth Routes (require authentication)
    Route::prefix('auth')->group(function () {
        Route::get('/two-factor/setup', function () {
            // Generate QR code and manual entry code
            // In a real implementation, use Laravel Fortify or similar
            $qrCode = '<svg><!-- QR code SVG here --></svg>';
            $manualEntryCode = 'ABCD1234EFGH5678';

            return \Inertia\Inertia::render('Auth/TwoFactor/Setup', [
                'qrCode' => $qrCode,
                'manualEntryCode' => $manualEntryCode,
            ]);
        })->name('auth.two-factor.setup');

        Route::get('/two-factor/verify', function () {
            return \Inertia\Inertia::render('Auth/TwoFactor/Verify');
        })->name('auth.two-factor.verify')->withoutMiddleware(['verified']);

        Route::get('/oauth/connect', function () {
            // Get user's connected OAuth providers
            $providers = [
                [
                    'name' => 'GitHub',
                    'provider' => 'github',
                    'connected' => false,
                ],
                [
                    'name' => 'Google',
                    'provider' => 'google',
                    'connected' => false,
                ],
                [
                    'name' => 'GitLab',
                    'provider' => 'gitlab',
                    'connected' => false,
                ],
            ];

            return \Inertia\Inertia::render('Auth/OAuth/Connect', [
                'providers' => $providers,
            ]);
        })->name('auth.oauth.connect');

        Route::get('/onboarding', function () {
            return \Inertia\Inertia::render('Auth/Onboarding/Index', [
                'userName' => auth()->user()->name,
                'templates' => [],
            ]);
        })->name('auth.onboarding');
    });

    // Server routes
    require __DIR__.'/web/servers.php';

    // Project routes
    require __DIR__.'/web/projects.php';

    // DEMO: Mock project for preview (no database required)
    Route::get('/demo/project', function () {
        $mockProject = [
            'id' => 1,
            'uuid' => 'demo-project-uuid',
            'name' => 'Saturn Demo Project',
            'description' => 'Demo project to preview the Railway-style UI',
            'environments' => [
                [
                    'id' => 1,
                    'uuid' => 'env-production',
                    'name' => 'production',
                    'applications' => [
                        [
                            'id' => 1,
                            'uuid' => 'app-api-001',
                            'name' => 'API Server',
                            'description' => 'Main backend API',
                            'status' => 'running',
                            'fqdn' => 'api.saturn.app',
                            'git_repository' => 'github.com/saturn/api',
                            'git_branch' => 'main',
                            'created_at' => now()->subDays(30)->toISOString(),
                            'updated_at' => now()->subHours(2)->toISOString(),
                        ],
                        [
                            'id' => 2,
                            'uuid' => 'app-web-002',
                            'name' => 'Web Frontend',
                            'description' => 'React frontend application',
                            'status' => 'running',
                            'fqdn' => 'app.saturn.app',
                            'git_repository' => 'github.com/saturn/web',
                            'git_branch' => 'main',
                            'created_at' => now()->subDays(25)->toISOString(),
                            'updated_at' => now()->subMinutes(30)->toISOString(),
                        ],
                        [
                            'id' => 3,
                            'uuid' => 'app-worker-003',
                            'name' => 'Background Worker',
                            'description' => 'Job processing worker',
                            'status' => 'running',
                            'fqdn' => null,
                            'git_repository' => 'github.com/saturn/worker',
                            'git_branch' => 'main',
                            'created_at' => now()->subDays(20)->toISOString(),
                            'updated_at' => now()->subHours(1)->toISOString(),
                        ],
                    ],
                    'databases' => [
                        [
                            'id' => 1,
                            'uuid' => 'db-postgres-001',
                            'name' => 'PostgreSQL Main',
                            'database_type' => 'postgresql',
                            'status' => 'running',
                            'created_at' => now()->subDays(30)->toISOString(),
                            'updated_at' => now()->subHours(5)->toISOString(),
                        ],
                        [
                            'id' => 2,
                            'uuid' => 'db-redis-002',
                            'name' => 'Redis Cache',
                            'database_type' => 'redis',
                            'status' => 'running',
                            'created_at' => now()->subDays(28)->toISOString(),
                            'updated_at' => now()->subHours(3)->toISOString(),
                        ],
                    ],
                    'services' => [
                        [
                            'id' => 1,
                            'uuid' => 'svc-minio-001',
                            'name' => 'MinIO Storage',
                            'description' => 'S3-compatible object storage',
                            'status' => 'running',
                            'created_at' => now()->subDays(15)->toISOString(),
                            'updated_at' => now()->subHours(8)->toISOString(),
                        ],
                    ],
                ],
                [
                    'id' => 2,
                    'uuid' => 'env-staging',
                    'name' => 'staging',
                    'applications' => [
                        [
                            'id' => 4,
                            'uuid' => 'app-api-staging',
                            'name' => 'API Server (Staging)',
                            'description' => 'Staging API',
                            'status' => 'stopped',
                            'fqdn' => 'api-staging.saturn.app',
                            'git_repository' => 'github.com/saturn/api',
                            'git_branch' => 'develop',
                            'created_at' => now()->subDays(10)->toISOString(),
                            'updated_at' => now()->subDays(1)->toISOString(),
                        ],
                    ],
                    'databases' => [],
                    'services' => [],
                ],
            ],
            'created_at' => now()->subDays(30)->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        return \Inertia\Inertia::render('Projects/Show', [
            'project' => $mockProject,
        ]);
    })->name('demo.project');

    // DEMO: All pages index for easy navigation
    Route::get('/demo', function () {
        return \Inertia\Inertia::render('Demo/Index');
    })->name('demo.index');

    // Templates
    Route::get('/templates', function () {
        return \Inertia\Inertia::render('Templates/Index');
    })->name('templates.index');

    Route::get('/templates/{id}', function (string $id) {
        return \Inertia\Inertia::render('Templates/Show');
    })->name('templates.show');

    Route::get('/templates/{id}/deploy', function (string $id) {
        return \Inertia\Inertia::render('Templates/Deploy');
    })->name('templates.deploy');

    // Service routes
    require __DIR__.'/web/services.php';

    // Application routes
    require __DIR__.'/web/applications.php';

    // Subscription Routes
    Route::get('/subscription', function () {
        return \Inertia\Inertia::render('Subscription/Index');
    })->name('subscription.index');

    Route::get('/subscription/plans', function () {
        return \Inertia\Inertia::render('Subscription/Plans');
    })->name('subscription.plans');

    Route::get('/subscription/checkout', function () {
        return \Inertia\Inertia::render('Subscription/Checkout');
    })->name('subscription.checkout');

    Route::get('/subscription/success', function () {
        return \Inertia\Inertia::render('Subscription/Success');
    })->name('subscription.success');

    // Settings routes
    require __DIR__.'/web/settings.php';

    // Database routes
    require __DIR__.'/web/databases.php';

    // Admin routes
    require __DIR__.'/web/admin.php';

    // Observability routes
    Route::get('/observability', function () {
        return \Inertia\Inertia::render('Observability/Index');
    })->name('observability.index');

    Route::get('/observability/metrics', function () {
        return \Inertia\Inertia::render('Observability/Metrics');
    })->name('observability.metrics');

    Route::get('/observability/logs', function () {
        $team = auth()->user()->currentTeam();

        // Get all resources from the team
        $applications = \App\Models\Application::whereHas('environment.project.team', function ($query) use ($team) {
            $query->where('id', $team->id);
        })->select('uuid', 'name', 'status')->get()->map(fn ($app) => [
            'uuid' => $app->uuid,
            'name' => $app->name,
            'type' => 'application',
            'status' => $app->status,
        ]);

        $services = \App\Models\Service::whereHas('environment.project.team', function ($query) use ($team) {
            $query->where('id', $team->id);
        })->select('uuid', 'name')->get()->map(fn ($svc) => [
            'uuid' => $svc->uuid,
            'name' => $svc->name,
            'type' => 'service',
            'status' => 'running',
        ]);

        // Get all database types
        $databaseModels = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        $databases = collect();
        foreach ($databaseModels as $model) {
            $dbs = $model::whereHas('environment.project.team', function ($query) use ($team) {
                $query->where('id', $team->id);
            })->select('uuid', 'name', 'status')->get()->map(fn ($db) => [
                'uuid' => $db->uuid,
                'name' => $db->name,
                'type' => 'database',
                'status' => $db->status,
            ]);
            $databases = $databases->merge($dbs);
        }

        $resources = $applications->merge($services)->merge($databases)->values();

        return \Inertia\Inertia::render('Observability/Logs', [
            'resources' => $resources,
        ]);
    })->name('observability.logs');

    Route::get('/observability/traces', function () {
        return \Inertia\Inertia::render('Observability/Traces');
    })->name('observability.traces');

    Route::get('/observability/alerts', function () {
        return \Inertia\Inertia::render('Observability/Alerts');
    })->name('observability.alerts');

    // Volumes routes
    Route::get('/volumes', function () {
        return \Inertia\Inertia::render('Volumes/Index');
    })->name('volumes.index');

    Route::get('/volumes/create', function () {
        return \Inertia\Inertia::render('Volumes/Create');
    })->name('volumes.create');

    Route::get('/volumes/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Volumes/Show');
    })->name('volumes.show');

    // Storage routes
    Route::get('/storage/backups', function () {
        return \Inertia\Inertia::render('Storage/Backups');
    })->name('storage.backups');

    Route::get('/storage/snapshots', function () {
        return \Inertia\Inertia::render('Storage/Snapshots');
    })->name('storage.snapshots');

    // Domains routes
    Route::get('/domains', function () {
        return \Inertia\Inertia::render('Domains/Index');
    })->name('domains.index');

    Route::get('/domains/add', function () {
        return \Inertia\Inertia::render('Domains/Add');
    })->name('domains.add');

    Route::get('/domains/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Domains/Show');
    })->name('domains.show');

    Route::get('/domains/{uuid}/redirects', function (string $uuid) {
        return \Inertia\Inertia::render('Domains/Redirects');
    })->name('domains.redirects');

    // SSL routes
    Route::get('/ssl', function () {
        return \Inertia\Inertia::render('SSL/Index');
    })->name('ssl.index');

    Route::get('/ssl/upload', function () {
        return \Inertia\Inertia::render('SSL/Upload');
    })->name('ssl.upload');

    // Cron Jobs routes
    Route::get('/cron-jobs', function () {
        return \Inertia\Inertia::render('CronJobs/Index');
    })->name('cron-jobs.index');

    Route::get('/cron-jobs/create', function () {
        return \Inertia\Inertia::render('CronJobs/Create');
    })->name('cron-jobs.create');

    Route::get('/cron-jobs/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('CronJobs/Show');
    })->name('cron-jobs.show');

    // Scheduled Tasks routes
    Route::get('/scheduled-tasks', function () {
        return \Inertia\Inertia::render('ScheduledTasks/Index');
    })->name('scheduled-tasks.index');

    Route::get('/scheduled-tasks/history', function () {
        return \Inertia\Inertia::render('ScheduledTasks/History');
    })->name('scheduled-tasks.history');

    // Deployments routes
    Route::get('/deployments', function () {
        return \Inertia\Inertia::render('Deployments/Index');
    })->name('deployments.index');

    Route::get('/deployments/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Deployments/Show');
    })->name('deployments.show');

    Route::get('/deployments/{uuid}/logs', function (string $uuid) {
        return \Inertia\Inertia::render('Deployments/BuildLogs');
    })->name('deployments.logs');

    // JSON endpoint for deployment logs (for XHR requests)
    Route::get('/deployments/{uuid}/logs/json', function (string $uuid) {
        $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();
        if (! $deployment) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        // Verify the deployment belongs to the current team
        $application = $deployment->application;
        if (! $application || $application->team()?->id !== currentTeam()->id) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        $logs = $deployment->logs;
        $parsedLogs = [];

        if ($logs) {
            $parsedLogs = json_decode($logs, true) ?: [];
        }

        return response()->json([
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => $deployment->status,
            'logs' => $parsedLogs,
        ]);
    })->name('deployments.logs.json');

    // JSON endpoint for application container logs (for XHR requests)
    Route::get('/applications/{uuid}/logs/json', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $server = $application->destination->server;
        if (! $server) {
            return response()->json(['message' => 'Server not found.'], 404);
        }

        // Get container logs
        try {
            $containers = getCurrentApplicationContainerStatus($server, $application->id, 0, true);

            if (empty($containers)) {
                return response()->json([
                    'container_logs' => 'No containers found for this application.',
                    'containers' => [],
                ]);
            }

            // Get logs from first container
            $containerName = $containers[0]['Names'] ?? null;
            if ($containerName) {
                $logs = instant_remote_process(["docker logs -n 200 {$containerName} 2>&1"], $server);

                return response()->json([
                    'container_logs' => $logs,
                    'containers' => $containers,
                ]);
            }

            return response()->json([
                'container_logs' => 'Container not found.',
                'containers' => $containers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch logs: '.$e->getMessage(),
            ], 500);
        }
    })->name('applications.logs.json');

    // Activity routes
    Route::get('/activity', function () {
        return \Inertia\Inertia::render('Activity/Index');
    })->name('activity.index');

    Route::get('/activity/timeline', function () {
        return \Inertia\Inertia::render('Activity/Timeline');
    })->name('activity.timeline');

    // Notifications routes
    Route::get('/notifications', function () {
        $team = auth()->user()->currentTeam();
        $notifications = \App\Models\UserNotification::where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get()
            ->map(fn ($n) => $n->toFrontendArray());

        return \Inertia\Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    })->name('notifications.index');

    Route::post('/notifications/{id}/read', function (string $id) {
        $team = auth()->user()->currentTeam();
        $notification = \App\Models\UserNotification::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return back();
    })->name('notifications.read');

    Route::post('/notifications/read-all', function () {
        $team = auth()->user()->currentTeam();
        \App\Models\UserNotification::where('team_id', $team->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return back();
    })->name('notifications.read-all');

    Route::delete('/notifications/{id}', function (string $id) {
        $team = auth()->user()->currentTeam();
        $notification = \App\Models\UserNotification::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return back();
    })->name('notifications.destroy');

    // CLI routes
    Route::get('/cli/setup', function () {
        return \Inertia\Inertia::render('CLI/Setup');
    })->name('cli.setup');

    Route::get('/cli/commands', function () {
        return \Inertia\Inertia::render('CLI/Commands');
    })->name('cli.commands');

    // Integrations routes
    Route::get('/integrations/webhooks', function () {
        $team = auth()->user()->currentTeam();
        $webhooks = $team->webhooks()
            ->with(['deliveries' => function ($query) {
                $query->limit(5);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return \Inertia\Inertia::render('Integrations/Webhooks', [
            'webhooks' => $webhooks,
            'availableEvents' => \App\Models\TeamWebhook::availableEvents(),
        ]);
    })->name('integrations.webhooks');

    Route::post('/integrations/webhooks', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
        ]);

        $webhook = $team->webhooks()->create($validated);

        return redirect()->back()->with('success', 'Webhook created successfully.');
    })->name('integrations.webhooks.store');

    Route::put('/integrations/webhooks/{uuid}', function (\Illuminate\Http\Request $request, string $uuid) {
        $team = auth()->user()->currentTeam();
        $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'enabled' => 'sometimes|boolean',
        ]);

        $webhook->update($validated);

        return redirect()->back()->with('success', 'Webhook updated successfully.');
    })->name('integrations.webhooks.update');

    Route::delete('/integrations/webhooks/{uuid}', function (string $uuid) {
        $team = auth()->user()->currentTeam();
        $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

        $webhook->delete();

        return redirect()->back()->with('success', 'Webhook deleted successfully.');
    })->name('integrations.webhooks.destroy');

    Route::post('/integrations/webhooks/{uuid}/toggle', function (string $uuid) {
        $team = auth()->user()->currentTeam();
        $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

        $webhook->update(['enabled' => ! $webhook->enabled]);

        $status = $webhook->enabled ? 'enabled' : 'disabled';

        return redirect()->back()->with('success', "Webhook {$status} successfully.");
    })->name('integrations.webhooks.toggle');

    Route::post('/integrations/webhooks/{uuid}/test', function (string $uuid) {
        $team = auth()->user()->currentTeam();
        $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

        $delivery = \App\Models\WebhookDelivery::create([
            'team_webhook_id' => $webhook->id,
            'event' => 'test.event',
            'status' => 'pending',
            'payload' => [
                'event' => 'test.event',
                'message' => 'This is a test webhook delivery from Saturn.',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        \App\Jobs\SendTeamWebhookJob::dispatch($webhook, $delivery);

        return redirect()->back()->with('success', 'Test webhook queued for delivery.');
    })->name('integrations.webhooks.test');

    Route::post('/integrations/webhooks/{uuid}/deliveries/{deliveryUuid}/retry', function (string $uuid, string $deliveryUuid) {
        $team = auth()->user()->currentTeam();
        $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();
        $delivery = $webhook->deliveries()->where('uuid', $deliveryUuid)->firstOrFail();

        $delivery->update(['status' => 'pending']);
        \App\Jobs\SendTeamWebhookJob::dispatch($webhook, $delivery);

        return redirect()->back()->with('success', 'Retry queued for delivery.');
    })->name('integrations.webhooks.retry');

    // Onboarding routes
    Route::get('/onboarding/welcome', function () {
        return \Inertia\Inertia::render('Onboarding/Welcome');
    })->name('onboarding.welcome');

    Route::get('/onboarding/connect-repo', function () {
        // Get GitHub Apps for current team
        $githubApps = \App\Models\GithubApp::where(function ($query) {
            $query->where('team_id', currentTeam()->id)
                ->orWhere('is_system_wide', true);
        })->whereNotNull('app_id')->get();

        return \Inertia\Inertia::render('Onboarding/ConnectRepo', [
            'githubApps' => $githubApps->map(fn ($app) => [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'installation_id' => $app->installation_id,
            ]),
        ]);
    })->name('onboarding.connect-repo');

    // GitHub App API routes (for web session)
    Route::get('/web-api/github-apps/{github_app_id}/repositories', function ($github_app_id) {
        $githubApp = \App\Models\GithubApp::where('id', $github_app_id)
            ->where(function ($query) {
                $query->where('team_id', currentTeam()->id)
                    ->orWhere('is_system_wide', true);
            })
            ->firstOrFail();

        $token = generateGithubInstallationToken($githubApp);
        $repositories = collect();
        $page = 1;
        $maxPages = 100;

        while ($page <= $maxPages) {
            $response = \Illuminate\Support\Facades\Http::GitHub($githubApp->api_url, $token)
                ->timeout(20)
                ->retry(3, 200, throw: false)
                ->get('/installation/repositories', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->status() !== 200) {
                return response()->json([
                    'message' => $response->json()['message'] ?? 'Failed to load repositories',
                ], $response->status());
            }

            $json = $response->json();
            $repos = $json['repositories'] ?? [];

            if (empty($repos)) {
                break;
            }

            $repositories = $repositories->concat($repos);
            $page++;
        }

        return response()->json([
            'repositories' => $repositories->sortBy('name')->values(),
        ]);
    })->name('web-api.github-apps.repositories');

    Route::get('/web-api/github-apps/{github_app_id}/repositories/{owner}/{repo}/branches', function ($github_app_id, $owner, $repo) {
        $githubApp = \App\Models\GithubApp::where('id', $github_app_id)
            ->where(function ($query) {
                $query->where('team_id', currentTeam()->id)
                    ->orWhere('is_system_wide', true);
            })
            ->firstOrFail();

        $token = generateGithubInstallationToken($githubApp);

        $response = \Illuminate\Support\Facades\Http::GitHub($githubApp->api_url, $token)
            ->timeout(20)
            ->retry(3, 200, throw: false)
            ->get("/repos/{$owner}/{$repo}/branches");

        if ($response->status() !== 200) {
            return response()->json([
                'message' => 'Error loading branches from GitHub.',
                'error' => $response->json('message'),
            ], $response->status());
        }

        return response()->json([
            'branches' => $response->json(),
        ]);
    })->name('web-api.github-apps.branches');

    // Support routes
    Route::get('/support', function () {
        return \Inertia\Inertia::render('Support/Index');
    })->name('support.index');

    // Sources routes
    Route::prefix('sources')->group(function () {
        Route::get('/', function () {
            $githubApps = \App\Models\GithubApp::ownedByCurrentTeam()->get();
            $gitlabApps = \App\Models\GitlabApp::ownedByCurrentTeam()->get();

            // Combine all sources with type indicator
            $sources = collect()
                ->concat($githubApps->map(fn ($app) => [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? null,
                    'name' => $app->name,
                    'type' => 'github',
                    'html_url' => $app->html_url,
                    'is_public' => $app->is_public,
                    'created_at' => $app->created_at,
                ]))
                ->concat($gitlabApps->map(fn ($app) => [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? null,
                    'name' => $app->name,
                    'type' => 'gitlab',
                    'html_url' => $app->html_url ?? null,
                    'is_public' => $app->public_key ? false : true,
                    'created_at' => $app->created_at,
                ]));

            return \Inertia\Inertia::render('Sources/Index', [
                'sources' => $sources,
            ]);
        })->name('sources.index');

        Route::prefix('github')->group(function () {
            Route::get('/', function () {
                $sources = \App\Models\GithubApp::ownedByCurrentTeam()->get()->map(fn ($app) => [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? null,
                    'name' => $app->name,
                    'html_url' => $app->html_url,
                    'api_url' => $app->api_url,
                    'app_id' => $app->app_id,
                    'installation_id' => $app->installation_id,
                    'is_public' => $app->is_public,
                    'is_system_wide' => $app->is_system_wide,
                    'created_at' => $app->created_at,
                    'updated_at' => $app->updated_at,
                ]);

                return \Inertia\Inertia::render('Sources/GitHub/Index', [
                    'sources' => $sources,
                ]);
            })->name('sources.github.index');

            Route::get('/create', function () {
                return \Inertia\Inertia::render('Sources/GitHub/Create');
            })->name('sources.github.create');

            Route::get('/{id}', function (int $id) {
                $source = \App\Models\GithubApp::ownedByCurrentTeam()->findOrFail($id);

                return \Inertia\Inertia::render('Sources/GitHub/Show', [
                    'source' => $source,
                ]);
            })->name('sources.github.show');

            Route::delete('/{id}', function (int $id) {
                $source = \App\Models\GithubApp::ownedByCurrentTeam()->findOrFail($id);
                $source->delete();

                return redirect()->route('sources.github.index')->with('success', 'GitHub App deleted successfully');
            })->name('sources.github.destroy');
        });

        Route::prefix('gitlab')->group(function () {
            Route::get('/', function () {
                $sources = \App\Models\GitlabApp::ownedByCurrentTeam()->get()->map(fn ($app) => [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? null,
                    'name' => $app->name,
                    'api_url' => $app->api_url,
                    'app_id' => $app->app_id,
                    'is_system_wide' => $app->is_system_wide,
                    'created_at' => $app->created_at,
                    'updated_at' => $app->updated_at,
                ]);

                return \Inertia\Inertia::render('Sources/GitLab/Index', [
                    'sources' => $sources,
                ]);
            })->name('sources.gitlab.index');

            Route::get('/create', function () {
                return \Inertia\Inertia::render('Sources/GitLab/Create');
            })->name('sources.gitlab.create');

            Route::get('/{id}', function (int $id) {
                $source = \App\Models\GitlabApp::ownedByCurrentTeam()->findOrFail($id);

                return \Inertia\Inertia::render('Sources/GitLab/Show', [
                    'source' => $source,
                ]);
            })->name('sources.gitlab.show');

            Route::delete('/{id}', function (int $id) {
                $source = \App\Models\GitlabApp::ownedByCurrentTeam()->findOrFail($id);
                $source->delete();

                return redirect()->route('sources.gitlab.index')->with('success', 'GitLab App deleted successfully');
            })->name('sources.gitlab.destroy');
        });

        Route::get('/bitbucket', function () {
            // Bitbucket integration not yet fully implemented
            return \Inertia\Inertia::render('Sources/Bitbucket/Index', [
                'sources' => [],
                'message' => 'Bitbucket integration coming soon',
            ]);
        })->name('sources.bitbucket.index');
    });

    // Storage routes
    Route::get('/storage', function () {
        return \Inertia\Inertia::render('Storage/Index');
    })->name('storage.index');

    Route::get('/storage/create', function () {
        return \Inertia\Inertia::render('Storage/Create');
    })->name('storage.create');

    Route::post('/storage', function (\Illuminate\Http\Request $request) {
        return redirect()->route('storage.index')->with('success', 'Storage created successfully');
    })->name('storage.store');

    Route::get('/storage/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Storage/Show', ['uuid' => $uuid]);
    })->name('storage.show');

    Route::get('/storage/{uuid}/settings', function (string $uuid) {
        return \Inertia\Inertia::render('Storage/Settings', ['uuid' => $uuid]);
    })->name('storage.settings');

    Route::get('/storage/{uuid}/backups', function (string $uuid) {
        return \Inertia\Inertia::render('Storage/Backups', ['uuid' => $uuid]);
    })->name('storage.backups.show');

    Route::get('/storage/{uuid}/snapshots', function (string $uuid) {
        return \Inertia\Inertia::render('Storage/Snapshots', ['uuid' => $uuid]);
    })->name('storage.snapshots.show');

    // Destinations routes
    Route::get('/destinations', function () {
        return \Inertia\Inertia::render('Destinations/Index');
    })->name('destinations.index');

    Route::get('/destinations/create', function () {
        return \Inertia\Inertia::render('Destinations/Create');
    })->name('destinations.create');

    Route::post('/destinations', function (\Illuminate\Http\Request $request) {
        return redirect()->route('destinations.index')->with('success', 'Destination created successfully');
    })->name('destinations.store');

    Route::get('/destinations/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Destinations/Show', ['uuid' => $uuid]);
    })->name('destinations.show');

    // Shared Variables routes
    Route::get('/shared-variables', function () {
        return \Inertia\Inertia::render('SharedVariables/Index');
    })->name('shared-variables.index');

    Route::get('/shared-variables/create', function () {
        return \Inertia\Inertia::render('SharedVariables/Create');
    })->name('shared-variables.create');

    Route::post('/shared-variables', function (\Illuminate\Http\Request $request) {
        return redirect()->route('shared-variables.index')->with('success', 'Shared variable created successfully');
    })->name('shared-variables.store');

    Route::get('/shared-variables/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('SharedVariables/Show', ['uuid' => $uuid]);
    })->name('shared-variables.show');

    // Templates additional routes
    Route::get('/templates/categories', function () {
        return \Inertia\Inertia::render('Templates/Categories');
    })->name('templates.categories');

    Route::get('/templates/submit', function () {
        return \Inertia\Inertia::render('Templates/Submit');
    })->name('templates.submit');

    Route::post('/templates/submit', function (\Illuminate\Http\Request $request) {
        return redirect()->route('templates.index')->with('success', 'Template submitted successfully');
    })->name('templates.submit.store');

    // Tags routes
    Route::get('/tags', function () {
        return \Inertia\Inertia::render('Tags/Index');
    })->name('tags.index');

    Route::get('/tags/{tagName}', function (string $tagName) {
        return \Inertia\Inertia::render('Tags/Show', ['tagName' => $tagName]);
    })->name('tags.show');

    // Notifications additional routes
    // NOTE: preferences route must be before {uuid} to avoid conflict
    Route::get('/notifications/preferences', function () {
        $user = auth()->user();
        $preferences = \App\Models\UserNotificationPreference::getOrCreateForUser($user->id);

        return \Inertia\Inertia::render('Notifications/Preferences', [
            'preferences' => $preferences->toFrontendFormat(),
        ]);
    })->name('notifications.preferences');

    Route::get('/notifications/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Notifications/NotificationDetail', ['uuid' => $uuid]);
    })->name('notifications.detail');

    // Activity additional routes
    Route::get('/activity/project/{projectUuid}', function (string $projectUuid) {
        return \Inertia\Inertia::render('Activity/ProjectActivity', ['projectUuid' => $projectUuid]);
    })->name('activity.project');

    Route::get('/activity/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Activity/Show', ['uuid' => $uuid]);
    })->name('activity.show');

    // Environments additional routes
    Route::get('/environments/{environmentUuid}/secrets', function (string $environmentUuid) {
        return \Inertia\Inertia::render('Environments/Secrets', ['environmentUuid' => $environmentUuid]);
    })->name('environments.secrets');

    Route::get('/environments/{environmentUuid}/variables', function (string $environmentUuid) {
        return \Inertia\Inertia::render('Environments/Variables', ['environmentUuid' => $environmentUuid]);
    })->name('environments.variables');

    // Boarding route
    Route::get('/boarding', function () {
        $servers = \App\Models\Server::ownedByCurrentTeam()->get(['id', 'name', 'ip']);
        $privateKeys = \App\Models\PrivateKey::ownedByCurrentTeam()->get(['id', 'name']);

        // Get GitHub Apps for current team
        $githubApps = \App\Models\GithubApp::where(function ($query) {
            $query->where('team_id', currentTeam()->id)
                ->orWhere('is_system_wide', true);
        })->whereNotNull('app_id')->get();

        return \Inertia\Inertia::render('Boarding/Index', [
            'userName' => auth()->user()->name,
            'existingServers' => $servers,
            'privateKeys' => $privateKeys,
            'githubApps' => $githubApps->map(fn ($app) => [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'installation_id' => $app->installation_id,
            ]),
        ]);
    })->name('boarding.index');

    Route::post('/boarding/skip', function () {
        $team = currentTeam();
        $team->show_boarding = false;
        $team->save();
        refreshSession($team);

        return redirect()->route('dashboard');
    })->name('boarding.skip');

    Route::post('/boarding/deploy', function (\Illuminate\Http\Request $request) {
        $team = currentTeam();
        if (! $team) {
            return redirect()->route('dashboard')->with('error', 'Please select a team first');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'git_repository' => 'required|string',
            'git_branch' => 'nullable|string',
            'server_id' => 'required|integer',
            'github_app_id' => 'nullable|integer',
        ]);

        // Find server
        $server = \App\Models\Server::ownedByCurrentTeam()
            ->where('id', $validated['server_id'])
            ->first();

        if (! $server) {
            return redirect()->back()->withErrors(['server_id' => 'Server not found']);
        }

        $destination = $server->destinations()->first();
        if (! $destination) {
            return redirect()->back()->withErrors(['server_id' => 'Server has no destinations configured']);
        }

        // Find or create default project
        $project = \App\Models\Project::ownedByCurrentTeam()->first();
        if (! $project) {
            $project = \App\Models\Project::create([
                'name' => 'Default Project',
                'team_id' => $team->id,
            ]);
        }

        // Find or create default environment
        $environment = $project->environments()->first();
        if (! $environment) {
            $environment = \App\Models\Environment::create([
                'name' => 'production',
                'project_id' => $project->id,
            ]);
        }

        // Create application
        $application = new \App\Models\Application;
        $application->name = $validated['name'];
        $application->git_repository = $validated['git_repository'];
        $application->git_branch = $validated['git_branch'] ?? 'main';
        $application->build_pack = 'nixpacks';
        $application->environment_id = $environment->id;
        $application->destination_id = $destination->id;
        $application->destination_type = $destination->getMorphClass();
        $application->ports_exposes = '80'; // Will be auto-detected during deployment
        $application->auto_inject_database_url = true; // Enable auto-inject for Railway-like experience

        // Set source (GitHub App or public)
        $githubAppId = $validated['github_app_id'] ?? 0;
        $githubApp = \App\Models\GithubApp::find($githubAppId);
        if ($githubApp) {
            $application->source_type = \App\Models\GithubApp::class;
            $application->source_id = $githubApp->id;
        }

        $application->save();

        // Auto-generate domain
        if (empty($application->fqdn)) {
            $application->fqdn = generateUrl(server: $server, random: $application->uuid);
            $application->save();
        }

        // Auto-inject database URLs from linked databases in the same environment
        $application->autoInjectDatabaseUrl();

        // Queue deployment
        queue_application_deployment(
            application: $application,
            deployment_uuid: (string) \Illuminate\Support\Str::uuid(),
            force_rebuild: false,
        );

        // Mark onboarding as complete
        $team->show_boarding = false;
        $team->save();
        refreshSession($team);

        return redirect()->route('applications.show', $application->uuid)
            ->with('success', 'Application created and deployment started!');
    })->name('boarding.deploy');

    // Error pages (for preview)
    Route::get('/errors/404', function () {
        return \Inertia\Inertia::render('Errors/404');
    })->name('errors.404');

    Route::get('/errors/500', function () {
        return \Inertia\Inertia::render('Errors/500');
    })->name('errors.500');

    Route::get('/errors/403', function () {
        return \Inertia\Inertia::render('Errors/403');
    })->name('errors.403');

    Route::get('/errors/maintenance', function () {
        return \Inertia\Inertia::render('Errors/Maintenance');
    })->name('errors.maintenance');
});

Route::any('/{any}', function () {
    if (auth()->user()) {
        return redirect(RouteServiceProvider::HOME);
    }

    return redirect()->route('login');
})->where('any', '.*');
