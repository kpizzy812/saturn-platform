<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Proxy\GetProxyConfiguration;
use App\Actions\Proxy\SaveProxyConfiguration;
use App\Actions\Server\ValidateServer;
use App\Http\Controllers\Controller;
use App\Jobs\RestartProxyJob;
use App\Models\PrivateKey;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    /**
     * Display a listing of servers.
     */
    public function index(): Response
    {
        $servers = Server::ownedByCurrentTeam()
            ->with('settings')
            ->get();

        return Inertia::render('Servers/Index', [
            'servers' => $servers,
        ]);
    }

    /**
     * Show the form for creating a new server.
     */
    public function create(): Response
    {
        return Inertia::render('Servers/Create');
    }

    /**
     * Store a newly created server in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ip' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                $v = trim($value);
                if (! filter_var($v, FILTER_VALIDATE_IP) && ! preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/', $v)) {
                    $fail('The IP must be a valid IP address or hostname.');
                }
            }],
            'port' => 'required|integer|min:1|max:65535',
            'user' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'private_key_id' => 'required|exists:private_keys,id',
        ]);

        $privateKey = PrivateKey::ownedByCurrentTeam()
            ->where('id', $validated['private_key_id'])
            ->firstOrFail();

        $server = Server::createWithPrivateKey([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'ip' => $validated['ip'],
            'port' => $validated['port'],
            'user' => $validated['user'],
            'team_id' => currentTeam()->id,
        ], $privateKey);

        return redirect()->route('servers.show', $server->uuid)
            ->with('success', 'Server created successfully. Please validate the connection.');
    }

    /**
     * Display the specified server.
     */
    public function show(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()
            ->with('settings')
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Servers/Show', [
            'server' => $server,
        ]);
    }

    /**
     * Validate server connection.
     */
    public function validateServer(string $uuid): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        try {
            ValidateServer::run($server);

            return redirect()->back()->with('success', 'Server validated successfully and is ready to use.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Server validation failed: '.$e->getMessage());
        }
    }

    /**
     * Display server settings.
     */
    public function settings(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Settings/Index', ['server' => $server]);
    }

    /**
     * Display server Docker settings.
     */
    public function dockerSettings(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Settings/Docker', ['server' => $server]);
    }

    /**
     * Display server network settings.
     */
    public function networkSettings(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Settings/Network', ['server' => $server]);
    }

    /**
     * Display server destinations.
     */
    public function destinations(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $destinations = $server->destinations()->map(function ($destination) {
            return [
                'id' => $destination->id,
                'uuid' => $destination->uuid ?? null,
                'name' => $destination->name,
                'network' => $destination->network,
                'type' => class_basename($destination),
                'applications_count' => $destination->applications?->count() ?? 0,
                'databases_count' => $destination->databases()?->count() ?? 0,
                'services_count' => $destination->services?->count() ?? 0,
            ];
        });

        return Inertia::render('Servers/Destinations/Index', [
            'server' => $server,
            'destinations' => $destinations,
        ]);
    }

    /**
     * Show the form for creating a new destination.
     */
    public function createDestination(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Destinations/Create', ['server' => $server]);
    }

    /**
     * Display server resources.
     */
    public function resources(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $applications = $server->applications()->map(function ($app) {
            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'status' => $app->status,
                'fqdn' => $app->fqdn,
                'created_at' => $app->created_at,
            ];
        });

        $databases = $server->databases()->map(function ($db) {
            return [
                'id' => $db->id,
                'uuid' => $db->uuid,
                'name' => $db->name,
                'status' => $db->status,
                'type' => class_basename($db),
                'created_at' => $db->created_at,
            ];
        });

        $services = $server->services->map(function ($service) {
            return [
                'id' => $service->id,
                'uuid' => $service->uuid,
                'name' => $service->name,
                'created_at' => $service->created_at,
            ];
        });

        return Inertia::render('Servers/Resources/Index', [
            'server' => $server,
            'applications' => $applications,
            'databases' => $databases,
            'services' => $services,
        ]);
    }

    /**
     * Display server log drains.
     */
    public function logDrains(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $logDrains = [];

        if ($server->settings->is_logdrain_newrelic_enabled) {
            $logDrains[] = [
                'type' => 'newrelic',
                'enabled' => true,
                'base_uri' => $server->settings->logdrain_newrelic_base_uri,
            ];
        }

        if ($server->settings->is_logdrain_highlight_enabled) {
            $logDrains[] = [
                'type' => 'highlight',
                'enabled' => true,
                'project_id' => $server->settings->logdrain_highlight_project_id,
            ];
        }

        if ($server->settings->is_logdrain_axiom_enabled) {
            $logDrains[] = [
                'type' => 'axiom',
                'enabled' => true,
                'dataset_name' => $server->settings->logdrain_axiom_dataset_name,
            ];
        }

        if ($server->settings->is_logdrain_custom_enabled) {
            $logDrains[] = [
                'type' => 'custom',
                'enabled' => true,
                'config' => $server->settings->logdrain_custom_config,
                'parser' => $server->settings->logdrain_custom_config_parser,
            ];
        }

        return Inertia::render('Servers/LogDrains/Index', [
            'server' => $server,
            'logDrains' => $logDrains,
        ]);
    }

    /**
     * Display server private keys.
     */
    public function privateKeys(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $privateKeys = PrivateKey::ownedByCurrentTeam()
            ->select('id', 'uuid', 'name', 'description', 'fingerprint', 'created_at')
            ->get()
            ->map(function ($key) use ($server) {
                return [
                    'id' => $key->id,
                    'uuid' => $key->uuid,
                    'name' => $key->name,
                    'description' => $key->description,
                    'fingerprint' => $key->fingerprint,
                    'is_current' => $server->private_key_id === $key->id,
                    'created_at' => $key->created_at,
                ];
            });

        return Inertia::render('Servers/PrivateKeys/Index', [
            'server' => $server,
            'privateKeys' => $privateKeys,
        ]);
    }

    /**
     * Display server cleanup interface.
     */
    public function cleanup(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $latestExecution = $server->dockerCleanupExecutions()
            ->latest()
            ->first();

        $cleanupStats = null;
        if ($latestExecution) {
            $cleanupStats = [
                'last_run_at' => $latestExecution->created_at,
                'containers_removed' => $latestExecution->containers_removed ?? 0,
                'images_removed' => $latestExecution->images_removed ?? 0,
                'volumes_removed' => $latestExecution->volumes_removed ?? 0,
                'networks_removed' => $latestExecution->networks_removed ?? 0,
                'space_reclaimed_bytes' => $latestExecution->space_reclaimed ?? 0,
            ];
        }

        return Inertia::render('Servers/Cleanup/Index', [
            'server' => $server,
            'cleanupStats' => $cleanupStats,
            'cleanupSettings' => [
                'frequency' => $server->settings->docker_cleanup_frequency ?? 'daily',
                'threshold' => $server->settings->docker_cleanup_threshold ?? 80,
                'delete_unused_volumes' => $server->settings->delete_unused_volumes ?? false,
                'delete_unused_networks' => $server->settings->delete_unused_networks ?? false,
            ],
        ]);
    }

    /**
     * Display server metrics.
     */
    public function metrics(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Metrics/Index', ['server' => $server]);
    }

    /**
     * Display server terminal.
     */
    public function terminal(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Terminal/Index', [
            'server' => $server,
        ]);
    }

    /**
     * Display proxy overview.
     */
    public function proxyIndex(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $proxyStatus = $server->proxy->status ?? 'exited';
        $proxyVersion = null;
        $proxyUptime = null;

        // Get proxy version and uptime from server if it's functional
        if ($server->isFunctional()) {
            try {
                $containerName = $server->isSwarm() ? 'saturn-proxy_traefik' : 'saturn-proxy';

                // Get container status and uptime
                $inspectOutput = instant_remote_process([
                    "docker inspect $containerName --format '{{.State.Status}}|{{.State.StartedAt}}' 2>/dev/null || echo 'not_found'",
                ], $server, false);

                if ($inspectOutput && $inspectOutput !== 'not_found') {
                    [$status, $startedAt] = explode('|', trim($inspectOutput));
                    if ($status === 'running' && $startedAt) {
                        $proxyUptime = \Carbon\Carbon::parse($startedAt)->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE);
                    }
                }

                // Get Traefik version if using Traefik
                if ($server->proxyType() === 'TRAEFIK') {
                    $proxyVersion = $server->detected_traefik_version ?? null;
                }
            } catch (\Throwable $e) {
                // Ignore errors, will use default values
            }
        }

        // Count applications with domains
        $applicationsWithDomains = $server->applications()->filter(function ($app) {
            return ! empty($app->fqdn);
        })->count();

        // Count SSL certificates
        $sslCount = $server->sslCertificates()->count();

        return Inertia::render('Servers/Proxy/Index', [
            'server' => $server,
            'proxy' => [
                'type' => $server->proxyType() ?? 'traefik',
                'status' => $proxyStatus,
                'version' => $proxyVersion,
                'uptime' => $proxyUptime,
                'domains_count' => $applicationsWithDomains,
                'ssl_count' => $sslCount,
            ],
        ]);
    }

    /**
     * Display proxy configuration.
     */
    public function proxyConfiguration(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $configuration = GetProxyConfiguration::run($server);
        $proxyPath = $server->proxyPath();
        $filePath = "$proxyPath/docker-compose.yml";

        return Inertia::render('Servers/Proxy/Configuration', [
            'server' => $server,
            'configuration' => $configuration ?? '# Proxy configuration not available',
            'filePath' => $filePath,
        ]);
    }

    /**
     * Update proxy configuration.
     */
    public function updateProxyConfiguration(string $uuid, Request $request): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'configuration' => 'required|string',
        ]);

        try {
            SaveProxyConfiguration::run($server, $validated['configuration']);

            return redirect()->back()->with('success', 'Proxy configuration saved successfully. Restart the proxy to apply changes.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to save proxy configuration: '.$e->getMessage());
        }
    }

    /**
     * Reset proxy configuration to default.
     */
    public function resetProxyConfiguration(string $uuid): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        try {
            $defaultConfiguration = GetProxyConfiguration::run($server);
            SaveProxyConfiguration::run($server, $defaultConfiguration);

            return redirect()->back()->with('success', 'Proxy configuration reset to default. Restart the proxy to apply changes.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to reset proxy configuration: '.$e->getMessage());
        }
    }

    /**
     * Display proxy logs.
     */
    public function proxyLogs(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $logs = '';
        if ($server->isFunctional()) {
            try {
                $containerName = $server->isSwarm() ? 'saturn-proxy_traefik' : 'saturn-proxy';
                $logs = instant_remote_process([
                    "docker logs --tail 100 $containerName 2>&1",
                ], $server, false) ?? 'No logs available';
            } catch (\Throwable $e) {
                $logs = 'Failed to fetch logs: '.$e->getMessage();
            }
        } else {
            $logs = 'Server is not reachable. Cannot fetch logs.';
        }

        return Inertia::render('Servers/Proxy/Logs', [
            'server' => $server,
            'logs' => $logs,
        ]);
    }

    /**
     * Display proxy domains.
     */
    public function proxyDomains(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Get all applications with domains from this server
        $domains = $server->applications()->filter(function ($app) {
            return ! empty($app->fqdn);
        })->map(function ($app) {
            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'domain' => $app->fqdn,
                'status' => $app->status,
            ];
        })->values();

        return Inertia::render('Servers/Proxy/Domains', [
            'server' => $server,
            'domains' => $domains,
        ]);
    }

    /**
     * Store a new proxy domain.
     */
    public function storeProxyDomain(string $uuid, Request $request): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Note: This is typically handled by application creation, not directly
        // This is a placeholder for future custom domain management
        return redirect()->back()->with('info', 'Domains are managed through applications. Create an application to add a domain.');
    }

    /**
     * Update a proxy domain.
     */
    public function updateProxyDomain(string $uuid, int $domainId, Request $request): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Note: This is typically handled by application updates, not directly
        return redirect()->back()->with('info', 'Domain updates are managed through the application settings.');
    }

    /**
     * Remove a proxy domain.
     */
    public function destroyProxyDomain(string $uuid, int $domainId): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Note: This is typically handled by application deletion or domain removal
        return redirect()->back()->with('info', 'Domains are removed by updating the application settings.');
    }

    /**
     * Renew SSL certificate for a domain.
     */
    public function renewDomainCertificate(string $uuid, int $domainId): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Find the SSL certificate
        $certificate = $server->sslCertificates()->where('id', $domainId)->first();

        if (! $certificate) {
            return redirect()->back()->with('error', 'SSL certificate not found.');
        }

        try {
            // Dispatch renewal job
            \App\Jobs\RegenerateSslCertJob::dispatch(
                server_id: $server->id,
                force_regeneration: true
            );

            return redirect()->back()->with('success', 'SSL certificate renewal initiated. This may take a few moments.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to initiate certificate renewal: '.$e->getMessage());
        }
    }

    /**
     * Display proxy settings.
     */
    public function proxySettings(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

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
    }

    /**
     * Update proxy settings.
     */
    public function updateProxySettings(string $uuid, Request $request): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'redirect_url' => 'nullable|string|url',
            'redirect_enabled' => 'boolean',
        ]);

        try {
            if (isset($validated['redirect_url'])) {
                $server->proxy->redirect_url = $validated['redirect_url'];
            }
            if (isset($validated['redirect_enabled'])) {
                $server->proxy->redirect_enabled = $validated['redirect_enabled'];
            }

            $server->save();
            $server->setupDefaultRedirect();

            return redirect()->back()->with('success', 'Proxy settings saved successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to save proxy settings: '.$e->getMessage());
        }
    }

    /**
     * Restart proxy.
     */
    public function restartProxy(string $uuid): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        try {
            RestartProxyJob::dispatch($server);

            return redirect()->back()->with('success', 'Proxy restart initiated. This may take a few moments.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to restart proxy: '.$e->getMessage());
        }
    }

    /**
     * Start proxy.
     */
    public function startProxy(string $uuid): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        try {
            \App\Actions\Proxy\StartProxy::dispatch($server);

            return redirect()->back()->with('success', 'Proxy start initiated. This may take a few moments.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to start proxy: '.$e->getMessage());
        }
    }

    /**
     * Stop proxy.
     */
    public function stopProxy(string $uuid): RedirectResponse
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        try {
            \App\Actions\Proxy\StopProxy::dispatch($server);

            return redirect()->back()->with('success', 'Proxy stop initiated. This may take a few moments.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to stop proxy: '.$e->getMessage());
        }
    }

    /**
     * Display Sentinel monitoring overview.
     */
    public function sentinelIndex(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Sentinel/Index', [
            'server' => $server,
        ]);
    }

    /**
     * Display Sentinel alerts.
     */
    public function sentinelAlerts(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        // Sentinel alerts are typically configured through notification settings
        // This displays the current server monitoring status
        $alertRules = [
            [
                'name' => 'Server Unreachable',
                'enabled' => true,
                'type' => 'connectivity',
                'description' => 'Alert when server becomes unreachable',
            ],
            [
                'name' => 'High Disk Usage',
                'enabled' => true,
                'type' => 'disk',
                'threshold' => $server->settings->docker_cleanup_threshold ?? 80,
                'description' => 'Alert when disk usage exceeds threshold',
            ],
        ];

        // Get recent notification history from activity log if available
        $alertHistory = [];
        if ($server->unreachable_notification_sent) {
            $alertHistory[] = [
                'type' => 'Server Unreachable',
                'message' => 'Server is currently unreachable',
                'severity' => 'error',
                'triggered_at' => $server->updated_at,
            ];
        }

        return Inertia::render('Servers/Sentinel/Alerts', [
            'server' => $server,
            'alertRules' => $alertRules,
            'alertHistory' => $alertHistory,
            'sentinelStatus' => [
                'enabled' => $server->isSentinelEnabled(),
                'live' => $server->isSentinelLive(),
                'last_heartbeat' => $server->sentinel_updated_at,
            ],
        ]);
    }

    /**
     * Display Sentinel metrics.
     */
    public function sentinelMetrics(string $uuid): Response
    {
        $server = Server::ownedByCurrentTeam()->where('uuid', $uuid)->firstOrFail();

        return Inertia::render('Servers/Sentinel/Metrics', [
            'server' => $server,
        ]);
    }
}
