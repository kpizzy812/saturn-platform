<?php

/**
 * Server routes for Saturn Platform
 *
 * These routes handle server management, proxy configuration, and monitoring.
 * All routes require authentication and email verification.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Visus\Cuid2\Cuid2;

// Servers
Route::get('/servers', function () {
    $servers = \App\Models\Server::ownedByCurrentTeam()
        ->with('settings')
        ->get();

    return Inertia::render('Servers/Index', [
        'servers' => $servers,
    ]);
})->name('servers.index');

Route::get('/servers/create', function () {
    $privateKeys = \App\Models\PrivateKey::ownedByCurrentTeam()->get();

    return Inertia::render('Servers/Create', [
        'privateKeys' => $privateKeys,
    ]);
})->name('servers.create');

Route::post('/servers', function (Request $request) {
    Gate::authorize('create', \App\Models\Server::class);

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:500',
        'ip' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
            $v = trim($value);
            if (! filter_var($v, FILTER_VALIDATE_IP) && ! preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/', $v)) {
                $fail('The IP must be a valid IP address or hostname.');
            }
        }],
        'port' => 'required|integer|min:1|max:65535',
        'user' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
        'private_key' => 'required_without:private_key_id|nullable|string',
        'private_key_id' => 'required_without:private_key|nullable|exists:private_keys,id',
    ]);

    $validated['team_id'] = currentTeam()->id;
    $validated['uuid'] = (string) new Cuid2;

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

    return Inertia::render('Servers/Show', [
        'server' => $server,
    ]);
})->name('servers.show');

Route::get('/servers/{uuid}/settings', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Settings/Index', ['server' => $server]);
})->name('servers.settings');

Route::get('/servers/{uuid}/settings/general', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Settings/General', ['server' => $server]);
})->name('servers.settings.general');

Route::patch('/servers/{uuid}/settings/general', function (string $uuid, Request $request) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    Gate::authorize('update', $server);

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:500',
        'ip' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
            $v = trim($value);
            if (! filter_var($v, FILTER_VALIDATE_IP) && ! preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/', $v)) {
                $fail('The IP must be a valid IP address or hostname.');
            }
        }],
        'port' => 'required|integer|min:1|max:65535',
        'user' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
    ]);

    $server->update($validated);

    return redirect()->back()->with('success', 'Server settings updated successfully');
})->name('servers.settings.general.update');

Route::get('/servers/{uuid}/settings/docker', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Settings/Docker', ['server' => $server]);
})->name('servers.settings.docker');

Route::get('/servers/{uuid}/settings/network', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Settings/Network', ['server' => $server]);
})->name('servers.settings.network');

Route::get('/servers/{uuid}/destinations', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    // Fetch destinations from database
    $destinations = $server->destinations();

    return Inertia::render('Servers/Destinations/Index', [
        'server' => $server,
        'destinations' => $destinations,
    ]);
})->name('servers.destinations');

Route::get('/servers/{uuid}/destinations/create', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Destinations/Create', ['server' => $server]);
})->name('servers.destinations.create');

Route::get('/servers/{uuid}/resources', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    // Count actual resources on this server
    $applications = $server->applications()->count();
    $databases = $server->databases()->count();
    $services = $server->services()->count();

    return Inertia::render('Servers/Resources/Index', [
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

    return Inertia::render('Servers/LogDrains/Index', [
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

    return Inertia::render('Servers/PrivateKeys/Index', [
        'server' => $server,
        'privateKeys' => $privateKeys,
    ]);
})->name('servers.private-keys');

Route::get('/servers/{uuid}/cleanup', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    // Query Docker for real-time unused resource counts
    $cleanupStats = null;
    if ($server->isFunctional()) {
        try {
            // Count unused images
            $imagesResult = trim(instant_remote_process(
                ["docker images -f 'dangling=true' -q 2>/dev/null | wc -l"],
                $server,
                false
            ) ?? '0');

            // Count stopped containers
            $containersResult = trim(instant_remote_process(
                ["docker ps -f 'status=exited' -f 'status=dead' -q 2>/dev/null | wc -l"],
                $server,
                false
            ) ?? '0');

            // Count unused volumes
            $volumesResult = trim(instant_remote_process(
                ["docker volume ls -f 'dangling=true' -q 2>/dev/null | wc -l"],
                $server,
                false
            ) ?? '0');

            // Count unused networks (excluding default ones)
            $networksResult = trim(instant_remote_process(
                ["docker network ls -f 'dangling=true' -q 2>/dev/null | wc -l"],
                $server,
                false
            ) ?? '0');

            // Get total reclaimable size
            $sizeResult = trim(instant_remote_process(
                ["docker system df --format '{{.Reclaimable}}' 2>/dev/null | head -1"],
                $server,
                false
            ) ?? '0B');

            $cleanupStats = [
                'unused_images' => (int) $imagesResult,
                'unused_containers' => (int) $containersResult,
                'unused_volumes' => (int) $volumesResult,
                'unused_networks' => (int) $networksResult,
                'total_size' => $sizeResult ?: '0B',
            ];
        } catch (\Exception $e) {
            // Fallback: server unreachable
            $cleanupStats = null;
        }
    }

    return Inertia::render('Servers/Cleanup/Index', [
        'server' => $server,
        'cleanupStats' => $cleanupStats,
    ]);
})->name('servers.cleanup');

Route::get('/servers/{uuid}/metrics', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Metrics/Index', ['server' => $server]);
})->name('servers.metrics');

Route::get('/servers/{uuid}/terminal', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Servers/Terminal/Index', [
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

        return Inertia::render('Servers/Proxy/Index', [
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

        return Inertia::render('Servers/Proxy/Configuration', [
            'server' => $server,
            'configuration' => "version: '3'\nservices:\n  traefik:\n    image: traefik:latest\n    # Add your configuration here",
            'filePath' => '/data/saturn/proxy/docker-compose.yml',
        ]);
    })->name('servers.proxy.configuration');

    Route::post('/configuration', function (string $uuid, Request $request) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('manageProxy', $server);

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

        Gate::authorize('manageProxy', $server);

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

        return Inertia::render('Servers/Proxy/Logs', [
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

        return Inertia::render('Servers/Proxy/Domains', [
            'server' => $server,
            'domains' => $domains->values()->toArray(),
        ]);
    })->name('servers.proxy.domains');

    Route::post('/domains', function (string $uuid, Request $request) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('manageProxy', $server);

        // Note: Domains in Saturn Platform are managed at the application/service level,
        // not directly through the proxy. To add a domain, configure it on the application or service.
        return redirect()->back()->with('info', 'Domains are managed at the application/service level. Please configure domains on your applications or services.');
    })->name('servers.proxy.domains.store');

    Route::patch('/domains/{domainId}', function (string $uuid, int $domainId, Request $request) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('manageProxy', $server);

        // Note: Domains in Saturn Platform are managed at the application/service level,
        // not directly through the proxy. To update a domain, configure it on the application or service.
        return redirect()->back()->with('info', 'Domains are managed at the application/service level. Please update domains on your applications or services.');
    })->name('servers.proxy.domains.update');

    Route::delete('/domains/{domainId}', function (string $uuid, int $domainId) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('manageProxy', $server);

        // Note: Domains in Saturn Platform are managed at the application/service level,
        // not directly through the proxy. To remove a domain, configure it on the application or service.
        return redirect()->back()->with('info', 'Domains are managed at the application/service level. Please remove domains from your applications or services.');
    })->name('servers.proxy.domains.destroy');

    Route::post('/domains/{domainId}/renew-certificate', function (string $uuid, int $domainId) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('manageProxy', $server);

        // Note: SSL certificates are automatically managed and renewed by the proxy.
        // Certificate renewal happens automatically when close to expiration.
        return redirect()->back()->with('info', 'SSL certificates are automatically renewed by the proxy. No manual action is required.');
    })->name('servers.proxy.domains.renew-certificate');

    Route::get('/settings', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Proxy/Settings', [
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

    Route::post('/settings', function (string $uuid, Request $request) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('manageProxy', $server);

        // Note: Proxy settings in Saturn Platform are typically managed through the proxy configuration file
        // and server settings. This is a placeholder for future proxy-specific settings.
        return redirect()->back()->with('info', 'Proxy settings are currently managed through the proxy configuration file. Please use the Configuration tab to modify proxy settings.');
    })->name('servers.proxy.settings.update');

    Route::post('/restart', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('manageProxy', $server);

        try {
            \App\Jobs\RestartProxyJob::dispatch($server);

            return redirect()->back()->with('success', 'Proxy restart initiated');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to restart proxy: '.$e->getMessage());
        }
    })->name('servers.proxy.restart');

    Route::post('/start', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('manageProxy', $server);

        try {
            \App\Actions\Proxy\StartProxy::run($server);

            return redirect()->back()->with('success', 'Proxy start initiated');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to start proxy: '.$e->getMessage());
        }
    })->name('servers.proxy.start');

    Route::post('/stop', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('manageProxy', $server);

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

        return Inertia::render('Servers/Sentinel/Index', [
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

        return Inertia::render('Servers/Sentinel/Alerts', [
            'server' => $server,
            'alertRules' => $alertRules,
            'alertHistory' => $alertHistory,
        ]);
    })->name('servers.sentinel.alerts');

    Route::get('/metrics', function (string $uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Sentinel/Metrics', [
            'server' => $server,
        ]);
    })->name('servers.sentinel.metrics');

    // JSON endpoint for sentinel metrics (session-auth, used by useSentinelMetrics hook)
    Route::get('/metrics/json', [\App\Http\Controllers\Api\SentinelMetricsController::class, 'metrics'])
        ->name('servers.sentinel.metrics.json');
});

// Server action routes
Route::post('/servers/{uuid}/validate', function (string $uuid) {
    $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

    Gate::authorize('update', $server);

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
