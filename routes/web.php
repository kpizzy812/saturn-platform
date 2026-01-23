<?php

use App\Actions\Application\StopApplication;
use App\Actions\Database\RestartDatabase;
use App\Actions\Service\RestartService;
use App\Actions\Service\StopService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\UploadController;
use App\Jobs\DeleteResourceJob;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Visus\Cuid2\Cuid2;

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

    // Servers
    Route::get('/servers', function () {
        $servers = \App\Models\Server::ownedByCurrentTeam()
            ->with('settings')
            ->get();

        return \Inertia\Inertia::render('Servers/Index', [
            'servers' => $servers,
        ]);
    })->name('servers.index');

    Route::get('/servers/create', function () {
        $privateKeys = \App\Models\PrivateKey::ownedByCurrentTeam()->get();

        return \Inertia\Inertia::render('Servers/Create', [
            'privateKeys' => $privateKeys,
        ]);
    })->name('servers.create');

    Route::post('/servers', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'ip' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'user' => 'required|string|max:255',
            'private_key' => 'required_without:private_key_id|nullable|string',
            'private_key_id' => 'required_without:private_key|nullable|exists:private_keys,id',
        ]);

        $validated['team_id'] = currentTeam()->id;
        $validated['uuid'] = (string) new \Visus\Cuid2\Cuid2;

        // If private_key content is provided, create a new PrivateKey record
        if (! empty($validated['private_key'])) {
            $privateKey = \App\Models\PrivateKey::create([
                'name' => $validated['name'].' SSH Key',
                'private_key' => $validated['private_key'],
                'team_id' => currentTeam()->id,
            ]);
            $validated['private_key_id'] = $privateKey->id;
            unset($validated['private_key']);
        } else {
            // Verify ownership of existing key
            $privateKey = \App\Models\PrivateKey::ownedByCurrentTeam()
                ->findOrFail($validated['private_key_id']);
        }

        $server = \App\Models\Server::create($validated);

        return redirect()->route('servers.show', $server->uuid)
            ->with('success', 'Server created successfully');
    })->name('servers.store');

    Route::get('/servers/{uuid}', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Show', [
            'server' => $server,
        ]);
    })->name('servers.show');

    Route::get('/servers/{uuid}/settings', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Settings/Index', ['server' => $server]);
    })->name('servers.settings');

    Route::get('/servers/{uuid}/settings/docker', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Settings/Docker', ['server' => $server]);
    })->name('servers.settings.docker');

    Route::get('/servers/{uuid}/settings/network', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Settings/Network', ['server' => $server]);
    })->name('servers.settings.network');

    Route::get('/servers/{uuid}/destinations', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Fetch destinations from database
        $destinations = $server->destinations();

        return \Inertia\Inertia::render('Servers/Destinations/Index', [
            'server' => $server,
            'destinations' => $destinations,
        ]);
    })->name('servers.destinations');

    Route::get('/servers/{uuid}/destinations/create', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Destinations/Create', ['server' => $server]);
    })->name('servers.destinations.create');

    Route::get('/servers/{uuid}/resources', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Count actual resources on this server
        $applications = $server->applications()->count();
        $databases = $server->databases()->count();
        $services = $server->services()->count();

        return \Inertia\Inertia::render('Servers/Resources/Index', [
            'server' => $server,
            'applications' => $applications,
            'databases' => $databases,
            'services' => $services,
        ]);
    })->name('servers.resources');

    Route::get('/servers/{uuid}/log-drains', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Fetch destinations (log drains) from StandaloneDocker and SwarmDocker
        $standaloneDockers = $server->standaloneDockers()->get()->map(function ($destination) {
            return [
                'id' => $destination->id,
                'type' => 'standalone',
                'name' => $destination->name,
                'network' => $destination->network,
                'created_at' => $destination->created_at,
            ];
        });

        $swarmDockers = $server->swarmDockers()->get()->map(function ($destination) {
            return [
                'id' => $destination->id,
                'type' => 'swarm',
                'name' => $destination->name,
                'network' => $destination->network,
                'created_at' => $destination->created_at,
            ];
        });

        $logDrains = $standaloneDockers->concat($swarmDockers);

        return \Inertia\Inertia::render('Servers/LogDrains/Index', [
            'server' => $server,
            'logDrains' => $logDrains,
        ]);
    })->name('servers.log-drains');

    Route::get('/servers/{uuid}/private-keys', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Fetch all private keys owned by current team
        $privateKeys = \App\Models\PrivateKey::ownedByCurrentTeam()
            ->select(['id', 'uuid', 'name', 'description', 'fingerprint', 'is_git_related', 'created_at'])
            ->get()
            ->map(function ($key) use ($server) {
                return [
                    'id' => $key->id,
                    'uuid' => $key->uuid,
                    'name' => $key->name,
                    'description' => $key->description,
                    'fingerprint' => $key->fingerprint,
                    'is_git_related' => $key->is_git_related,
                    'created_at' => $key->created_at,
                    'is_used_by_server' => $server->private_key_id === $key->id,
                ];
            });

        return \Inertia\Inertia::render('Servers/PrivateKeys/Index', [
            'server' => $server,
            'privateKeys' => $privateKeys,
        ]);
    })->name('servers.private-keys');

    Route::get('/servers/{uuid}/cleanup', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Fetch latest cleanup execution for this server
        $latestCleanup = $server->dockerCleanupExecutions()
            ->orderBy('created_at', 'desc')
            ->first();

        $cleanupStats = $latestCleanup ? [
            'executed_at' => $latestCleanup->created_at,
            'containers_removed' => $latestCleanup->containers_removed ?? 0,
            'images_removed' => $latestCleanup->images_removed ?? 0,
            'volumes_removed' => $latestCleanup->volumes_removed ?? 0,
            'networks_removed' => $latestCleanup->networks_removed ?? 0,
            'space_reclaimed' => $latestCleanup->space_reclaimed ?? 0,
        ] : null;

        return \Inertia\Inertia::render('Servers/Cleanup/Index', [
            'server' => $server,
            'cleanupStats' => $cleanupStats,
        ]);
    })->name('servers.cleanup');

    Route::get('/servers/{uuid}/metrics', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Metrics/Index', ['server' => $server]);
    })->name('servers.metrics');

    Route::get('/servers/{uuid}/terminal', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return \Inertia\Inertia::render('Servers/Terminal/Index', [
            'server' => $server,
        ]);
    })->name('servers.terminal');

    // Proxy routes
    Route::prefix('/servers/{uuid}/proxy')->group(function () {
        Route::get('/', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            // Get proxy status from server's proxy configuration
            $proxyStatus = data_get($server->proxy, 'status', 'exited');
            $proxyType = $server->proxyType() ?? 'traefik';

            // Get detected version for Traefik
            $proxyVersion = null;
            if ($proxyType === 'traefik') {
                $proxyVersion = $server->detected_traefik_version;
            }

            // Count SSL certificates for this server
            $sslCount = $server->sslCertificates()->count();

            // Count applications with domains (as proxy for domain count)
            $domainsCount = $server->applications()
                ->whereNotNull('fqdn')
                ->where('fqdn', '!=', '')
                ->count();

            return \Inertia\Inertia::render('Servers/Proxy/Index', [
                'server' => $server,
                'proxy' => [
                    'type' => $proxyType,
                    'status' => $proxyStatus,
                    'version' => $proxyVersion,
                    'uptime' => null, // Uptime requires real-time container inspection
                    'domains_count' => $domainsCount,
                    'ssl_count' => $sslCount,
                ],
            ]);
        })->name('servers.proxy.index');

        Route::get('/configuration', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            return \Inertia\Inertia::render('Servers/Proxy/Configuration', [
                'server' => $server,
                'configuration' => "version: '3'\nservices:\n  traefik:\n    image: traefik:latest\n    # Add your configuration here",
                'filePath' => '/data/saturn/proxy/docker-compose.yml',
            ]);
        })->name('servers.proxy.configuration');

        Route::post('/configuration', function (string $uuid, \Illuminate\Http\Request $request) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            $validated = $request->validate([
                'configuration' => 'required|string',
            ]);

            try {
                \App\Actions\Proxy\SaveProxyConfiguration::run($server, $validated['configuration']);

                return redirect()->back()->with('success', 'Proxy configuration saved successfully');
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to save configuration: '.$e->getMessage());
            }
        })->name('servers.proxy.configuration.update');

        Route::post('/configuration/reset', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            try {
                $configuration = \App\Actions\Proxy\GetProxyConfiguration::run($server, forceRegenerate: true);
                \App\Actions\Proxy\SaveProxyConfiguration::run($server, $configuration);

                return redirect()->back()->with('success', 'Proxy configuration reset to default');
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to reset configuration: '.$e->getMessage());
            }
        })->name('servers.proxy.configuration.reset');

        Route::get('/logs', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            $logs = '';
            try {
                $containerName = $server->isSwarm() ? 'saturn-proxy_traefik' : 'saturn-proxy';
                $logs = getContainerLogs($server, $containerName, 500);
            } catch (\Throwable $e) {
                $logs = 'Failed to fetch logs: '.$e->getMessage();
            }

            return \Inertia\Inertia::render('Servers/Proxy/Logs', [
                'server' => $server,
                'logs' => $logs,
            ]);
        })->name('servers.proxy.logs');

        Route::get('/domains', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            // Collect domains from applications and services on this server
            $domains = collect();

            // Get applications with domains
            $applications = $server->applications()
                ->whereNotNull('fqdn')
                ->where('fqdn', '!=', '')
                ->get();

            foreach ($applications as $app) {
                $fqdns = explode(',', $app->fqdn);
                foreach ($fqdns as $fqdn) {
                    $fqdn = trim($fqdn);
                    if (! empty($fqdn)) {
                        $domains->push([
                            'id' => $app->id,
                            'type' => 'application',
                            'name' => $app->name,
                            'fqdn' => $fqdn,
                            'ssl_enabled' => ! empty($app->fqdn),
                        ]);
                    }
                }
            }

            // Get service applications with domains
            $serviceApps = \App\Models\ServiceApplication::whereHas('service', function ($query) use ($server) {
                $query->where('server_id', $server->id);
            })
                ->whereNotNull('fqdn')
                ->where('fqdn', '!=', '')
                ->get();

            foreach ($serviceApps as $serviceApp) {
                $fqdns = explode(',', $serviceApp->fqdn);
                foreach ($fqdns as $fqdn) {
                    $fqdn = trim($fqdn);
                    if (! empty($fqdn)) {
                        $domains->push([
                            'id' => $serviceApp->id,
                            'type' => 'service',
                            'name' => $serviceApp->service->name.' - '.$serviceApp->name,
                            'fqdn' => $fqdn,
                            'ssl_enabled' => ! empty($serviceApp->fqdn),
                        ]);
                    }
                }
            }

            return \Inertia\Inertia::render('Servers/Proxy/Domains', [
                'server' => $server,
                'domains' => $domains->values()->toArray(),
            ]);
        })->name('servers.proxy.domains');

        Route::post('/domains', function (string $uuid, \Illuminate\Http\Request $request) {
            // Note: Domains in Saturn Platform are managed at the application/service level,
            // not directly through the proxy. To add a domain, configure it on the application or service.
            return redirect()->back()->with('info', 'Domains are managed at the application/service level. Please configure domains on your applications or services.');
        })->name('servers.proxy.domains.store');

        Route::patch('/domains/{domainId}', function (string $uuid, int $domainId, \Illuminate\Http\Request $request) {
            // Note: Domains in Saturn Platform are managed at the application/service level,
            // not directly through the proxy. To update a domain, configure it on the application or service.
            return redirect()->back()->with('info', 'Domains are managed at the application/service level. Please update domains on your applications or services.');
        })->name('servers.proxy.domains.update');

        Route::delete('/domains/{domainId}', function (string $uuid, int $domainId) {
            // Note: Domains in Saturn Platform are managed at the application/service level,
            // not directly through the proxy. To remove a domain, configure it on the application or service.
            return redirect()->back()->with('info', 'Domains are managed at the application/service level. Please remove domains from your applications or services.');
        })->name('servers.proxy.domains.destroy');

        Route::post('/domains/{domainId}/renew-certificate', function (string $uuid, int $domainId) {
            // Note: SSL certificates are automatically managed and renewed by the proxy.
            // Certificate renewal happens automatically when close to expiration.
            return redirect()->back()->with('info', 'SSL certificates are automatically renewed by the proxy. No manual action is required.');
        })->name('servers.proxy.domains.renew-certificate');

        Route::get('/settings', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            return \Inertia\Inertia::render('Servers/Proxy/Settings', [
                'server' => $server,
                'settings' => [
                    'ssl_provider' => 'letsencrypt',
                    'letsencrypt_email' => '',
                    'default_redirect_url' => '',
                    'enable_rate_limiting' => false,
                    'rate_limit_requests' => 100,
                    'rate_limit_window' => 60,
                    'custom_headers' => [],
                ],
            ]);
        })->name('servers.proxy.settings');

        Route::post('/settings', function (string $uuid, \Illuminate\Http\Request $request) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            // Note: Proxy settings in Saturn Platform are typically managed through the proxy configuration file
            // and server settings. This is a placeholder for future proxy-specific settings.
            return redirect()->back()->with('info', 'Proxy settings are currently managed through the proxy configuration file. Please use the Configuration tab to modify proxy settings.');
        })->name('servers.proxy.settings.update');

        Route::post('/restart', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            try {
                \App\Jobs\RestartProxyJob::dispatch($server);

                return redirect()->back()->with('success', 'Proxy restart initiated');
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to restart proxy: '.$e->getMessage());
            }
        })->name('servers.proxy.restart');

        Route::post('/start', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            try {
                \App\Actions\Proxy\StartProxy::run($server);

                return redirect()->back()->with('success', 'Proxy start initiated');
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to start proxy: '.$e->getMessage());
            }
        })->name('servers.proxy.start');

        Route::post('/stop', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            try {
                \App\Actions\Proxy\StopProxy::run($server);

                return redirect()->back()->with('success', 'Proxy stopped successfully');
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to stop proxy: '.$e->getMessage());
            }
        })->name('servers.proxy.stop');
    });

    // Sentinel (Server Monitoring) routes
    Route::prefix('/servers/{uuid}/sentinel')->group(function () {
        Route::get('/', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            return \Inertia\Inertia::render('Servers/Sentinel/Index', [
                'server' => $server,
            ]);
        })->name('servers.sentinel.index');

        Route::get('/alerts', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            // Fetch alert rules and history (Sentinel alerting tables if available)
            $alertRules = [];
            $alertHistory = [];

            // Try to load from server settings if available
            if ($server->settings && method_exists($server->settings, 'alertRules')) {
                $alertRules = $server->settings->alertRules ?? [];
            }

            return \Inertia\Inertia::render('Servers/Sentinel/Alerts', [
                'server' => $server,
                'alertRules' => $alertRules,
                'alertHistory' => $alertHistory,
            ]);
        })->name('servers.sentinel.alerts');

        Route::get('/metrics', function (string $uuid) {
            $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

            return \Inertia\Inertia::render('Servers/Sentinel/Metrics', [
                'server' => $server,
            ]);
        })->name('servers.sentinel.metrics');
    });

    // Server action routes
    Route::post('/servers/{uuid}/validate', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        try {
            // Validate server connection
            $result = $server->validateConnection();

            if ($result['uptime']) {
                // Validate Docker engine
                $server->validateDockerEngine();

                return redirect()->back()->with('success', 'Server validation completed successfully');
            } else {
                return redirect()->back()->with('error', 'Server validation failed: '.($result['error'] ?? 'Unable to connect'));
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Server validation failed: '.$e->getMessage());
        }
    })->name('servers.validate');

    // Projects
    Route::get('/projects', function () {
        $projects = \App\Models\Project::ownedByCurrentTeam()
            ->with(['environments.applications'])
            ->get();

        return \Inertia\Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    })->name('projects.index');

    Route::get('/projects/create', function () {
        return \Inertia\Inertia::render('Projects/Create');
    })->name('projects.create');

    // Sub-routes for project creation flow
    Route::get('/projects/create/github', function () {
        // Redirect to applications create with github preset
        return redirect()->route('applications.create', ['source' => 'github']);
    })->name('projects.create.github');

    Route::get('/projects/create/database', function () {
        return redirect()->route('databases.create');
    })->name('projects.create.database');

    Route::get('/projects/create/docker', function () {
        // Redirect to applications create with docker preset
        return redirect()->route('applications.create', ['source' => 'docker']);
    })->name('projects.create.docker');

    Route::get('/projects/create/empty', function () {
        // Create empty project directly
        $project = \App\Models\Project::create([
            'name' => 'New Project',
            'description' => null,
            'team_id' => currentTeam()->id,
        ]);

        // Create default environment
        $project->environments()->create([
            'name' => 'production',
        ]);

        return redirect()->route('projects.show', $project->uuid)
            ->with('success', 'Empty project created');
    })->name('projects.create.empty');

    Route::get('/projects/create/function', function () {
        return redirect()->route('projects.create')
            ->with('info', 'Functions are coming soon!');
    })->name('projects.create.function');

    Route::post('/projects', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = \App\Models\Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'team_id' => currentTeam()->id,
        ]);

        // Create default environment
        $project->environments()->create([
            'name' => 'production',
        ]);

        return redirect()->route('projects.show', $project->uuid);
    })->name('projects.store');

    Route::get('/projects/{uuid}', function (string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->with([
                'environments.applications',
                'environments.services',
                // Load all database types (databases() is not a relationship)
                'environments.postgresqls',
                'environments.redis',
                'environments.mongodbs',
                'environments.mysqls',
                'environments.mariadbs',
                'environments.keydbs',
                'environments.dragonflies',
                'environments.clickhouses',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Add computed databases to each environment
        $project->environments->each(function ($env) {
            $env->databases = $env->databases();
        });

        return \Inertia\Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    })->name('projects.show');

    Route::get('/projects/{uuid}/environments', function (string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Projects/Environments', [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
            ],
        ]);
    })->name('projects.environments');

    // Project settings page
    Route::get('/projects/{uuid}/settings', function (string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Projects/Settings', [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ],
        ]);
    })->name('projects.settings');

    // Update project
    Route::patch('/projects/{uuid}', function (\Illuminate\Http\Request $request, string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update($request->only(['name', 'description']));

        return redirect()->back()->with('success', 'Project updated successfully');
    })->name('projects.update');

    // Delete project
    Route::delete('/projects/{uuid}', function (string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $project->isEmpty()) {
            return redirect()->back()->with('error', 'Cannot delete project with active resources. Please remove all applications and databases first.');
        }

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted successfully');
    })->name('projects.destroy');

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

    // Services
    Route::get('/services', function () {
        $services = \App\Models\Service::ownedByCurrentTeam()->get();

        return \Inertia\Inertia::render('Services/Index', ['services' => $services]);
    })->name('services.index');

    Route::get('/services/create', function () {
        $projects = \App\Models\Project::ownedByCurrentTeam()
            ->with('environments')
            ->get();

        // Always get localhost (platform's master server) - used by default
        $localhost = \App\Models\Server::where('id', 0)->first();

        // Get user's additional servers (optional)
        $userServers = \App\Models\Server::ownedByCurrentTeam()
            ->where('id', '!=', 0)
            ->whereRelation('settings', 'is_usable', true)
            ->get();

        return \Inertia\Inertia::render('Services/Create', [
            'projects' => $projects,
            'localhost' => $localhost,
            'userServers' => $userServers,
            'needsProject' => $projects->isEmpty(),
        ]);
    })->name('services.create');

    Route::post('/services', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'docker_compose_raw' => 'required|string',
            'project_uuid' => 'required|string',
            'environment_uuid' => 'required|string',
            'server_uuid' => 'required|string',
        ]);

        // Find project and environment
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $validated['project_uuid'])
            ->firstOrFail();

        $environment = $project->environments()
            ->where('uuid', $validated['environment_uuid'])
            ->firstOrFail();

        // Find server and destination
        // First check if it's localhost (platform's master server with id=0)
        $localhost = \App\Models\Server::where('id', 0)->first();
        if ($localhost && $localhost->uuid === $validated['server_uuid']) {
            $server = $localhost;
        } else {
            // Otherwise, look for user's own servers
            $server = \App\Models\Server::ownedByCurrentTeam()
                ->where('uuid', $validated['server_uuid'])
                ->firstOrFail();
        }

        $destination = $server->destinations()->first();
        if (! $destination) {
            return redirect()->back()->withErrors(['server_uuid' => 'Server has no destinations configured']);
        }

        // Create the service
        $service = new \App\Models\Service;
        $service->name = $validated['name'];
        $service->description = $validated['description'] ?? null;
        $service->docker_compose_raw = $validated['docker_compose_raw'];
        $service->environment_id = $environment->id;
        $service->destination_id = $destination->id;
        $service->destination_type = $destination->getMorphClass();
        $service->server_id = $server->id;
        $service->save();

        // Parse docker-compose and create service applications/databases
        try {
            $service->parse();
        } catch (\Exception $e) {
            // Log but don't fail - service is created
            \Illuminate\Support\Facades\Log::warning('Failed to parse docker-compose: '.$e->getMessage());
        }

        return redirect()->route('services.show', $service->uuid)
            ->with('success', 'Service created successfully');
    })->name('services.store');

    Route::get('/services/{uuid}', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Show', [
            'service' => $service,
        ]);
    })->name('services.show');

    Route::get('/services/{uuid}/metrics', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Metrics', [
            'service' => $service,
        ]);
    })->name('services.metrics');

    Route::get('/services/{uuid}/build-logs', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/BuildLogs', [
            'service' => $service,
        ]);
    })->name('services.build-logs');

    Route::get('/services/{uuid}/domains', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Domains', [
            'service' => $service,
        ]);
    })->name('services.domains');

    Route::get('/services/{uuid}/webhooks', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $team = auth()->user()->currentTeam();
        $webhooks = $team->webhooks()
            ->with(['deliveries' => function ($query) {
                $query->limit(5);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return \Inertia\Inertia::render('Services/Webhooks', [
            'service' => $service,
            'webhooks' => $webhooks,
            'availableEvents' => \App\Models\TeamWebhook::availableEvents(),
        ]);
    })->name('services.webhooks');

    Route::get('/services/{uuid}/deployments', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Deployments', [
            'service' => $service,
        ]);
    })->name('services.deployments');

    Route::get('/services/{uuid}/logs', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Logs', [
            'service' => $service,
        ]);
    })->name('services.logs');

    Route::get('/services/{uuid}/health-checks', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/HealthChecks', [
            'service' => $service,
        ]);
    })->name('services.health-checks');

    Route::get('/services/{uuid}/networking', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Networking', [
            'service' => $service,
        ]);
    })->name('services.networking');

    Route::get('/services/{uuid}/scaling', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Scaling', [
            'service' => $service,
        ]);
    })->name('services.scaling');

    Route::get('/services/{uuid}/rollbacks', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Rollbacks', [
            'service' => $service,
        ]);
    })->name('services.rollbacks');

    Route::get('/services/{uuid}/settings', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Settings', [
            'service' => $service,
        ]);
    })->name('services.settings');

    Route::get('/services/{uuid}/variables', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Services/Variables', [
            'service' => $service,
        ]);
    })->name('services.variables');

    // Service action routes
    Route::post('/services/{uuid}/restart', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        RestartService::dispatch($service, false);

        return redirect()->back()->with('success', 'Service restart initiated');
    })->name('services.restart');

    Route::post('/services/{uuid}/stop', function (string $uuid) {
        $service = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        StopService::dispatch($service);

        return redirect()->back()->with('success', 'Service stopped');
    })->name('services.stop');

    // Applications (Saturn)
    Route::get('/applications', function () {
        // Ensure user has a current team
        $team = currentTeam();
        if (! $team) {
            return redirect()->route('dashboard')->with('error', 'Please select a team first');
        }

        $applications = \App\Models\Application::ownedByCurrentTeam()
            ->with(['environment.project'])
            ->get()
            ->map(function ($app) {
                return [
                    'id' => $app->id,
                    'uuid' => $app->uuid,
                    'name' => $app->name,
                    'description' => $app->description,
                    'fqdn' => $app->fqdn,
                    'git_repository' => $app->git_repository,
                    'git_branch' => $app->git_branch,
                    'build_pack' => $app->build_pack,
                    'status' => $app->status,
                    'project_name' => $app->environment->project->name,
                    'environment_name' => $app->environment->name,
                    'created_at' => $app->created_at,
                    'updated_at' => $app->updated_at,
                ];
            });

        return \Inertia\Inertia::render('Applications/Index', [
            'applications' => $applications,
        ]);
    })->name('applications.index');

    Route::get('/applications/create', function () {
        // Ensure user has a current team
        $team = currentTeam();
        if (! $team) {
            return redirect()->route('dashboard')->with('error', 'Please select a team first');
        }

        $projects = \App\Models\Project::ownedByCurrentTeam()
            ->with('environments')
            ->get();

        // Always get localhost (platform's master server) - used by default
        $localhost = \App\Models\Server::where('id', 0)->first();

        // Get user's additional servers (optional, for advanced users)
        $userServers = \App\Models\Server::ownedByCurrentTeam()
            ->where('id', '!=', 0)
            ->whereRelation('settings', 'is_usable', true)
            ->get();

        return \Inertia\Inertia::render('Applications/Create', [
            'projects' => $projects,
            'localhost' => $localhost,
            'userServers' => $userServers,
            'needsProject' => $projects->isEmpty(),
        ]);
    })->name('applications.create');

    Route::post('/applications', function (\Illuminate\Http\Request $request) {
        // Ensure user has a current team
        $team = currentTeam();
        if (! $team) {
            return redirect()->route('dashboard')->with('error', 'Please select a team first');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'source_type' => 'required|string|in:github,gitlab,bitbucket,docker',
            'git_repository' => 'required_unless:source_type,docker|nullable|string',
            'git_branch' => 'nullable|string',
            'build_pack' => 'required|string|in:nixpacks,dockerfile,dockercompose,dockerimage',
            'project_uuid' => 'required|string',
            'environment_uuid' => 'required|string',
            'server_uuid' => 'required|string',
            'fqdn' => 'nullable|string',
            'description' => 'nullable|string',
            'docker_image' => 'required_if:source_type,docker|nullable|string',
        ]);

        // Find project and environment
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $validated['project_uuid'])
            ->firstOrFail();

        $environment = $project->environments()
            ->where('uuid', $validated['environment_uuid'])
            ->firstOrFail();

        // Find server and destination
        // First check if it's localhost (platform's master server with id=0)
        $localhost = \App\Models\Server::where('id', 0)->first();
        if ($localhost && $localhost->uuid === $validated['server_uuid']) {
            $server = $localhost;
        } else {
            // Otherwise, look for user's own servers
            $server = \App\Models\Server::ownedByCurrentTeam()
                ->where('uuid', $validated['server_uuid'])
                ->firstOrFail();
        }

        $destination = $server->destinations()->first();
        if (! $destination) {
            return redirect()->back()->withErrors(['server_uuid' => 'Server has no destinations configured']);
        }

        // Create the application
        $application = new \App\Models\Application;
        $application->name = $validated['name'];
        $application->description = $validated['description'] ?? null;
        $application->fqdn = $validated['fqdn'] ?? null;
        $application->git_repository = $validated['git_repository'] ?? null;
        $application->git_branch = $validated['git_branch'] ?? 'main';
        $application->build_pack = $validated['build_pack'];
        $application->environment_id = $environment->id;
        $application->destination_id = $destination->id;
        $application->destination_type = $destination->getMorphClass();

        // Handle Docker image source
        if ($validated['source_type'] === 'docker') {
            $application->build_pack = 'dockerimage';
            $application->docker_registry_image_name = $validated['docker_image'];
        }

        // Set source type for git
        if (in_array($validated['source_type'], ['github', 'gitlab', 'bitbucket'])) {
            $githubApp = \App\Models\GithubApp::find(0); // Default public source
            if ($githubApp) {
                $application->source_type = \App\Models\GithubApp::class;
                $application->source_id = $githubApp->id;
            }
        }

        // Set default ports
        $application->ports_exposes = '80';

        $application->save();

        // Auto-generate domain if not provided
        if (empty($application->fqdn)) {
            $application->fqdn = generateUrl(server: $server, random: $application->uuid);
            $application->save();
        }

        return redirect()->route('applications.show', $application->uuid)
            ->with('success', 'Application created successfully');
    })->name('applications.store');

    Route::get('/applications/{uuid}', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->with(['environment.project'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Get recent deployments
        $recentDeployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('pull_request_id', 0)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'commit_message' => $deployment->commitMessage(),
                    'created_at' => $deployment->created_at,
                ];
            });

        // Count environment variables
        $envVarsCount = \App\Models\EnvironmentVariable::where('resourceable_type', \App\Models\Application::class)
            ->where('resourceable_id', $application->id)
            ->where('is_preview', false)
            ->count();

        return \Inertia\Inertia::render('Applications/Show', [
            'application' => [
                'id' => $application->id,
                'uuid' => $application->uuid,
                'name' => $application->name,
                'description' => $application->description,
                'fqdn' => $application->fqdn,
                'git_repository' => $application->git_repository,
                'git_branch' => $application->git_branch,
                'build_pack' => $application->build_pack,
                'status' => $application->status,
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
                'project' => $application->environment->project,
                'environment' => $application->environment,
                'recent_deployments' => $recentDeployments,
                'environment_variables_count' => $envVarsCount,
            ],
        ]);
    })->name('applications.show');

    // Application action routes
    Route::post('/applications/{uuid}/deploy', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            force_rebuild: false,
            is_api: false,
        );

        if ($result['status'] === 'skipped') {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Deployment started');
    })->name('applications.deploy');

    Route::post('/applications/{uuid}/start', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            force_rebuild: false,
            is_api: false,
        );

        if ($result['status'] === 'skipped') {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Application started');
    })->name('applications.start');

    Route::post('/applications/{uuid}/stop', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        StopApplication::dispatch($application);

        return redirect()->back()->with('success', 'Application stopped');
    })->name('applications.stop');

    Route::post('/applications/{uuid}/restart', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            restart_only: true,
            is_api: false,
        );

        if ($result['status'] === 'skipped') {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Application restarted');
    })->name('applications.restart');

    Route::delete('/applications/{uuid}', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        DeleteResourceJob::dispatch(
            resource: $application,
            deleteVolumes: true,
            deleteConnectedNetworks: true,
            deleteConfigurations: true,
            dockerCleanup: true
        );

        return redirect()->route('applications.index')->with('success', 'Application deletion queued');
    })->name('applications.destroy');

    // Application Rollback Routes (Saturn)
    Route::get('/applications/{uuid}/rollback', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return \Inertia\Inertia::render('Applications/Rollback/Index', [
            'application' => $application,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.rollback');

    Route::get('/applications/{uuid}/rollback/{deploymentUuid}', function (string $uuid, string $deploymentUuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return \Inertia\Inertia::render('Applications/Rollback/Show', [
            'application' => $application,
            'deploymentUuid' => $deploymentUuid,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.rollback.show');

    // Application Preview Deployment Routes (Saturn)
    Route::get('/applications/{uuid}/previews', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual preview deployments from database
        $previews = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('pull_request_id', '!=', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('pull_request_id')
            ->map(function ($deployments, $prId) {
                $latest = $deployments->first();

                return [
                    'pull_request_id' => $prId,
                    'status' => $latest->status,
                    'commit' => $latest->commit,
                    'commit_message' => $latest->commitMessage(),
                    'created_at' => $latest->created_at,
                    'updated_at' => $latest->updated_at,
                    'deployments_count' => $deployments->count(),
                ];
            })
            ->values();

        return \Inertia\Inertia::render('Applications/Previews/Index', [
            'application' => $application,
            'previews' => $previews,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.previews');

    Route::get('/applications/{uuid}/previews/settings', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Get preview settings from application
        $settings = [
            'preview_url_template' => $application->preview_url_template,
            'instant_deploy_preview' => $application->instant_deploy_preview ?? false,
        ];

        return \Inertia\Inertia::render('Applications/Previews/Settings', [
            'application' => $application,
            'settings' => $settings,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.previews.settings');

    Route::get('/applications/{uuid}/previews/{previewUuid}', function (string $uuid, string $previewUuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual preview deployment from database
        $preview = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('deployment_uuid', $previewUuid)
            ->firstOrFail();

        $previewData = [
            'deployment_uuid' => $preview->deployment_uuid,
            'pull_request_id' => $preview->pull_request_id,
            'status' => $preview->status,
            'commit' => $preview->commit,
            'commit_message' => $preview->commitMessage(),
            'created_at' => $preview->created_at,
            'updated_at' => $preview->updated_at,
            'started_at' => $preview->started_at,
            'finished_at' => $preview->finished_at,
        ];

        return \Inertia\Inertia::render('Applications/Previews/Show', [
            'application' => $application,
            'preview' => $previewData,
            'previewUuid' => $previewUuid,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.previews.show');

    // Application Settings Routes (Saturn)
    Route::get('/applications/{uuid}/settings', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return \Inertia\Inertia::render('Applications/Settings/Index', [
            'application' => $application,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.settings');

    Route::get('/applications/{uuid}/settings/domains', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Parse domains from fqdn field (comma-separated)
        $domains = [];
        if ($application->fqdn) {
            $fqdns = explode(',', $application->fqdn);
            foreach ($fqdns as $index => $fqdn) {
                $fqdn = trim($fqdn);
                if ($fqdn) {
                    $domains[] = [
                        'id' => $index,
                        'domain' => $fqdn,
                        'is_primary' => $index === 0,
                    ];
                }
            }
        }

        return \Inertia\Inertia::render('Applications/Settings/Domains', [
            'application' => $application,
            'domains' => $domains,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.settings.domains');

    Route::get('/applications/{uuid}/settings/variables', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual environment variables from database
        $variables = \App\Models\EnvironmentVariable::where('resourceable_type', \App\Models\Application::class)
            ->where('resourceable_id', $application->id)
            ->where('is_preview', false)
            ->orderBy('key')
            ->get()
            ->map(function ($var) {
                return [
                    'id' => $var->id,
                    'key' => $var->key,
                    'value' => $var->value,
                    'is_multiline' => $var->is_multiline,
                    'is_literal' => $var->is_literal,
                    'is_runtime' => $var->is_runtime,
                    'is_buildtime' => $var->is_buildtime,
                    'created_at' => $var->created_at,
                ];
            });

        return \Inertia\Inertia::render('Applications/Settings/Variables', [
            'application' => $application,
            'variables' => $variables,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.settings.variables');

    // Application Logs Route (Saturn)
    Route::get('/applications/{uuid}/logs', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return \Inertia\Inertia::render('Applications/Logs', [
            'application' => $application,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.logs');

    // Application Deployments Route (Saturn)
    Route::get('/applications/{uuid}/deployments', function (string $uuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual deployments from database
        $deployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('pull_request_id', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'commit_message' => $deployment->commitMessage(),
                    'created_at' => $deployment->created_at,
                    'updated_at' => $deployment->updated_at,
                    'started_at' => $deployment->started_at,
                    'finished_at' => $deployment->finished_at,
                ];
            });

        return \Inertia\Inertia::render('Applications/Deployments', [
            'application' => $application,
            'deployments' => $deployments,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.deployments');

    // Application Deployment Details Route (Saturn)
    Route::get('/applications/{uuid}/deployments/{deploymentUuid}', function (string $uuid, string $deploymentUuid) {
        $application = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $deployment = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('deployment_uuid', $deploymentUuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        // Parse logs from JSON
        $logs = [];
        if ($deployment->logs) {
            $rawLogs = json_decode($deployment->logs, true);
            if (is_array($rawLogs)) {
                $logs = collect($rawLogs)
                    ->filter(fn ($log) => ! ($log['hidden'] ?? false))
                    ->map(fn ($log) => [
                        'output' => $log['output'] ?? '',
                        'type' => $log['type'] ?? 'stdout',
                        'timestamp' => $log['timestamp'] ?? null,
                        'order' => $log['order'] ?? 0,
                    ])
                    ->sortBy('order')
                    ->values()
                    ->all();
            }
        }

        // Calculate duration
        $duration = null;
        if ($deployment->created_at && $deployment->updated_at && in_array($deployment->status, ['finished', 'failed', 'cancelled'])) {
            $duration = $deployment->updated_at->diffInSeconds($deployment->created_at);
        }

        // Get server info
        $server = $deployment->server;

        return \Inertia\Inertia::render('Applications/DeploymentDetails', [
            'application' => [
                'id' => $application->id,
                'uuid' => $application->uuid,
                'name' => $application->name,
                'git_repository' => $application->git_repository,
                'git_branch' => $application->git_branch,
            ],
            'deployment' => [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commitMessage(),
                'is_webhook' => $deployment->is_webhook,
                'is_api' => $deployment->is_api,
                'force_rebuild' => $deployment->force_rebuild,
                'rollback' => $deployment->rollback,
                'only_this_server' => $deployment->only_this_server,
                'created_at' => $deployment->created_at,
                'updated_at' => $deployment->updated_at,
                'duration' => $duration,
                'server_name' => $server?->name ?? 'Unknown',
                'server_id' => $deployment->server_id,
            ],
            'logs' => $logs,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    })->name('applications.deployment.show');

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

    // Settings
    Route::get('/settings', function () {
        return \Inertia\Inertia::render('Settings/Index');
    })->name('settings.index');

    Route::get('/settings/account', function () {
        return \Inertia\Inertia::render('Settings/Account');
    })->name('settings.account');

    Route::get('/settings/team', function () {
        $team = currentTeam();

        $members = $team->members->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role ?? 'member',
            'joinedAt' => $user->pivot->created_at?->toISOString() ?? $user->created_at->toISOString(),
        ]);

        $invitations = $team->invitations->map(fn ($invitation) => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role ?? 'member',
            'sentAt' => $invitation->created_at->toISOString(),
        ]);

        return \Inertia\Inertia::render('Settings/Team', [
            'members' => $members,
            'invitations' => $invitations,
        ]);
    })->name('settings.team');

    Route::get('/settings/billing', function () {
        return \Inertia\Inertia::render('Settings/Billing/Index');
    })->name('settings.billing');

    Route::get('/settings/billing/plans', function () {
        return \Inertia\Inertia::render('Settings/Billing/Plans');
    })->name('settings.billing.plans');

    Route::get('/settings/billing/payment-methods', function () {
        return \Inertia\Inertia::render('Settings/Billing/PaymentMethods');
    })->name('settings.billing.payment-methods');

    Route::get('/settings/billing/invoices', function () {
        return \Inertia\Inertia::render('Settings/Billing/Invoices');
    })->name('settings.billing.invoices');

    Route::get('/settings/billing/usage', function () {
        return \Inertia\Inertia::render('Settings/Billing/Usage');
    })->name('settings.billing.usage');

    Route::get('/settings/tokens', function () {
        $tokens = auth()->user()->tokens->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toISOString(),
            'created_at' => $token->created_at->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
        ]);

        return \Inertia\Inertia::render('Settings/Tokens', [
            'tokens' => $tokens,
        ]);
    })->name('settings.tokens');

    Route::get('/settings/integrations', function () {
        return \Inertia\Inertia::render('Settings/Integrations');
    })->name('settings.integrations.legacy');

    Route::get('/settings/security', function () {
        return \Inertia\Inertia::render('Settings/Security');
    })->name('settings.security');

    Route::get('/settings/workspace', function () {
        return \Inertia\Inertia::render('Settings/Workspace');
    })->name('settings.workspace');

    // Notification Settings
    Route::get('/settings/notifications', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Index', [
            'channels' => [
                'discord' => [
                    'enabled' => $team->discordNotificationSettings->discord_enabled ?? false,
                    'configured' => ! empty($team->discordNotificationSettings->discord_webhook_url),
                ],
                'slack' => [
                    'enabled' => $team->slackNotificationSettings->slack_enabled ?? false,
                    'configured' => ! empty($team->slackNotificationSettings->slack_webhook_url),
                ],
                'telegram' => [
                    'enabled' => $team->telegramNotificationSettings->telegram_enabled ?? false,
                    'configured' => ! empty($team->telegramNotificationSettings->telegram_token),
                ],
                'email' => [
                    'enabled' => $team->emailNotificationSettings->isEnabled() ?? false,
                    'configured' => $team->emailNotificationSettings->smtp_enabled || $team->emailNotificationSettings->resend_enabled || $team->emailNotificationSettings->use_instance_email_settings,
                ],
                'webhook' => [
                    'enabled' => $team->webhookNotificationSettings->webhook_enabled ?? false,
                    'configured' => ! empty($team->webhookNotificationSettings->webhook_url),
                ],
                'pushover' => [
                    'enabled' => $team->pushoverNotificationSettings->pushover_enabled ?? false,
                    'configured' => ! empty($team->pushoverNotificationSettings->pushover_user_key),
                ],
            ],
        ]);
    })->name('settings.notifications');

    Route::get('/settings/notifications/discord', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Discord', [
            'settings' => $team->discordNotificationSettings,
        ]);
    })->name('settings.notifications.discord');

    Route::get('/settings/notifications/slack', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Slack', [
            'settings' => $team->slackNotificationSettings,
        ]);
    })->name('settings.notifications.slack');

    Route::get('/settings/notifications/telegram', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Telegram', [
            'settings' => $team->telegramNotificationSettings,
        ]);
    })->name('settings.notifications.telegram');

    Route::get('/settings/notifications/email', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Email', [
            'settings' => $team->emailNotificationSettings,
            'canUseInstanceSettings' => config('saturn.is_self_hosted'),
        ]);
    })->name('settings.notifications.email');

    Route::get('/settings/notifications/webhook', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Webhook', [
            'settings' => $team->webhookNotificationSettings,
        ]);
    })->name('settings.notifications.webhook');

    Route::get('/settings/notifications/pushover', function () {
        $team = auth()->user()->currentTeam();

        return \Inertia\Inertia::render('Settings/Notifications/Pushover', [
            'settings' => $team->pushoverNotificationSettings,
        ]);
    })->name('settings.notifications.pushover');

    // Account Settings POST routes
    Route::post('/settings/account/profile', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);
        $user->update($request->only(['name', 'email']));

        return redirect()->back()->with('success', 'Profile updated successfully');
    })->name('settings.account.profile');

    Route::post('/settings/account/password', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();
        if (! \Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password changed successfully');
    })->name('settings.account.password');

    Route::post('/settings/account/2fa', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();

        // Toggle 2FA based on current state
        if ($user->two_factor_secret) {
            // 2FA is enabled, disable it
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            return redirect()->back()->with('success', 'Two-factor authentication has been disabled');
        }

        return redirect()->back()->with('info', 'Please enable two-factor authentication from your account settings');
    })->name('settings.account.2fa');

    Route::delete('/settings/account', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();

        // Validate password confirmation
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return redirect()->back()->withErrors(['password' => 'The provided password is incorrect']);
        }

        // Log out the user
        \Illuminate\Support\Facades\Auth::logout();

        // Delete the user (this will trigger all cleanup logic in the User model's deleting event)
        $user->delete();

        return redirect('/')->with('success', 'Account deleted successfully');
    })->name('settings.account.delete');

    // Security Settings POST/DELETE routes
    Route::delete('/settings/security/sessions/{id}', function (string $id) {
        $user = auth()->user();
        $currentSessionId = session()->getId();

        // Prevent deleting current session
        if ($id === $currentSessionId) {
            return redirect()->back()->withErrors(['session' => 'Cannot revoke your current session']);
        }

        // Delete the specified session
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->back()->with('success', 'Session revoked successfully');
    })->name('settings.security.sessions.revoke');

    Route::delete('/settings/security/sessions/all', function () {
        $user = auth()->user();
        $currentSessionId = session()->getId();

        // Delete all sessions except the current one
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return redirect()->back()->with('success', 'All other sessions revoked successfully');
    })->name('settings.security.sessions.revoke-all');

    Route::post('/settings/security/ip-allowlist', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'ip_address' => 'required|ip',
            'description' => 'nullable|string|max:255',
        ]);

        $settings = \App\Models\InstanceSettings::get();
        $allowedIps = $settings->allowed_ips ? json_decode($settings->allowed_ips, true) : [];

        // Check if IP already exists
        foreach ($allowedIps as $item) {
            if ($item['ip'] === $request->ip_address) {
                return redirect()->back()->withErrors(['ip_address' => 'This IP address is already in the allowlist']);
            }
        }

        // Add new IP to the list
        $allowedIps[] = [
            'ip' => $request->ip_address,
            'description' => $request->description ?? '',
            'created_at' => now()->toISOString(),
        ];

        $settings->update(['allowed_ips' => json_encode($allowedIps)]);

        return redirect()->back()->with('success', 'IP address added to allowlist');
    })->name('settings.security.ip-allowlist.store');

    Route::delete('/settings/security/ip-allowlist/{id}', function (string $id) {
        $settings = \App\Models\InstanceSettings::get();
        $allowedIps = $settings->allowed_ips ? json_decode($settings->allowed_ips, true) : [];

        // Remove IP by index (id is the array index)
        $index = (int) $id;
        if (isset($allowedIps[$index])) {
            array_splice($allowedIps, $index, 1);
            $settings->update(['allowed_ips' => json_encode(array_values($allowedIps))]);

            return redirect()->back()->with('success', 'IP address removed from allowlist');
        }

        return redirect()->back()->withErrors(['ip_allowlist' => 'IP address not found']);
    })->name('settings.security.ip-allowlist.destroy');

    // Team Settings POST/DELETE routes
    Route::post('/settings/team/members/{id}/role', function (string $id, \Illuminate\Http\Request $request) {
        $request->validate([
            'role' => 'required|string|in:owner,admin,member',
        ]);

        $team = currentTeam();
        $currentUser = auth()->user();

        // Check if the current user is an admin or owner
        if (! $currentUser->isAdmin()) {
            return redirect()->back()->withErrors(['role' => 'You do not have permission to update member roles']);
        }

        // Find the member in the team
        $member = $team->members()->where('user_id', $id)->first();
        if (! $member) {
            return redirect()->back()->withErrors(['role' => 'Member not found in this team']);
        }

        // Prevent changing own role
        if ($member->id == $currentUser->id) {
            return redirect()->back()->withErrors(['role' => 'You cannot change your own role']);
        }

        // Update the role in the pivot table
        $team->members()->updateExistingPivot($id, ['role' => $request->role]);

        return redirect()->back()->with('success', 'Member role updated successfully');
    })->name('settings.team.members.update-role');

    Route::delete('/settings/team/members/{id}', function (string $id) {
        $team = currentTeam();
        $currentUser = auth()->user();

        // Check if the current user is an admin or owner
        if (! $currentUser->isAdmin()) {
            return redirect()->back()->withErrors(['member' => 'You do not have permission to remove members']);
        }

        // Find the member in the team
        $member = $team->members()->where('user_id', $id)->first();
        if (! $member) {
            return redirect()->back()->withErrors(['member' => 'Member not found in this team']);
        }

        // Prevent removing yourself
        if ($member->id == $currentUser->id) {
            return redirect()->back()->withErrors(['member' => 'You cannot remove yourself from the team']);
        }

        // Check if this is the last owner
        $owners = $team->members()->wherePivot('role', 'owner')->get();
        if ($member->pivot->role === 'owner' && $owners->count() <= 1) {
            return redirect()->back()->withErrors(['member' => 'Cannot remove the last owner of the team']);
        }

        // Remove the member from the team
        $team->members()->detach($id);

        return redirect()->back()->with('success', 'Member removed from team successfully');
    })->name('settings.team.members.destroy');

    Route::post('/settings/team/invite', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|in:owner,admin,member',
        ]);

        $email = strtolower($request->email);
        $role = $request->role;
        $team = currentTeam();

        // Check if user is already a member
        $existingMember = $team->members()->where('email', $email)->first();
        if ($existingMember) {
            return redirect()->back()->with('error', 'User is already a member of this team');
        }

        // Check for existing pending invitation
        $existingInvitation = \App\Models\TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->first();
        if ($existingInvitation) {
            return redirect()->back()->with('error', 'An invitation has already been sent to this email');
        }

        // Create the invitation
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $link = url("/invitations/{$uuid}");

        $invitation = \App\Models\TeamInvitation::create([
            'team_id' => $team->id,
            'uuid' => $uuid,
            'email' => $email,
            'role' => $role,
            'link' => $link,
            'via' => 'link',
        ]);

        // Try to send email notification if email settings are configured
        try {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user) {
                $user->notify(new \App\Notifications\TransactionalEmails\InvitationLink($user));
            }
        } catch (\Exception $e) {
            // Email sending failed, but invitation still created
            \Illuminate\Support\Facades\Log::warning('Failed to send invitation email: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Invitation sent successfully');
    })->name('settings.team.invite.store');

    // Tokens POST/DELETE routes
    Route::post('/settings/tokens', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string|in:read,write,deploy,root,read:sensitive',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $user = auth()->user();
        $abilities = $request->abilities ?? ['read'];
        $expiresAt = $request->expires_at ? new \DateTime($request->expires_at) : null;

        // Create the token using Sanctum
        $tokenResult = $user->createToken($request->name, $abilities, $expiresAt);

        // Return JSON response with the token (only shown once)
        return response()->json([
            'token' => $tokenResult->plainTextToken,
            'id' => $tokenResult->accessToken->id,
            'name' => $request->name,
            'abilities' => $abilities,
            'created_at' => $tokenResult->accessToken->created_at->toISOString(),
            'expires_at' => $expiresAt?->format('c'),
        ]);
    })->name('settings.tokens.store');

    Route::delete('/settings/tokens/{id}', function (string $id) {
        $user = auth()->user();

        // Find and delete the token
        $token = $user->tokens()->where('id', $id)->first();
        if (! $token) {
            return redirect()->back()->withErrors(['token' => 'Token not found']);
        }

        $token->delete();

        return redirect()->back()->with('success', 'API token revoked successfully');
    })->name('settings.tokens.destroy');

    // Workspace POST/DELETE routes
    Route::post('/settings/workspace', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $team = currentTeam();
        $user = auth()->user();

        // Check if the user is an admin or owner
        if (! $user->isAdmin()) {
            return redirect()->back()->withErrors(['workspace' => 'You do not have permission to update workspace settings']);
        }

        // Update the team
        $team->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Workspace updated successfully');
    })->name('settings.workspace.update');

    Route::delete('/settings/workspace', function () {
        $team = currentTeam();
        $user = auth()->user();

        // Check if the user is an owner
        if (! $user->isOwner()) {
            return redirect()->back()->withErrors(['workspace' => 'Only workspace owners can delete the workspace']);
        }

        // Prevent deletion of personal team
        if ($team->personal_team) {
            return redirect()->back()->withErrors(['workspace' => 'Cannot delete your personal workspace']);
        }

        // Prevent deletion of root team
        if ($team->id === 0) {
            return redirect()->back()->withErrors(['workspace' => 'Cannot delete the root team']);
        }

        // Switch to personal team before deleting
        $personalTeam = $user->teams()->where('personal_team', true)->first();
        if ($personalTeam) {
            session(['currentTeam' => $personalTeam]);
        }

        // Delete the team (this will trigger all cleanup logic in the Team model's deleting event)
        $team->delete();

        return redirect('/')->with('success', 'Workspace deleted successfully');
    })->name('settings.workspace.delete');

    // Notification Settings POST routes
    Route::post('/settings/notifications/discord', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();
        $settings = $team->discordNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Discord notification settings saved successfully');
    })->name('settings.notifications.discord.update');

    Route::post('/settings/notifications/discord/test', function () {
        $team = auth()->user()->currentTeam();
        $team->notify(new \App\Notifications\Test(channel: 'discord'));

        return redirect()->back()->with('success', 'Test notification sent to Discord');
    })->name('settings.notifications.discord.test');

    Route::post('/settings/notifications/slack', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();
        $settings = $team->slackNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Slack notification settings saved successfully');
    })->name('settings.notifications.slack.update');

    Route::post('/settings/notifications/slack/test', function () {
        $team = auth()->user()->currentTeam();
        $team->notify(new \App\Notifications\Test(channel: 'slack'));

        return redirect()->back()->with('success', 'Test notification sent to Slack');
    })->name('settings.notifications.slack.test');

    Route::post('/settings/notifications/telegram', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();
        $settings = $team->telegramNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Telegram notification settings saved successfully');
    })->name('settings.notifications.telegram.update');

    Route::post('/settings/notifications/telegram/test', function () {
        $team = auth()->user()->currentTeam();
        $team->notify(new \App\Notifications\Test(channel: 'telegram'));

        return redirect()->back()->with('success', 'Test notification sent to Telegram');
    })->name('settings.notifications.telegram.test');

    Route::post('/settings/notifications/email', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();
        $settings = $team->emailNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Email notification settings saved successfully');
    })->name('settings.notifications.email.update');

    Route::post('/settings/notifications/email/test', function () {
        $team = auth()->user()->currentTeam();
        $team->notify(new \App\Notifications\Test(channel: 'email'));

        return redirect()->back()->with('success', 'Test email sent');
    })->name('settings.notifications.email.test');

    Route::post('/settings/notifications/webhook', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();
        $settings = $team->webhookNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Webhook notification settings saved successfully');
    })->name('settings.notifications.webhook.update');

    Route::post('/settings/notifications/webhook/test', function () {
        $team = auth()->user()->currentTeam();
        $team->notify(new \App\Notifications\Test(channel: 'webhook'));

        return redirect()->back()->with('success', 'Test webhook sent');
    })->name('settings.notifications.webhook.test');

    Route::post('/settings/notifications/pushover', function (\Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();
        $settings = $team->pushoverNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Pushover notification settings saved successfully');
    })->name('settings.notifications.pushover.update');

    Route::post('/settings/notifications/pushover/test', function () {
        $team = auth()->user()->currentTeam();
        $team->notify(new \App\Notifications\Test(channel: 'pushover'));

        return redirect()->back()->with('success', 'Test notification sent to Pushover');
    })->name('settings.notifications.pushover.test');

    Route::post('/settings/notifications/{channel}/toggle', function (string $channel, \Illuminate\Http\Request $request) {
        $team = auth()->user()->currentTeam();

        $settingsMap = [
            'discord' => 'discordNotificationSettings',
            'slack' => 'slackNotificationSettings',
            'telegram' => 'telegramNotificationSettings',
            'email' => 'emailNotificationSettings',
            'webhook' => 'webhookNotificationSettings',
            'pushover' => 'pushoverNotificationSettings',
        ];

        if (! isset($settingsMap[$channel])) {
            return redirect()->back()->withErrors(['channel' => 'Invalid notification channel']);
        }

        $settings = $team->{$settingsMap[$channel]};
        $enabledField = $channel.'_enabled';

        if ($channel === 'email') {
            $settings->update(['smtp_enabled' => $request->input('enabled', false)]);
        } else {
            $settings->update([$enabledField => $request->input('enabled', false)]);
        }

        return redirect()->back()->with('success', ucfirst($channel).' notifications '.($request->input('enabled', false) ? 'enabled' : 'disabled'));
    })->name('settings.notifications.toggle');

    // Database helper functions (wrapped to prevent redeclaration on route caching)
    if (! function_exists('findDatabaseByUuid')) {
        function findDatabaseByUuid(string $uuid)
        {
            // Try to find the database in all types
            $database = \App\Models\StandalonePostgresql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'postgresql'];
            }

            $database = \App\Models\StandaloneMysql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'mysql'];
            }

            $database = \App\Models\StandaloneMariadb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'mariadb'];
            }

            $database = \App\Models\StandaloneMongodb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'mongodb'];
            }

            $database = \App\Models\StandaloneRedis::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'redis'];
            }

            $database = \App\Models\StandaloneKeydb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'keydb'];
            }

            $database = \App\Models\StandaloneDragonfly::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'dragonfly'];
            }

            $database = \App\Models\StandaloneClickhouse::ownedByCurrentTeam()->where('uuid', $uuid)->first();
            if ($database) {
                return [$database, 'clickhouse'];
            }

            abort(404);
        }
    }

    if (! function_exists('formatDatabaseForView')) {
        function formatDatabaseForView($databaseWithType)
        {
            [$database, $type] = is_array($databaseWithType) ? $databaseWithType : [$databaseWithType, 'postgresql'];

            return [
                'id' => $database->id,
                'uuid' => $database->uuid,
                'name' => $database->name,
                'description' => $database->description,
                'database_type' => $type,
                'status' => $database->status(),
                'environment_id' => $database->environment_id,
                'created_at' => $database->created_at,
                'updated_at' => $database->updated_at,
            ];
        }
    }

    if (! function_exists('getAllDatabases')) {
        function getAllDatabases()
        {
            $databases = \App\Models\StandalonePostgresql::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'postgresql']));

            $mysqlDatabases = \App\Models\StandaloneMysql::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'mysql']));

            $mariadbDatabases = \App\Models\StandaloneMariadb::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'mariadb']));

            $mongodbDatabases = \App\Models\StandaloneMongodb::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'mongodb']));

            $redisDatabases = \App\Models\StandaloneRedis::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'redis']));

            $keydbDatabases = \App\Models\StandaloneKeydb::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'keydb']));

            $dragonflyDatabases = \App\Models\StandaloneDragonfly::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'dragonfly']));

            $clickhouseDatabases = \App\Models\StandaloneClickhouse::ownedByCurrentTeamCached()
                ->map(fn ($db) => formatDatabaseForView([$db, 'clickhouse']));

            return $databases
                ->concat($mysqlDatabases)
                ->concat($mariadbDatabases)
                ->concat($mongodbDatabases)
                ->concat($redisDatabases)
                ->concat($keydbDatabases)
                ->concat($dragonflyDatabases)
                ->concat($clickhouseDatabases)
                ->sortByDesc('updated_at')
                ->values();
        }
    }

    // Databases
    Route::get('/databases', function () {
        // Collect all database types
        $formatDb = fn ($db, $type) => [
            'id' => $db->id,
            'uuid' => $db->uuid,
            'name' => $db->name,
            'description' => $db->description,
            'database_type' => $type,
            'status' => $db->status(),
            'environment_id' => $db->environment_id,
            'created_at' => $db->created_at,
            'updated_at' => $db->updated_at,
        ];

        $databases = collect()
            ->concat(\App\Models\StandalonePostgresql::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'postgresql')))
            ->concat(\App\Models\StandaloneMysql::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'mysql')))
            ->concat(\App\Models\StandaloneMariadb::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'mariadb')))
            ->concat(\App\Models\StandaloneMongodb::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'mongodb')))
            ->concat(\App\Models\StandaloneRedis::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'redis')))
            ->concat(\App\Models\StandaloneKeydb::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'keydb')))
            ->concat(\App\Models\StandaloneDragonfly::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'dragonfly')))
            ->concat(\App\Models\StandaloneClickhouse::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'clickhouse')))
            ->sortByDesc('updated_at')
            ->values();

        return \Inertia\Inertia::render('Databases/Index', [
            'databases' => $databases,
        ]);
    })->name('databases.index');

    Route::get('/databases/create', function () {
        return \Inertia\Inertia::render('Databases/Create');
    })->name('databases.create');

    Route::post('/databases', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'database_type' => 'required|string|in:postgresql,mysql,mariadb,mongodb,redis',
            'version' => 'required|string',
            'description' => 'nullable|string',
        ]);

        // Create the appropriate database type
        // Note: This is simplified - real implementation would need to handle
        // destination, environment, and other configuration
        $dbClass = match ($request->database_type) {
            'postgresql' => \App\Models\StandalonePostgresql::class,
            'mysql' => \App\Models\StandaloneMysql::class,
            'mariadb' => \App\Models\StandaloneMariadb::class,
            'mongodb' => \App\Models\StandaloneMongodb::class,
            'redis' => \App\Models\StandaloneRedis::class,
        };

        $database = $dbClass::create([
            'name' => $request->name,
            'description' => $request->description,
            'team_id' => currentTeam()->id,
        ]);

        return redirect()->route('databases.show', $database->uuid);
    })->name('databases.store');

    Route::get('/databases/{uuid}', function (string $uuid) {
        [$database, $type] = findDatabaseByUuid($uuid);

        // Fetch scheduled backups with latest execution
        $scheduledBackups = $database->scheduledBackups()
            ->with(['latest_log', 's3'])
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'uuid' => $backup->uuid,
                    'enabled' => $backup->enabled,
                    'frequency' => $backup->frequency,
                    'save_s3' => $backup->save_s3,
                    's3_storage_id' => $backup->s3_storage_id,
                    'databases_to_backup' => $backup->databases_to_backup,
                    'latest_log' => $backup->latest_log ? [
                        'id' => $backup->latest_log->id,
                        'status' => $backup->latest_log->status,
                        'message' => $backup->latest_log->message,
                        'size' => $backup->latest_log->size,
                        'created_at' => $backup->latest_log->created_at,
                    ] : null,
                ];
            });

        return \Inertia\Inertia::render('Databases/Show', [
            'database' => formatDatabaseForView([$database, $type]),
            'scheduledBackups' => $scheduledBackups,
        ]);
    })->name('databases.show');

    Route::delete('/databases/{uuid}', function (string $uuid) {
        [$database, $type] = findDatabaseByUuid($uuid);
        $database->delete();

        return redirect()->route('databases.index');
    })->name('databases.destroy');

    Route::get('/databases/{uuid}/backups', function (string $uuid) {
        [$database, $type] = findDatabaseByUuid($uuid);

        // Fetch scheduled backups with all executions
        $scheduledBackups = $database->scheduledBackups()
            ->with(['executions' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            }, 's3'])
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'uuid' => $backup->uuid,
                    'enabled' => $backup->enabled,
                    'frequency' => $backup->frequency,
                    'save_s3' => $backup->save_s3,
                    's3_storage_id' => $backup->s3_storage_id,
                    'databases_to_backup' => $backup->databases_to_backup,
                    'executions' => $backup->executions->map(function ($execution) {
                        return [
                            'id' => $execution->id,
                            'status' => $execution->status,
                            'message' => $execution->message,
                            'size' => $execution->size,
                            'filename' => $execution->filename,
                            's3_uploaded' => $execution->s3_uploaded,
                            'created_at' => $execution->created_at,
                            'updated_at' => $execution->updated_at,
                        ];
                    }),
                ];
            });

        return \Inertia\Inertia::render('Databases/Backups', [
            'database' => formatDatabaseForView([$database, $type]),
            'scheduledBackups' => $scheduledBackups,
        ]);
    })->name('databases.backups');

    Route::get('/databases/{uuid}/logs', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Logs', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.logs');

    Route::get('/databases/{uuid}/metrics', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Metrics', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.metrics');

    // API endpoint for real-time database metrics (JSON)
    Route::get('/api/databases/{uuid}/metrics', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMetrics'])
        ->name('databases.metrics.api');

    // API endpoint for historical database metrics (JSON)
    Route::get('/api/databases/{uuid}/metrics/history', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getHistoricalMetrics'])
        ->name('databases.metrics.history.api');

    Route::get('/databases/{uuid}/settings', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Settings/Index', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.settings');

    Route::get('/databases/{uuid}/settings/backups', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Settings/Backups', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.settings.backups');

    Route::patch('/databases/{uuid}/settings/backups', function (string $uuid, \Illuminate\Http\Request $request) {
        [$database, $type] = findDatabaseByUuid($uuid);

        $request->validate([
            'backup_id' => 'nullable|exists:scheduled_database_backups,id',
            'enabled' => 'boolean',
            'frequency' => 'nullable|string',
            'save_s3' => 'boolean',
            's3_storage_id' => 'nullable|exists:s3_storages,id',
            'databases_to_backup' => 'nullable|string',
        ]);

        try {
            if ($request->has('backup_id') && $request->backup_id) {
                // Update existing backup configuration
                $backup = ScheduledDatabaseBackup::where('id', $request->backup_id)
                    ->where('database_id', $database->id)
                    ->where('database_type', $database->getMorphClass())
                    ->firstOrFail();

                $backup->update($request->only([
                    'enabled',
                    'frequency',
                    'save_s3',
                    's3_storage_id',
                    'databases_to_backup',
                ]));
            } else {
                // Create new backup configuration
                ScheduledDatabaseBackup::create([
                    'database_id' => $database->id,
                    'database_type' => $database->getMorphClass(),
                    'team_id' => currentTeam()->id,
                    'enabled' => $request->input('enabled', true),
                    'frequency' => $request->input('frequency', 'daily'),
                    'save_s3' => $request->input('save_s3', false),
                    's3_storage_id' => $request->input('s3_storage_id'),
                    'databases_to_backup' => $request->input('databases_to_backup'),
                ]);
            }

            return redirect()->back()->with('success', 'Backup settings saved successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to save backup settings: '.$e->getMessage()]);
        }
    })->name('databases.settings.backups.update');

    Route::get('/databases/{uuid}/connections', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Connections', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.connections');

    Route::get('/databases/{uuid}/users', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Users', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.users');

    Route::get('/databases/{uuid}/query', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        // Get all databases for the selector
        $allDatabases = getAllDatabases();

        return \Inertia\Inertia::render('Databases/Query', [
            'database' => formatDatabaseForView($database),
            'databases' => $allDatabases,
        ]);
    })->name('databases.query');

    Route::get('/databases/{uuid}/tables', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Tables', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.tables');

    Route::get('/databases/{uuid}/extensions', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Extensions', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.extensions');

    Route::get('/databases/{uuid}/import', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Import', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.import');

    Route::get('/databases/{uuid}/overview', function (string $uuid) {
        $database = findDatabaseByUuid($uuid);

        return \Inertia\Inertia::render('Databases/Overview', [
            'database' => formatDatabaseForView($database),
        ]);
    })->name('databases.overview');

    Route::post('/databases/{uuid}/restart', function (string $uuid) {
        [$database, $type] = findDatabaseByUuid($uuid);

        try {
            RestartDatabase::run($database);

            return redirect()->back()->with('success', 'Database restart initiated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to restart database: '.$e->getMessage()]);
        }
    })->name('databases.restart');

    // Observability routes
    Route::get('/observability', function () {
        return \Inertia\Inertia::render('Observability/Index');
    })->name('observability.index');

    Route::get('/observability/metrics', function () {
        return \Inertia\Inertia::render('Observability/Metrics');
    })->name('observability.metrics');

    Route::get('/observability/logs', function () {
        return \Inertia\Inertia::render('Observability/Logs');
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

            return \Inertia\Inertia::render('Admin/Index', [
                'stats' => $stats,
                'recentActivity' => [],
            ]);
        })->name('admin.index');

        Route::get('/users', function () {
            // Fetch all users with their teams
            $users = \App\Models\User::with(['teams'])
                ->withCount(['teams'])
                ->latest()
                ->paginate(50);

            return \Inertia\Inertia::render('Admin/Users/Index', [
                'users' => $users,
            ]);
        })->name('admin.users.index');

        Route::get('/users/{id}', function (int $id) {
            // Fetch specific user with all relationships
            $user = \App\Models\User::with(['teams.projects.environments'])
                ->withCount(['teams'])
                ->findOrFail($id);

            return \Inertia\Inertia::render('Admin/Users/Show', [
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

            return \Inertia\Inertia::render('Admin/Applications/Index', [
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

            return \Inertia\Inertia::render('Admin/Databases/Index', [
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

            return \Inertia\Inertia::render('Admin/Services/Index', [
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

            return \Inertia\Inertia::render('Admin/Servers/Index', [
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

            return \Inertia\Inertia::render('Admin/Deployments/Index', [
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

            return \Inertia\Inertia::render('Admin/Teams/Index', [
                'teams' => $teams,
            ]);
        })->name('admin.teams.index');

        Route::get('/settings', function () {
            // Fetch instance settings (admin view)
            $settings = \App\Models\InstanceSettings::get();

            return \Inertia\Inertia::render('Admin/Settings/Index', [
                'settings' => $settings,
            ]);
        })->name('admin.settings.index');

        Route::get('/logs', function () {
            // Fetch system logs (admin view)
            $logPath = storage_path('logs/laravel.log');
            $logs = [];

            if (file_exists($logPath)) {
                $logContent = file_get_contents($logPath);
                $logLines = array_filter(explode("\n", $logContent));

                // Get last 100 log lines
                $logLines = array_slice($logLines, -100);

                foreach ($logLines as $line) {
                    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
                        $logs[] = [
                            'timestamp' => $matches[1],
                            'environment' => $matches[2],
                            'level' => $matches[3],
                            'message' => $matches[4],
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

            return \Inertia\Inertia::render('Admin/Logs/Index', [
                'logs' => $logs,
            ]);
        })->name('admin.logs.index');
    });

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

    // Projects additional routes
    Route::get('/projects/{uuid}/local-setup', function (string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Projects/LocalSetup', [
            'project' => $project,
        ]);
    })->name('projects.local-setup');

    Route::get('/projects/{uuid}/variables', function (string $uuid) {
        $project = \App\Models\Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return \Inertia\Inertia::render('Projects/Variables', [
            'project' => $project,
        ]);
    })->name('projects.variables');

    // Settings additional routes
    // Redirect legacy api-tokens route to new tokens route
    Route::get('/settings/api-tokens', function () {
        return redirect()->route('settings.tokens');
    })->name('settings.api-tokens');

    Route::get('/settings/audit-log', function () {
        return \Inertia\Inertia::render('Settings/AuditLog');
    })->name('settings.audit-log');

    Route::get('/settings/usage', function () {
        return \Inertia\Inertia::render('Settings/Usage');
    })->name('settings.usage');

    Route::get('/settings/integrations', function () {
        return \Inertia\Inertia::render('Settings/Integrations');
    })->name('settings.integrations');

    Route::get('/settings/members/{uuid}', function (string $uuid) {
        return \Inertia\Inertia::render('Settings/Members/Show', ['uuid' => $uuid]);
    })->name('settings.members.show');

    Route::get('/settings/team/activity', function () {
        return \Inertia\Inertia::render('Settings/Team/Activity');
    })->name('settings.team.activity');

    Route::get('/settings/team/index', function () {
        return \Inertia\Inertia::render('Settings/Team/Index');
    })->name('settings.team.team-index');

    Route::get('/settings/team/invite', function () {
        return \Inertia\Inertia::render('Settings/Team/Invite');
    })->name('settings.team.invite');

    Route::get('/settings/team/roles', function () {
        return \Inertia\Inertia::render('Settings/Team/Roles');
    })->name('settings.team.roles');

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
