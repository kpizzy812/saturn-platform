<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationPreview;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\ServiceApplication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

class CloudflareProtectionService
{
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';

    private function settings(): InstanceSettings
    {
        return InstanceSettings::get();
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->settings()->cloudflare_api_token)
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    /**
     * Execute an API call with token-safe error handling.
     * Prevents API token from leaking in exception stack traces.
     */
    private function safeApiCall(callable $callback): mixed
    {
        try {
            return $callback($this->client());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $response = $e->response;
            $status = $response?->status() ?? 'unknown';
            $cfError = $response?->json('errors.0.message') ?? $e->getMessage();

            throw new \RuntimeException("Cloudflare API error (HTTP {$status}): {$cfError}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException('Cloudflare API connection failed: '.$e->getMessage());
        }
    }

    public function isConfigured(): bool
    {
        return $this->settings()->hasCloudflareProtection();
    }

    public function isActive(): bool
    {
        return $this->settings()->isCloudflareProtectionActive();
    }

    /**
     * Create a Cloudflare Tunnel and deploy cloudflared container on the master server.
     * Includes rollback on partial failure to avoid "Tunnel already exists" deadlock.
     */
    public function initializeTunnel(): void
    {
        $settings = $this->settings();

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Cloudflare credentials are not configured.');
        }

        if (! empty($settings->cloudflare_tunnel_id)) {
            throw new \RuntimeException('Tunnel already exists. Destroy it first.');
        }

        $tunnelName = 'saturn-'.($settings->instance_name ?: 'platform');
        $tunnelId = null;

        try {
            // Create tunnel via Cloudflare API
            $response = $this->safeApiCall(fn ($client) => $client->post(
                "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel",
                [
                    'name' => $tunnelName,
                    'tunnel_secret' => base64_encode(random_bytes(32)),
                    'config_src' => 'cloudflare',
                ]
            ));

            $tunnelId = $response->json('result.id');
            if (! $tunnelId) {
                throw new \RuntimeException('Failed to create tunnel: no tunnel ID returned.');
            }

            // Get tunnel token
            $tokenResponse = $this->safeApiCall(fn ($client) => $client->get(
                "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel/{$tunnelId}/token"
            ));

            $tunnelToken = $tokenResponse->json('result');
            if (! $tunnelToken) {
                throw new \RuntimeException('Failed to get tunnel token.');
            }

            // Save tunnel info
            $settings->update([
                'cloudflare_tunnel_id' => $tunnelId,
                'cloudflare_tunnel_token' => $tunnelToken,
                'is_cloudflare_protection_enabled' => true,
            ]);

            // Deploy cloudflared container on master server
            $this->deployCloudflaredContainer($tunnelToken);

            // Sync all routes
            $this->syncAllRoutes();
        } catch (\Throwable $e) {
            // Rollback: if tunnel was created in Cloudflare but subsequent steps failed
            if ($tunnelId) {
                Log::error('initializeTunnel partial failure, rolling back tunnel '.$tunnelId, [
                    'error' => $e->getMessage(),
                ]);

                try {
                    $this->client()->delete(
                        "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel/{$tunnelId}/connections"
                    );
                    $this->client()->delete(
                        "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel/{$tunnelId}"
                    );
                } catch (\Throwable $rollbackError) {
                    Log::error('Failed to rollback tunnel: '.$rollbackError->getMessage());
                }

                // Clear any partially saved state
                $settings->update([
                    'cloudflare_tunnel_id' => null,
                    'cloudflare_tunnel_token' => null,
                    'is_cloudflare_protection_enabled' => false,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Deploy cloudflared Docker container on the master server.
     */
    private function deployCloudflaredContainer(string $tunnelToken): void
    {
        $masterServer = Server::masterServer();
        if (! $masterServer) {
            throw new \RuntimeException('Master server not found.');
        }

        $config = [
            'services' => [
                'saturn-cloudflared' => [
                    'container_name' => 'saturn-cloudflared',
                    'image' => 'cloudflare/cloudflared:latest',
                    'restart' => RESTART_MODE,
                    'network_mode' => 'host',
                    'command' => 'tunnel run',
                    'environment' => [
                        "TUNNEL_TOKEN={$tunnelToken}",
                        'TUNNEL_METRICS=127.0.0.1:60123',
                    ],
                    'healthcheck' => [
                        'test' => ['CMD', 'cloudflared', 'tunnel', '--metrics', '127.0.0.1:60123', 'ready'],
                        'interval' => '5s',
                        'timeout' => '30s',
                        'retries' => 5,
                    ],
                ],
            ],
        ];

        $yamlConfig = Yaml::dump($config, 12, 2);
        $dockerComposeBase64 = base64_encode($yamlConfig);

        instant_remote_process([
            'mkdir -p /tmp/cloudflared',
            'cd /tmp/cloudflared',
            "echo '{$dockerComposeBase64}' | base64 -d | tee docker-compose.yml > /dev/null",
            'docker compose pull',
            'docker rm -f saturn-cloudflared || true',
            'docker compose up -d --wait --wait-timeout 15 --remove-orphans',
        ], $masterServer);
    }

    /**
     * Collect all FQDNs and sync tunnel ingress rules + DNS records.
     */
    public function syncAllRoutes(): void
    {
        $settings = $this->settings();

        if (! $this->isActive()) {
            return;
        }

        $ingressRules = $this->buildIngressRules();

        // Update tunnel configuration (ingress rules)
        $this->safeApiCall(fn ($client) => $client->put(
            "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel/{$settings->cloudflare_tunnel_id}/configurations",
            [
                'config' => [
                    'ingress' => $ingressRules,
                ],
            ]
        ));

        // Sync DNS records (CNAME to tunnel)
        $this->syncDnsRecords($ingressRules);

        $settings->update(['cloudflare_last_synced_at' => now()]);

        Log::info('Cloudflare routes synced', ['rules_count' => count($ingressRules)]);
    }

    /**
     * Build ingress rules from all Applications, ServiceApplications, and Previews with FQDNs.
     * Logs invalid FQDNs, filters wildcards, and warns about duplicate hostnames.
     */
    public function buildIngressRules(): array
    {
        $rules = [];
        $seenHostnames = [];

        // Platform FQDN
        $settings = $this->settings();
        if (! empty($settings->fqdn)) {
            $platformHost = parse_url($settings->fqdn, PHP_URL_HOST);
            if ($platformHost) {
                $rules[] = [
                    'hostname' => $platformHost,
                    'service' => 'http://localhost:80',
                ];
                $seenHostnames[$platformHost] = 'platform';
            }
        }

        // All Applications with FQDNs
        $applications = Application::whereNotNull('fqdn')
            ->where('fqdn', '!=', '')
            ->get();

        foreach ($applications as $app) {
            $fqdns = collect(explode(',', $app->fqdn))
                ->map(fn ($f) => trim($f))
                ->filter();

            foreach ($fqdns as $fqdn) {
                $host = parse_url($fqdn, PHP_URL_HOST);
                if (! $host) {
                    Log::warning('Cloudflare: invalid FQDN skipped', [
                        'fqdn' => $fqdn,
                        'source' => 'application',
                        'id' => $app->id,
                    ]);

                    continue;
                }

                // Skip wildcard hostnames (cannot create CNAME for wildcards without special handling)
                if (str_starts_with($host, '*.')) {
                    Log::info('Cloudflare: wildcard hostname skipped for DNS', ['hostname' => $host]);

                    continue;
                }

                if (isset($seenHostnames[$host])) {
                    Log::warning('Cloudflare: duplicate hostname detected (first-match-wins)', [
                        'hostname' => $host,
                        'first_owner' => $seenHostnames[$host],
                        'duplicate_owner' => "application:{$app->id}",
                    ]);
                } else {
                    $seenHostnames[$host] = "application:{$app->id}";
                }

                $scheme = parse_url($fqdn, PHP_URL_SCHEME) ?: 'http';
                $port = $this->resolveAppPort($app);

                $rules[] = [
                    'hostname' => $host,
                    'service' => "{$scheme}://localhost:{$port}",
                ];
            }
        }

        // All ServiceApplications with FQDNs
        $serviceApps = ServiceApplication::whereNotNull('fqdn')
            ->where('fqdn', '!=', '')
            ->get();

        foreach ($serviceApps as $serviceApp) {
            $fqdns = collect(explode(',', $serviceApp->fqdn))
                ->map(fn ($f) => trim($f))
                ->filter();

            foreach ($fqdns as $fqdn) {
                $host = parse_url($fqdn, PHP_URL_HOST);
                if (! $host) {
                    Log::warning('Cloudflare: invalid FQDN skipped', [
                        'fqdn' => $fqdn,
                        'source' => 'service_application',
                        'id' => $serviceApp->id,
                    ]);

                    continue;
                }

                if (str_starts_with($host, '*.')) {
                    Log::info('Cloudflare: wildcard hostname skipped for DNS', ['hostname' => $host]);

                    continue;
                }

                if (isset($seenHostnames[$host])) {
                    Log::warning('Cloudflare: duplicate hostname detected (first-match-wins)', [
                        'hostname' => $host,
                        'first_owner' => $seenHostnames[$host],
                        'duplicate_owner' => "service_application:{$serviceApp->id}",
                    ]);
                } else {
                    $seenHostnames[$host] = "service_application:{$serviceApp->id}";
                }

                $rules[] = [
                    'hostname' => $host,
                    'service' => 'http://localhost:80',
                ];
            }
        }

        // All ApplicationPreviews with FQDNs (PR preview deployments)
        $previews = ApplicationPreview::whereNotNull('fqdn')
            ->where('fqdn', '!=', '')
            ->with('application')
            ->get();

        foreach ($previews as $preview) {
            $fqdns = collect(explode(',', $preview->fqdn))
                ->map(fn ($f) => trim($f))
                ->filter();

            foreach ($fqdns as $fqdn) {
                $host = parse_url($fqdn, PHP_URL_HOST);
                if (! $host) {
                    Log::warning('Cloudflare: invalid FQDN skipped', [
                        'fqdn' => $fqdn,
                        'source' => 'application_preview',
                        'id' => $preview->id,
                    ]);

                    continue;
                }

                if (str_starts_with($host, '*.')) {
                    continue;
                }

                if (isset($seenHostnames[$host])) {
                    Log::warning('Cloudflare: duplicate hostname detected (first-match-wins)', [
                        'hostname' => $host,
                        'first_owner' => $seenHostnames[$host],
                        'duplicate_owner' => "preview:{$preview->id}",
                    ]);
                } else {
                    $seenHostnames[$host] = "preview:{$preview->id}";
                }

                $port = $this->resolveAppPort($preview->application);

                $rules[] = [
                    'hostname' => $host,
                    'service' => 'http://localhost:'.$port,
                ];
            }
        }

        // Catch-all rule (required by Cloudflare Tunnel)
        $rules[] = [
            'service' => 'http_status:404',
        ];

        return $rules;
    }

    /**
     * Sync DNS CNAME records pointing to the tunnel.
     */
    private function syncDnsRecords(array $ingressRules): void
    {
        $settings = $this->settings();
        $tunnelCname = "{$settings->cloudflare_tunnel_id}.cfargotunnel.com";

        // Get all existing CNAME DNS records (paginated — Cloudflare max 100 per page)
        $allRecords = collect();
        $page = 1;

        do {
            $response = $this->safeApiCall(fn ($client) => $client->get(
                "/zones/{$settings->cloudflare_zone_id}/dns_records",
                [
                    'type' => 'CNAME',
                    'per_page' => 100,
                    'page' => $page,
                ]
            ));

            $records = $response->json('result', []);
            $allRecords = $allRecords->merge($records);

            $totalPages = $response->json('result_info.total_pages', 1);
            $page++;
        } while ($page <= $totalPages);

        $existingByName = $allRecords->keyBy('name');

        // Collect all hostnames from ingress (excluding catch-all and wildcards)
        $hostnames = collect($ingressRules)
            ->filter(fn ($rule) => isset($rule['hostname']) && ! str_starts_with($rule['hostname'], '*.'))
            ->pluck('hostname')
            ->unique();

        foreach ($hostnames as $hostname) {
            $existing = $existingByName->get($hostname);

            if ($existing) {
                // Update if content differs
                if ($existing['content'] !== $tunnelCname) {
                    try {
                        $this->safeApiCall(fn ($client) => $client->put(
                            "/zones/{$settings->cloudflare_zone_id}/dns_records/{$existing['id']}",
                            [
                                'type' => 'CNAME',
                                'name' => $hostname,
                                'content' => $tunnelCname,
                                'proxied' => true,
                            ]
                        ));
                    } catch (\RuntimeException $e) {
                        Log::warning("Failed to update DNS record for {$hostname}: ".$e->getMessage());
                    }
                }
            } else {
                // Create new record
                try {
                    $this->safeApiCall(fn ($client) => $client->post(
                        "/zones/{$settings->cloudflare_zone_id}/dns_records",
                        [
                            'type' => 'CNAME',
                            'name' => $hostname,
                            'content' => $tunnelCname,
                            'proxied' => true,
                        ]
                    ));
                } catch (\RuntimeException $e) {
                    // DNS record may already exist with A record, log and continue
                    Log::warning("Failed to create DNS record for {$hostname}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Remove the tunnel and cloudflared container.
     */
    public function destroyTunnel(): void
    {
        $settings = $this->settings();

        // Remove cloudflared container from master server
        $masterServer = Server::masterServer();
        if ($masterServer) {
            try {
                instant_remote_process([
                    'docker rm -f saturn-cloudflared || true',
                    'rm -rf /tmp/cloudflared',
                ], $masterServer, throwError: false);
            } catch (\Exception $e) {
                Log::warning('Failed to remove cloudflared container: '.$e->getMessage());
            }
        }

        // Delete tunnel via Cloudflare API
        if (! empty($settings->cloudflare_tunnel_id) && ! empty($settings->cloudflare_api_token)) {
            try {
                // Clean up tunnel connections first
                $this->safeApiCall(fn ($client) => $client->delete(
                    "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel/{$settings->cloudflare_tunnel_id}/connections"
                ));

                $this->safeApiCall(fn ($client) => $client->delete(
                    "/accounts/{$settings->cloudflare_account_id}/cfd_tunnel/{$settings->cloudflare_tunnel_id}"
                ));
            } catch (\Exception $e) {
                Log::warning('Failed to delete Cloudflare tunnel via API: '.$e->getMessage());
            }
        }

        $settings->update([
            'cloudflare_tunnel_id' => null,
            'cloudflare_tunnel_token' => null,
            'is_cloudflare_protection_enabled' => false,
            'cloudflare_last_synced_at' => null,
        ]);
    }

    /**
     * Resolve the exposed port for an application.
     */
    private function resolveAppPort(Application $app): int
    {
        if (! empty($app->ports_exposes)) {
            $ports = explode(',', $app->ports_exposes);

            return (int) trim($ports[0]);
        }

        return 80;
    }
}
