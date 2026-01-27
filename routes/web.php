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
        $team = auth()->user()->currentTeam();
        $servers = $team->servers;
        $applications = \App\Models\Application::ownedByCurrentTeam()->get();
        $services = \App\Models\Service::ownedByCurrentTeam()->get();

        $metricsOverview = [
            ['label' => 'Servers', 'value' => $servers->count(), 'status' => 'healthy'],
            ['label' => 'Applications', 'value' => $applications->count(), 'status' => 'healthy'],
            ['label' => 'Services', 'value' => $services->count(), 'status' => 'healthy'],
            ['label' => 'Active Deployments', 'value' => \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applications->pluck('id'))->where('status', 'in_progress')->count(), 'status' => 'info'],
        ];

        $serviceHealth = $servers->map(fn ($server) => [
            'id' => $server->id,
            'name' => $server->name,
            'status' => $server->settings?->is_reachable ? 'healthy' : 'degraded',
            'uptime' => '—',
            'type' => 'server',
        ])->concat($applications->map(fn ($app) => [
            'id' => $app->id,
            'name' => $app->name,
            'status' => $app->status === 'running' ? 'healthy' : ($app->status === 'stopped' ? 'down' : 'degraded'),
            'uptime' => '—',
            'type' => 'application',
        ]))->values();

        $recentAlerts = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applications->pluck('id'))
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'type' => 'deployment_failed',
                'message' => 'Deployment failed for '.($d->application_name ?? 'app'),
                'severity' => 'error',
                'timestamp' => $d->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('Observability/Index', [
            'metricsOverview' => $metricsOverview,
            'services' => $serviceHealth,
            'recentAlerts' => $recentAlerts,
        ]);
    })->name('observability.index');

    Route::get('/observability/metrics', function () {
        $team = auth()->user()->currentTeam();
        $servers = $team->servers()->select('id', 'uuid', 'name')->get();

        return \Inertia\Inertia::render('Observability/Metrics', [
            'servers' => $servers->map(fn ($s) => [
                'uuid' => $s->uuid,
                'name' => $s->name,
            ])->values()->toArray(),
        ]);
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
        $team = auth()->user()->currentTeam();

        // Use Spatie Activity Log as traces source
        $activities = \Spatie\Activitylog\Models\Activity::where(function ($q) {
            // Activities related to team's resources
            $q->whereIn('subject_type', [
                \App\Models\Application::class,
                \App\Models\Service::class,
                \App\Models\Server::class,
            ]);
        })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $traces = $activities->map(function ($activity) {
            $properties = $activity->properties->toArray();
            $duration = $properties['duration'] ?? rand(10, 500);

            return [
                'id' => (string) $activity->id,
                'name' => $activity->description ?? $activity->event ?? 'Unknown',
                'duration' => is_numeric($duration) ? (int) $duration : 0,
                'timestamp' => $activity->created_at?->toISOString(),
                'status' => ($properties['status'] ?? null) === 'failed' ? 'error' : 'success',
                'services' => [class_basename($activity->subject_type ?? 'Unknown')],
                'spans' => [
                    [
                        'id' => (string) $activity->id.'-main',
                        'name' => $activity->description ?? 'main',
                        'service' => class_basename($activity->subject_type ?? 'Unknown'),
                        'duration' => is_numeric($duration) ? (int) $duration : 0,
                        'startTime' => 0,
                        'status' => ($properties['status'] ?? null) === 'failed' ? 'error' : 'success',
                    ],
                ],
            ];
        });

        return \Inertia\Inertia::render('Observability/Traces', [
            'traces' => $traces,
        ]);
    })->name('observability.traces');

    Route::get('/observability/alerts', function () {
        $alerts = \App\Models\Alert::ownedByCurrentTeam()->get()->map(fn ($alert) => [
            'id' => $alert->id,
            'uuid' => $alert->uuid,
            'name' => $alert->name,
            'metric' => $alert->metric,
            'condition' => $alert->condition,
            'threshold' => $alert->threshold,
            'duration' => $alert->duration,
            'enabled' => $alert->enabled,
            'channels' => $alert->channels ?? [],
            'triggered_count' => $alert->triggered_count,
            'last_triggered' => $alert->last_triggered_at?->toISOString(),
            'created_at' => $alert->created_at?->toISOString(),
        ]);

        $history = \App\Models\AlertHistory::whereIn('alert_id',
            \App\Models\Alert::ownedByCurrentTeam()->pluck('id')
        )->with('alert:id,name')
            ->orderByDesc('triggered_at')
            ->limit(50)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'alert_id' => $h->alert_id,
                'alert_name' => $h->alert?->name ?? 'Unknown',
                'triggered_at' => $h->triggered_at?->toISOString(),
                'resolved_at' => $h->resolved_at?->toISOString(),
                'value' => $h->value,
                'status' => $h->status,
            ]);

        return \Inertia\Inertia::render('Observability/Alerts', [
            'alerts' => $alerts,
            'history' => $history,
        ]);
    })->name('observability.alerts');

    // Alert CRUD routes
    Route::post('/observability/alerts', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'metric' => 'required|string|in:cpu,memory,disk,error_rate,response_time',
            'condition' => 'required|string|in:>,<,=',
            'threshold' => 'required|numeric',
            'duration' => 'required|integer|min:1',
            'channels' => 'nullable|array',
        ]);

        \App\Models\Alert::create([
            ...$validated,
            'team_id' => currentTeam()->id,
            'enabled' => true,
        ]);

        return redirect()->back()->with('success', 'Alert created successfully');
    })->name('observability.alerts.store');

    Route::put('/observability/alerts/{id}', function (\Illuminate\Http\Request $request, int $id) {
        $alert = \App\Models\Alert::ownedByCurrentTeam()->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'metric' => 'sometimes|string|in:cpu,memory,disk,error_rate,response_time',
            'condition' => 'sometimes|string|in:>,<,=',
            'threshold' => 'sometimes|numeric',
            'duration' => 'sometimes|integer|min:1',
            'enabled' => 'sometimes|boolean',
            'channels' => 'nullable|array',
        ]);

        $alert->update($validated);

        return redirect()->back()->with('success', 'Alert updated successfully');
    })->name('observability.alerts.update');

    Route::delete('/observability/alerts/{id}', function (int $id) {
        $alert = \App\Models\Alert::ownedByCurrentTeam()->where('id', $id)->firstOrFail();
        $alert->delete();

        return redirect()->back()->with('success', 'Alert deleted successfully');
    })->name('observability.alerts.destroy');

    // Volumes routes
    Route::get('/volumes', function () {
        $team = auth()->user()->currentTeam();

        // Collect resource IDs for team's applications, services, and databases
        $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
        $serviceAppIds = \App\Models\ServiceApplication::whereIn('service_id',
            \App\Models\Service::ownedByCurrentTeam()->pluck('id')
        )->pluck('id');
        $serviceDbIds = \App\Models\ServiceDatabase::whereIn('service_id',
            \App\Models\Service::ownedByCurrentTeam()->pluck('id')
        )->pluck('id');

        $volumes = \App\Models\LocalPersistentVolume::where(function ($q) use ($applicationIds, $serviceAppIds, $serviceDbIds) {
            $q->where(function ($q) use ($applicationIds) {
                $q->where('resource_type', 'App\\Models\\Application')
                    ->whereIn('resource_id', $applicationIds);
            })->orWhere(function ($q) use ($serviceAppIds) {
                $q->where('resource_type', 'App\\Models\\ServiceApplication')
                    ->whereIn('resource_id', $serviceAppIds);
            })->orWhere(function ($q) use ($serviceDbIds) {
                $q->where('resource_type', 'App\\Models\\ServiceDatabase')
                    ->whereIn('resource_id', $serviceDbIds);
            });
        })->get()->map(fn ($vol) => [
            'id' => $vol->id,
            'uuid' => $vol->id,
            'name' => $vol->name ?? $vol->mount_path,
            'mountPath' => $vol->mount_path,
            'hostPath' => $vol->host_path,
            'resourceType' => class_basename($vol->resource_type),
            'resourceId' => $vol->resource_id,
            'created_at' => $vol->created_at?->toISOString(),
        ]);

        return \Inertia\Inertia::render('Volumes/Index', [
            'volumes' => $volumes,
        ]);
    })->name('volumes.index');

    Route::get('/volumes/create', function () {
        $applications = \App\Models\Application::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name')
            ->get()
            ->map(fn ($app) => ['uuid' => $app->uuid, 'name' => $app->name, 'type' => 'application']);
        $services = \App\Models\Service::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name')
            ->get()
            ->map(fn ($svc) => ['uuid' => $svc->uuid, 'name' => $svc->name, 'type' => 'service']);

        return \Inertia\Inertia::render('Volumes/Create', [
            'services' => $applications->merge($services)->values(),
        ]);
    })->name('volumes.create');

    Route::post('/volumes', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mount_path' => 'required|string',
            'host_path' => 'nullable|string',
            'resource_uuid' => 'required|string',
            'resource_type' => 'required|string|in:application,service',
        ]);

        $resource = null;
        if ($validated['resource_type'] === 'application') {
            $resource = \App\Models\Application::ownedByCurrentTeam()
                ->where('uuid', $validated['resource_uuid'])->firstOrFail();
            $resourceType = \App\Models\Application::class;
        } else {
            $resource = \App\Models\Service::ownedByCurrentTeam()
                ->where('uuid', $validated['resource_uuid'])->firstOrFail();
            $resourceType = \App\Models\Service::class;
        }

        \App\Models\LocalPersistentVolume::create([
            'name' => $validated['name'],
            'mount_path' => $validated['mount_path'],
            'host_path' => $validated['host_path'] ?? null,
            'resource_type' => $resourceType,
            'resource_id' => $resource->id,
        ]);

        return redirect()->route('volumes.index')->with('success', 'Volume created successfully');
    })->name('volumes.store');

    Route::get('/volumes/{id}', function (string $id) {
        // LocalPersistentVolume has no uuid field, use id
        $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
        $serviceAppIds = \App\Models\ServiceApplication::whereIn('service_id',
            \App\Models\Service::ownedByCurrentTeam()->pluck('id')
        )->pluck('id');
        $serviceDbIds = \App\Models\ServiceDatabase::whereIn('service_id',
            \App\Models\Service::ownedByCurrentTeam()->pluck('id')
        )->pluck('id');

        $vol = \App\Models\LocalPersistentVolume::where('id', $id)
            ->where(function ($q) use ($applicationIds, $serviceAppIds, $serviceDbIds) {
                $q->where(function ($q) use ($applicationIds) {
                    $q->where('resource_type', 'App\\Models\\Application')
                        ->whereIn('resource_id', $applicationIds);
                })->orWhere(function ($q) use ($serviceAppIds) {
                    $q->where('resource_type', 'App\\Models\\ServiceApplication')
                        ->whereIn('resource_id', $serviceAppIds);
                })->orWhere(function ($q) use ($serviceDbIds) {
                    $q->where('resource_type', 'App\\Models\\ServiceDatabase')
                        ->whereIn('resource_id', $serviceDbIds);
                });
            })->with('resource')->firstOrFail();

        $volume = [
            'id' => $vol->id,
            'uuid' => (string) $vol->id,
            'name' => $vol->name ?? $vol->mount_path,
            'description' => null,
            'size' => 0,
            'used' => 0,
            'status' => 'active',
            'storage_class' => 'standard',
            'mount_path' => $vol->mount_path,
            'host_path' => $vol->host_path,
            'attached_services' => $vol->resource ? [[
                'id' => $vol->resource->id ?? 0,
                'name' => $vol->resource->name ?? 'Unknown',
                'type' => class_basename($vol->resource_type),
            ]] : [],
            'created_at' => $vol->created_at?->toISOString(),
            'updated_at' => $vol->updated_at?->toISOString(),
        ];

        return \Inertia\Inertia::render('Volumes/Show', [
            'volume' => $volume,
            'snapshots' => [],
        ]);
    })->name('volumes.show');

    // Storage routes
    Route::get('/storage/backups', function () {
        $backups = \App\Models\ScheduledDatabaseBackup::ownedByCurrentTeam()
            ->with(['database', 's3', 'latest_log'])
            ->get()
            ->map(fn ($backup) => [
                'id' => $backup->id,
                'uuid' => $backup->uuid,
                'databaseName' => $backup->database?->name ?? 'Unknown',
                'databaseType' => class_basename($backup->database_type ?? ''),
                'frequency' => $backup->frequency,
                'enabled' => $backup->enabled ?? true,
                's3StorageName' => $backup->s3?->name,
                'lastStatus' => $backup->latest_log?->status ?? 'unknown',
                'lastRun' => $backup->latest_log?->created_at?->toISOString(),
                'created_at' => $backup->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('Storage/Backups', [
            'backups' => $backups,
        ]);
    })->name('storage.backups');

    Route::get('/storage/snapshots', function () {
        // Use ScheduledDatabaseBackupExecution as snapshot data
        $backupIds = \App\Models\ScheduledDatabaseBackup::ownedByCurrentTeam()->pluck('id');

        $snapshots = \App\Models\ScheduledDatabaseBackupExecution::whereIn('scheduled_database_backup_id', $backupIds)
            ->with('scheduledDatabaseBackup.database')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($exec) => [
                'id' => $exec->id,
                'name' => $exec->filename ?? ('backup-'.$exec->id),
                'size' => $exec->size ?? '—',
                'source_volume' => $exec->scheduledDatabaseBackup?->database?->name ?? 'Unknown',
                'status' => $exec->status,
                'created_at' => $exec->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('Storage/Snapshots', [
            'snapshots' => $snapshots,
        ]);
    })->name('storage.snapshots');

    // Domains routes
    Route::get('/domains', function () {
        $team = auth()->user()->currentTeam();
        $domains = collect();
        $id = 1;

        // Collect FQDNs from applications
        $applications = \App\Models\Application::ownedByCurrentTeam()->get();
        foreach ($applications as $app) {
            if (! $app->fqdn) {
                continue;
            }
            foreach (explode(',', $app->fqdn) as $index => $fqdn) {
                $fqdn = trim($fqdn);
                if (empty($fqdn)) {
                    continue;
                }
                $domains->push([
                    'id' => $id++,
                    'uuid' => $app->uuid.'-'.$index,
                    'domain' => preg_replace('#^https?://#', '', $fqdn),
                    'fullUrl' => $fqdn,
                    'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
                    'resourceName' => $app->name,
                    'resourceType' => 'application',
                    'resourceUuid' => $app->uuid,
                    'isPrimary' => $index === 0,
                    'created_at' => $app->created_at?->toISOString(),
                ]);
            }
        }

        // Collect FQDNs from service applications
        $services = \App\Models\Service::ownedByCurrentTeam()->with('applications')->get();
        foreach ($services as $service) {
            foreach ($service->applications as $svcApp) {
                if (! $svcApp->fqdn) {
                    continue;
                }
                foreach (explode(',', $svcApp->fqdn) as $index => $fqdn) {
                    $fqdn = trim($fqdn);
                    if (empty($fqdn)) {
                        continue;
                    }
                    $domains->push([
                        'id' => $id++,
                        'uuid' => $service->uuid.'-svc-'.$index,
                        'domain' => preg_replace('#^https?://#', '', $fqdn),
                        'fullUrl' => $fqdn,
                        'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
                        'resourceName' => $service->name.' ('.$svcApp->name.')',
                        'resourceType' => 'service',
                        'resourceUuid' => $service->uuid,
                        'isPrimary' => $index === 0,
                        'created_at' => $service->created_at?->toISOString(),
                    ]);
                }
            }
        }

        return \Inertia\Inertia::render('Domains/Index', [
            'domains' => $domains->values(),
        ]);
    })->name('domains.index');

    Route::get('/domains/add', function () {
        $applications = \App\Models\Application::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name')->get();
        $services = \App\Models\Service::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name')->get();

        return \Inertia\Inertia::render('Domains/Add', [
            'applications' => $applications,
            'databases' => [],
            'services' => $services,
        ]);
    })->name('domains.add');

    Route::get('/domains/{uuid}', function (string $uuid) {
        // Find an application whose FQDN contains this domain info
        $app = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', 'like', explode('-', $uuid)[0].'%')
            ->first();

        $domain = null;
        $sslCertificate = null;

        if ($app && $app->fqdn) {
            $fqdns = explode(',', $app->fqdn);
            $fqdn = trim($fqdns[0] ?? '');
            $domainName = preg_replace('#^https?://#', '', $fqdn);
            $domain = [
                'id' => $app->id,
                'uuid' => $uuid,
                'domain' => $domainName,
                'fullUrl' => $fqdn,
                'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
                'resourceName' => $app->name,
                'resourceType' => 'application',
                'isPrimary' => true,
                'created_at' => $app->created_at?->toISOString(),
            ];

            // Try to find matching SSL certificate
            $serverIds = auth()->user()->currentTeam()->servers()->pluck('id');
            $sslCertificate = \App\Models\SslCertificate::whereIn('server_id', $serverIds)
                ->where('common_name', $domainName)
                ->first();
            if ($sslCertificate) {
                $sslCertificate = [
                    'id' => $sslCertificate->id,
                    'commonName' => $sslCertificate->common_name,
                    'validUntil' => $sslCertificate->valid_until?->toISOString(),
                    'isExpired' => $sslCertificate->valid_until?->isPast() ?? false,
                ];
            }
        }

        return \Inertia\Inertia::render('Domains/Show', [
            'domain' => $domain,
            'sslCertificate' => $sslCertificate,
        ]);
    })->name('domains.show');

    Route::get('/domains/{uuid}/redirects', function (string $uuid) {
        // Find the application for this domain
        $app = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', 'like', explode('-', $uuid)[0].'%')
            ->first();

        $redirects = \App\Models\RedirectRule::ownedByCurrentTeam()
            ->when($app, fn ($q) => $q->where('application_id', $app->id))
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'source' => $r->source,
                'target' => $r->target,
                'type' => $r->type,
                'enabled' => $r->enabled,
                'hits' => $r->hits,
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('Domains/Redirects', [
            'redirects' => $redirects,
            'domainUuid' => $uuid,
        ]);
    })->name('domains.redirects');

    // Redirect rules CRUD
    Route::post('/domains/{uuid}/redirects', function (\Illuminate\Http\Request $request, string $uuid) {
        $validated = $request->validate([
            'source' => 'required|string|max:500',
            'target' => 'required|string|max:500',
            'type' => 'required|string|in:301,302',
        ]);

        $app = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', 'like', explode('-', $uuid)[0].'%')
            ->first();

        \App\Models\RedirectRule::create([
            ...$validated,
            'team_id' => currentTeam()->id,
            'application_id' => $app?->id,
            'enabled' => true,
        ]);

        return redirect()->back()->with('success', 'Redirect rule created');
    })->name('domains.redirects.store');

    Route::put('/domains/{uuid}/redirects/{id}', function (\Illuminate\Http\Request $request, string $uuid, int $id) {
        $rule = \App\Models\RedirectRule::ownedByCurrentTeam()->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'source' => 'sometimes|string|max:500',
            'target' => 'sometimes|string|max:500',
            'type' => 'sometimes|string|in:301,302',
            'enabled' => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return redirect()->back()->with('success', 'Redirect rule updated');
    })->name('domains.redirects.update');

    Route::delete('/domains/{uuid}/redirects/{id}', function (string $uuid, int $id) {
        $rule = \App\Models\RedirectRule::ownedByCurrentTeam()->where('id', $id)->firstOrFail();
        $rule->delete();

        return redirect()->back()->with('success', 'Redirect rule deleted');
    })->name('domains.redirects.destroy');

    // SSL routes
    Route::get('/ssl', function () {
        $team = auth()->user()->currentTeam();
        $serverIds = $team->servers()->pluck('id');

        $certificates = \App\Models\SslCertificate::whereIn('server_id', $serverIds)
            ->with('server:id,name')
            ->get()
            ->map(fn ($cert) => [
                'id' => $cert->id,
                'commonName' => $cert->common_name,
                'subjectAlternativeNames' => $cert->subject_alternative_names ?? [],
                'validUntil' => $cert->valid_until?->toISOString(),
                'isExpired' => $cert->valid_until?->isPast() ?? false,
                'isExpiringSoon' => $cert->valid_until?->diffInDays(now()) <= 30,
                'serverName' => $cert->server?->name,
                'isCaCertificate' => $cert->is_ca_certificate ?? false,
                'created_at' => $cert->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('SSL/Index', [
            'certificates' => $certificates,
        ]);
    })->name('ssl.index');

    Route::get('/ssl/upload', function () {
        // Collect all unique FQDNs from applications in the current team
        $team = auth()->user()->currentTeam();
        $domains = [];
        if ($team) {
            $projects = $team->projects()->with('environments.applications')->get();
            $id = 1;
            foreach ($projects as $project) {
                foreach ($project->environments as $env) {
                    foreach ($env->applications as $app) {
                        if ($app->fqdn) {
                            // An app can have multiple FQDNs comma-separated
                            foreach (explode(',', $app->fqdn) as $fqdn) {
                                $domain = preg_replace('#^https?://#', '', trim($fqdn));
                                if ($domain && ! in_array($domain, array_column($domains, 'domain'))) {
                                    $domains[] = ['id' => $id++, 'domain' => $domain];
                                }
                            }
                        }
                    }
                }
            }
        }

        return \Inertia\Inertia::render('SSL/Upload', [
            'domains' => $domains,
        ]);
    })->name('ssl.upload');

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

        return \Inertia\Inertia::render('CronJobs/Index', [
            'cronJobs' => $cronJobs,
        ]);
    })->name('cron-jobs.index');

    Route::get('/cron-jobs/create', function () {
        $applications = \App\Models\Application::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name')->get();
        $services = \App\Models\Service::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name')->get();

        return \Inertia\Inertia::render('CronJobs/Create', [
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

        return \Inertia\Inertia::render('CronJobs/Show', [
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
        $team = auth()->user()->currentTeam();
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

        return \Inertia\Inertia::render('ScheduledTasks/Index', [
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

        return \Inertia\Inertia::render('ScheduledTasks/History', [
            'history' => $tasks,
        ]);
    })->name('scheduled-tasks.history');

    // Deployments routes
    Route::get('/deployments', function () {
        $team = auth()->user()->currentTeam();
        $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');

        $deployments = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'uuid' => $d->deployment_uuid,
                'application_id' => $d->application_id,
                'status' => $d->status,
                'commit' => $d->commit,
                'commit_message' => $d->commit_message,
                'created_at' => $d->created_at?->toISOString(),
                'updated_at' => $d->updated_at?->toISOString(),
                'service_name' => $d->application_name,
                'trigger' => $d->is_webhook ? 'push' : ($d->rollback ? 'rollback' : 'manual'),
            ]);

        return \Inertia\Inertia::render('Deployments/Index', [
            'deployments' => $deployments,
        ]);
    })->name('deployments.index');

    Route::get('/deployments/{uuid}', function (string $uuid) {
        $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

        if (! $deployment) {
            return \Inertia\Inertia::render('Deployments/Show', ['deployment' => null]);
        }

        $logs = $deployment->logs ? json_decode($deployment->logs, true) : [];
        $buildLogs = [];
        $deployLogs = [];
        foreach ($logs as $log) {
            $line = ($log['timestamp'] ?? '').' '.($log['output'] ?? $log['message'] ?? '');
            if (($log['type'] ?? '') === 'deploy') {
                $deployLogs[] = trim($line);
            } else {
                $buildLogs[] = trim($line);
            }
        }

        // Calculate duration
        $duration = null;
        if ($deployment->created_at && $deployment->updated_at && $deployment->status !== 'in_progress') {
            $diff = $deployment->created_at->diff($deployment->updated_at);
            if ($diff->i > 0) {
                $duration = $diff->i.'m '.$diff->s.'s';
            } else {
                $duration = $diff->s.'s';
            }
        }

        $data = [
            'id' => $deployment->id,
            'uuid' => $deployment->deployment_uuid,
            'application_id' => $deployment->application_id,
            'status' => $deployment->status,
            'commit' => $deployment->commit,
            'commit_message' => $deployment->commit_message,
            'created_at' => $deployment->created_at?->toISOString(),
            'updated_at' => $deployment->updated_at?->toISOString(),
            'service_name' => $deployment->application_name,
            'trigger' => $deployment->is_webhook ? 'push' : ($deployment->rollback ? 'rollback' : 'manual'),
            'duration' => $duration,
            'build_logs' => $buildLogs,
            'deploy_logs' => $deployLogs,
        ];

        return \Inertia\Inertia::render('Deployments/Show', [
            'deployment' => $data,
        ]);
    })->name('deployments.show');

    Route::get('/deployments/{uuid}/logs', function (string $uuid) {
        $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

        return \Inertia\Inertia::render('Deployments/BuildLogs', [
            'deployment' => $deployment ? [
                'uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'application_name' => $deployment->application_name,
            ] : null,
        ]);
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
    // Supports incremental fetching via ?since=<unix_timestamp> parameter
    Route::get('/applications/{uuid}/logs/json', function (string $uuid, \Illuminate\Http\Request $request) {
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

        // Get since parameter for incremental fetching
        $since = $request->query('since');

        // Get container logs
        try {
            $containers = getCurrentApplicationContainerStatus($server, $application->id, 0, true);

            if ($containers->isEmpty()) {
                \Log::warning('Logs route: No containers found', [
                    'app_id' => $application->id,
                    'app_uuid' => $uuid,
                    'server_id' => $server->id,
                    'server_ip' => $server->ip,
                ]);

                return response()->json([
                    'container_logs' => 'No containers found for this application.',
                    'containers' => [],
                    'timestamp' => now()->timestamp,
                ]);
            }

            // Get logs from first container
            $firstContainer = $containers->first();
            $containerName = $firstContainer['Names'] ?? null;
            if ($containerName) {
                // Use --since for incremental fetching, or -n 200 for initial load
                if ($since) {
                    // Fetch only new logs since the given timestamp
                    $logs = instant_remote_process(["docker logs --since {$since} --timestamps {$containerName} 2>&1"], $server);
                } else {
                    // Initial load - get last 200 lines with timestamps
                    $logs = instant_remote_process(["docker logs -n 200 --timestamps {$containerName} 2>&1"], $server);
                }

                return response()->json([
                    'container_logs' => $logs,
                    'containers' => $containers,
                    'timestamp' => now()->timestamp,
                ]);
            }

            return response()->json([
                'container_logs' => 'Container not found.',
                'containers' => $containers,
                'timestamp' => now()->timestamp,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch logs: '.$e->getMessage(),
            ], 500);
        }
    })->name('applications.logs.json');

    // Application environment variables bulk update (web route for session auth)
    Route::patch('/applications/{uuid}/envs/bulk', function (string $uuid, \Illuminate\Http\Request $request) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->first();

        if (! $application) {
            return back()->with('error', 'Application not found.');
        }

        $variables = $request->input('variables', []);

        // Delete all existing non-preview environment variables
        $application->environment_variables()
            ->where('is_preview', false)
            ->delete();

        // Create new environment variables
        foreach ($variables as $item) {
            if (empty($item['key'])) {
                continue;
            }

            $application->environment_variables()->create([
                'key' => $item['key'],
                'value' => $item['value'] ?? '',
                'is_preview' => false,
                'is_buildtime' => $item['is_build_time'] ?? false,
                'is_runtime' => true,
            ]);
        }

        return back()->with('success', 'Environment variables saved successfully.');
    })->name('applications.envs.bulk');

    // Scan .env.example from application repository
    Route::post('/web-api/applications/{uuid}/scan-env-example', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        try {
            $result = (new \App\Actions\Application\ScanEnvExample)->handle($application);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to scan: '.$e->getMessage()], 500);
        }
    })->name('web-api.applications.scan-env-example');

    // Activity routes
    Route::get('/activity', function () {
        $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities(50);

        return \Inertia\Inertia::render('Activity/Index', [
            'activities' => $activities,
        ]);
    })->name('activity.index');

    Route::get('/activity/timeline', function () {
        $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities(100);

        return \Inertia\Inertia::render('Activity/Timeline', [
            'activities' => $activities,
            'currentPage' => 1,
            'totalPages' => 1,
        ]);
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

    // Team activities JSON endpoint (for web session)
    Route::get('/web-api/team/activities', function (\Illuminate\Http\Request $request) {
        $limit = min((int) $request->query('per_page', 10), 100);
        $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities($limit);

        return response()->json([
            'data' => $activities,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $limit,
                'total' => count($activities),
            ],
        ]);
    })->name('web-api.team.activities');

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

    // Public git repository branches (session auth)
    Route::get('/web-api/git/branches', function (\Illuminate\Http\Request $request) {
        $repositoryUrl = $request->query('repository_url');

        if (empty($repositoryUrl)) {
            return response()->json([
                'message' => 'Repository URL is required',
            ], 400);
        }

        // Parse repository URL
        $url = preg_replace('/\.git$/', '', $repositoryUrl);
        $parsed = null;

        // GitHub
        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            $parsed = ['platform' => 'github', 'owner' => $matches[1], 'repo' => $matches[2]];
        }
        // GitLab
        elseif (preg_match('#^https?://gitlab\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            $parsed = ['platform' => 'gitlab', 'owner' => $matches[1], 'repo' => $matches[2]];
        }
        // Bitbucket
        elseif (preg_match('#^https?://bitbucket\.org/([^/]+)/([^/]+)#', $url, $matches)) {
            $parsed = ['platform' => 'bitbucket', 'owner' => $matches[1], 'repo' => $matches[2]];
        }

        if (! $parsed) {
            return response()->json([
                'message' => 'Invalid repository URL. Supported platforms: GitHub, GitLab, Bitbucket',
            ], 400);
        }

        // Cache key
        $cacheKey = 'git_branches_'.md5($repositoryUrl);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $owner = $parsed['owner'];
        $repo = $parsed['repo'];
        $result = null;

        // Fetch branches based on platform
        if ($parsed['platform'] === 'github') {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Saturn-Platform',
            ])->timeout(15)->retry(2, 100, throw: false)
                ->get("https://api.github.com/repos/{$owner}/{$repo}/branches", ['per_page' => 100]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 404) {
                    return response()->json(['message' => 'Repository not found or is private'], 404);
                }

                return response()->json(['message' => $response->json('message', 'Failed to fetch branches')], $status);
            }

            $branches = collect($response->json())->map(fn ($b) => ['name' => $b['name'], 'is_default' => false])->toArray();

            // Get default branch
            $repoResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Saturn-Platform',
            ])->timeout(10)->get("https://api.github.com/repos/{$owner}/{$repo}");

            $defaultBranch = $repoResponse->json('default_branch', 'main');
            foreach ($branches as &$branch) {
                if ($branch['name'] === $defaultBranch) {
                    $branch['is_default'] = true;
                }
            }

            $result = ['branches' => $branches, 'default_branch' => $defaultBranch];
        } elseif ($parsed['platform'] === 'gitlab') {
            $projectPath = urlencode("{$owner}/{$repo}");
            $response = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
                ->timeout(15)->retry(2, 100, throw: false)
                ->get("https://gitlab.com/api/v4/projects/{$projectPath}/repository/branches", ['per_page' => 100]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 404) {
                    return response()->json(['message' => 'Repository not found or is private'], 404);
                }

                return response()->json(['message' => $response->json('message', 'Failed to fetch branches')], $status);
            }

            $projectResponse = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
                ->timeout(10)->get("https://gitlab.com/api/v4/projects/{$projectPath}");
            $defaultBranch = $projectResponse->json('default_branch', 'main');

            $branches = collect($response->json())->map(fn ($b) => [
                'name' => $b['name'],
                'is_default' => $b['name'] === $defaultBranch,
            ])->toArray();

            $result = ['branches' => $branches, 'default_branch' => $defaultBranch];
        } elseif ($parsed['platform'] === 'bitbucket') {
            $response = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
                ->timeout(15)->retry(2, 100, throw: false)
                ->get("https://api.bitbucket.org/2.0/repositories/{$owner}/{$repo}/refs/branches", ['pagelen' => 100]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 404) {
                    return response()->json(['message' => 'Repository not found or is private'], 404);
                }

                return response()->json(['message' => $response->json('error.message', 'Failed to fetch branches')], $status);
            }

            $repoResponse = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
                ->timeout(10)->get("https://api.bitbucket.org/2.0/repositories/{$owner}/{$repo}");
            $defaultBranch = $repoResponse->json('mainbranch.name', 'main');

            $branches = collect($response->json('values', []))->map(fn ($b) => [
                'name' => $b['name'],
                'is_default' => $b['name'] === $defaultBranch,
            ])->toArray();

            $result = ['branches' => $branches, 'default_branch' => $defaultBranch];
        }

        if (! $result) {
            return response()->json(['message' => 'Unsupported platform'], 400);
        }

        // Sort: default first, then alphabetically
        usort($result['branches'], function ($a, $b) {
            if ($a['is_default'] && ! $b['is_default']) {
                return -1;
            }
            if (! $a['is_default'] && $b['is_default']) {
                return 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $responseData = [
            'branches' => $result['branches'],
            'default_branch' => $result['default_branch'],
            'platform' => $parsed['platform'],
        ];

        // Cache for 5 minutes
        \Illuminate\Support\Facades\Cache::put($cacheKey, $responseData, 300);

        return response()->json($responseData);
    })->name('web-api.git.branches');

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
        $storages = \App\Models\S3Storage::ownedByCurrentTeam()->get()->map(fn ($s) => [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'name' => $s->name,
            'description' => $s->description,
            'endpoint' => $s->endpoint,
            'bucket' => $s->bucket,
            'region' => $s->region,
            'is_usable' => $s->is_usable,
            'created_at' => $s->created_at?->toISOString(),
            'updated_at' => $s->updated_at?->toISOString(),
        ]);

        return \Inertia\Inertia::render('Storage/Index', [
            'storages' => $storages,
        ]);
    })->name('storage.index');

    Route::get('/storage/create', function () {
        return \Inertia\Inertia::render('Storage/Create');
    })->name('storage.create');

    Route::post('/storage', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'key' => 'required|string',
            'secret' => 'required|string',
            'bucket' => 'required|string',
            'region' => 'required|string',
            'endpoint' => 'nullable|string',
            'path' => 'nullable|string',
        ]);

        $team = auth()->user()->currentTeam();

        \App\Models\S3Storage::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'key' => $validated['key'],
            'secret' => $validated['secret'],
            'bucket' => $validated['bucket'],
            'region' => $validated['region'],
            'endpoint' => $validated['endpoint'] ?? null,
            'team_id' => $team->id,
        ]);

        return redirect()->route('storage.index')->with('success', 'Storage created successfully');
    })->name('storage.store');

    Route::post('/storage/test-connection', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'key' => 'required|string',
            'secret' => 'required|string',
            'bucket' => 'required|string',
            'region' => 'required|string',
            'endpoint' => 'nullable|string',
        ]);

        $team = auth()->user()->currentTeam();

        // Create a temporary S3Storage to test
        $storage = new \App\Models\S3Storage([
            'key' => $validated['key'],
            'secret' => $validated['secret'],
            'bucket' => $validated['bucket'],
            'region' => $validated['region'],
            'endpoint' => $validated['endpoint'] ?? null,
            'team_id' => $team->id,
        ]);

        try {
            $result = $storage->testConnection();

            return response()->json([
                'success' => (bool) $result,
                'message' => $result ? 'Connection successful! Storage is ready to use.' : 'Connection failed. Please check your credentials and try again.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ]);
        }
    })->name('storage.test-connection');

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
        $team = auth()->user()->currentTeam();
        $servers = $team->servers()->with(['standaloneDockers', 'swarmDockers'])->get();

        $destinations = collect();
        foreach ($servers as $server) {
            foreach ($server->standaloneDockers as $docker) {
                $destinations->push([
                    'id' => $docker->id,
                    'uuid' => $docker->uuid,
                    'name' => $docker->name,
                    'network' => $docker->network,
                    'serverName' => $server->name,
                    'serverUuid' => $server->uuid,
                    'type' => 'standalone',
                    'created_at' => $docker->created_at?->toISOString(),
                ]);
            }
            foreach ($server->swarmDockers as $swarm) {
                $destinations->push([
                    'id' => $swarm->id,
                    'uuid' => $swarm->uuid,
                    'name' => $swarm->name,
                    'network' => $swarm->network,
                    'serverName' => $server->name,
                    'serverUuid' => $server->uuid,
                    'type' => 'swarm',
                    'created_at' => $swarm->created_at?->toISOString(),
                ]);
            }
        }

        return \Inertia\Inertia::render('Destinations/Index', [
            'destinations' => $destinations->values(),
        ]);
    })->name('destinations.index');

    Route::get('/destinations/create', function () {
        $servers = \App\Models\Server::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name', 'ip')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'name' => $s->name,
                'ip' => $s->ip,
            ]);

        return \Inertia\Inertia::render('Destinations/Create', [
            'servers' => $servers,
        ]);
    })->name('destinations.create');

    Route::post('/destinations', function (\Illuminate\Http\Request $request) {
        return redirect()->route('destinations.index')->with('success', 'Destination created successfully');
    })->name('destinations.store');

    Route::get('/destinations/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Destinations/Show', ['uuid' => $uuid]);
    })->name('destinations.show');

    // Shared Variables routes
    Route::get('/shared-variables', function () {
        $team = auth()->user()->currentTeam();
        $variables = \App\Models\SharedEnvironmentVariable::where('team_id', $team->id)
            ->with(['project', 'environment'])
            ->get()
            ->map(fn ($var) => [
                'id' => $var->id,
                'uuid' => $var->uuid ?? $var->id,
                'key' => $var->key,
                'value' => $var->value,
                'scope' => $var->environment_id ? 'environment' : ($var->project_id ? 'project' : 'team'),
                'project_name' => $var->project?->name,
                'environment_name' => $var->environment?->name,
                'created_at' => $var->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('SharedVariables/Index', [
            'variables' => $variables,
            'team' => ['id' => $team->id, 'name' => $team->name],
        ]);
    })->name('shared-variables.index');

    Route::get('/shared-variables/create', function () {
        $team = auth()->user()->currentTeam();
        $projects = $team->projects()->with('environments')->get();

        return \Inertia\Inertia::render('SharedVariables/Create', [
            'teams' => [['id' => $team->id, 'name' => $team->name]],
            'projects' => $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'team_id' => $team->id]),
            'environments' => $projects->flatMap(fn ($p) => $p->environments->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'project_id' => $p->id,
            ])),
        ]);
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
        $tags = \App\Models\Tag::ownedByCurrentTeam()
            ->withCount(['applications', 'services'])
            ->get()
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'applicationsCount' => $tag->applications_count,
                'servicesCount' => $tag->services_count,
                'created_at' => $tag->created_at?->toISOString(),
            ]);

        return \Inertia\Inertia::render('Tags/Index', [
            'tags' => $tags,
        ]);
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
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $projectUuid)
            ->first();

        if (! $project) {
            return \Inertia\Inertia::render('Activity/ProjectActivity', [
                'project' => null,
            ]);
        }

        $environments = $project->environments()->select('id', 'name', 'uuid')->get();

        $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities(50);

        return \Inertia\Inertia::render('Activity/ProjectActivity', [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
            ],
            'environments' => $environments,
            'activities' => $activities,
        ]);
    })->name('activity.project');

    Route::get('/activity/{uuid}', function (string $uuid) {
        $activity = \App\Http\Controllers\Inertia\ActivityHelper::getActivity($uuid);
        $related = \App\Http\Controllers\Inertia\ActivityHelper::getRelatedActivities($uuid);

        return \Inertia\Inertia::render('Activity/Show', [
            'activity' => $activity,
            'relatedActivities' => $related,
        ]);
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
