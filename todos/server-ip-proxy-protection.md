# Server IP Proxy Protection - Implementation Plan

**Status:** In Planning
**Priority:** P2 (Medium-High)
**Created:** 2026-01-22
**Updated:** 2026-01-28
**Author:** Development Team

---

## Executive Summary

Полностью автоматическая защита IP-адресов серверов. Пользователь просто деплоит — система сама всё защищает.

**Принцип:** Zero User Configuration для защиты IP.

---

## Архитектура

### Общая схема

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      INSTANCE SETTINGS (один раз)                       │
│                                                                         │
│   Админ настраивает при установке Saturn:                              │
│   - cloudflare_api_token                                               │
│   - cloudflare_account_id                                              │
│   - cloudflare_zone_id                                                 │
│   - hetzner_api_token (уже есть для auto-provisioning)                │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                              (настроено)
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    CLOUDFLARE TUNNEL (всегда активен)                   │
│                                                                         │
│   saturn-cloudflared контейнер запущен на Master Server                │
│   Ingress rules синхронизируются автоматически                         │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
┌───────────────────────────────────┼─────────────────────────────────────┐
│                                   ▼                                     │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │                     MASTER SERVER (Saturn)                       │  │
│   │                                                                  │  │
│   │   User deploys app                                               │  │
│   │         │                                                        │  │
│   │         ▼                                                        │  │
│   │   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐       │  │
│   │   │ Deploy App   │ → │ Sync Tunnel  │ → │ Create DNS   │       │  │
│   │   │ (container)  │   │ Ingress      │   │ (auto)       │       │  │
│   │   └──────────────┘   └──────────────┘   └──────────────┘       │  │
│   │                                                                  │  │
│   │   Apps: app1, app2, ... app15 (пока хватает места)              │  │
│   └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                         (disk > 80%?)                                   │
│                              │                                          │
│                              ▼                                          │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │                    AUTO-PROVISIONING                             │  │
│   │                                                                  │  │
│   │   1. Saturn detects disk > 80%                                   │  │
│   │   2. Creates Hetzner VPS automatically                           │  │
│   │   3. Configures WireGuard (Master ↔ VPS)                        │  │
│   │   4. New deploys go to VPS                                       │  │
│   │   5. Traffic: Cloudflare → Master → WireGuard → VPS             │  │
│   └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │                     HETZNER VPS (auto-created)                   │  │
│   │                                                                  │  │
│   │   WireGuard IP: 10.100.0.10                                      │  │
│   │   Real IP: hidden (no public ports 80/443)                       │  │
│   │                                                                  │  │
│   │   Apps: app16, app17, ... (overflow)                            │  │
│   └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │                   USER'S OWN SERVER (optional)                   │  │
│   │                                                                  │  │
│   │   User adds their server → auto WireGuard setup                  │  │
│   │   Works same as Hetzner VPS                                      │  │
│   └─────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

### Поток трафика

```
User Request
     │
     ▼
┌─────────────┐
│ Cloudflare  │  ← DDoS protection, SSL termination
│ Edge        │  ← Real server IP hidden
└──────┬──────┘
       │
       ▼ (Tunnel)
┌─────────────┐
│ Master      │  ← saturn-cloudflared container
│ Server      │
└──────┬──────┘
       │
       ├─────────────────┐
       │                 │
       ▼                 ▼
┌─────────────┐   ┌─────────────┐
│ Local App   │   │ WireGuard   │
│ (container) │   │ → VPS App   │
└─────────────┘   └─────────────┘
```

---

## Этап 1: Cloudflare Auto-Protection (~15 часов)

### 1.1 Database Changes

```php
// database/migrations/2026_01_28_add_cloudflare_to_instance_settings.php

Schema::table('instance_settings', function (Blueprint $table) {
    // Cloudflare credentials (global)
    $table->string('cloudflare_api_token')->nullable();
    $table->string('cloudflare_account_id')->nullable();
    $table->string('cloudflare_zone_id')->nullable();

    // Tunnel (auto-created)
    $table->string('cloudflare_tunnel_id')->nullable();
    $table->string('cloudflare_tunnel_token')->nullable();

    // Status
    $table->boolean('is_cloudflare_enabled')->default(false);
    $table->timestamp('cloudflare_last_synced_at')->nullable();
});
```

### 1.2 InstanceSettings Model Updates

```php
// app/Models/InstanceSettings.php - additions

protected $casts = [
    // ... existing

    // Cloudflare
    'cloudflare_api_token' => 'encrypted',
    'cloudflare_tunnel_token' => 'encrypted',
    'is_cloudflare_enabled' => 'boolean',
    'cloudflare_last_synced_at' => 'datetime',
];

public function hasCloudflare(): bool
{
    return !empty($this->cloudflare_api_token)
        && !empty($this->cloudflare_account_id)
        && !empty($this->cloudflare_zone_id);
}

public function isCloudflareActive(): bool
{
    return $this->is_cloudflare_enabled && !empty($this->cloudflare_tunnel_id);
}
```

### 1.3 CloudflareService (Singleton)

```php
// app/Services/CloudflareService.php

<?php

namespace App\Services;

use App\Models\InstanceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CloudflareService
{
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';
    private InstanceSettings $settings;

    public function __construct()
    {
        $this->settings = InstanceSettings::get();
    }

    public static function instance(): self
    {
        return app(self::class);
    }

    public function isConfigured(): bool
    {
        return $this->settings->hasCloudflare();
    }

    private function client()
    {
        return Http::withToken($this->settings->cloudflare_api_token)
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }

    // ==================== Tunnel Management ====================

    /**
     * Initialize tunnel (called once during Saturn setup)
     */
    public function initializeTunnel(): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Cloudflare not configured');
        }

        // Create tunnel if not exists
        if (!$this->settings->cloudflare_tunnel_id) {
            $tunnel = $this->createTunnel('saturn-main');
            $token = $this->getTunnelToken($tunnel['id']);

            $this->settings->update([
                'cloudflare_tunnel_id' => $tunnel['id'],
                'cloudflare_tunnel_token' => $token,
                'is_cloudflare_enabled' => true,
            ]);
        }

        // Deploy cloudflared container on master server
        $this->deployTunnelContainer();
    }

    /**
     * Sync all application routes to tunnel
     */
    public function syncAllRoutes(): void
    {
        if (!$this->settings->isCloudflareActive()) {
            return;
        }

        $ingress = $this->buildIngressRules();
        $this->updateTunnelConfig($this->settings->cloudflare_tunnel_id, $ingress);
        $this->syncDnsRecords($ingress);

        $this->settings->update(['cloudflare_last_synced_at' => now()]);
    }

    /**
     * Add route for single application (called on deploy)
     */
    public function addApplicationRoute(Application $app): void
    {
        if (!$this->settings->isCloudflareActive()) {
            return;
        }

        // Just sync all - Cloudflare API is idempotent
        $this->syncAllRoutes();
    }

    /**
     * Remove route for application (called on delete)
     */
    public function removeApplicationRoute(Application $app): void
    {
        if (!$this->settings->isCloudflareActive()) {
            return;
        }

        $this->syncAllRoutes();
    }

    // ==================== Private Methods ====================

    private function buildIngressRules(): array
    {
        $ingress = [];
        $masterServer = Server::where('id', 0)->first(); // Master server

        // Get all applications from all servers
        $applications = Application::whereHas('destination.server', function ($q) {
            $q->where('is_usable', true);
        })->get();

        foreach ($applications as $app) {
            $server = $app->destination->server;

            foreach ($app->fqdns as $fqdn) {
                $parsed = parse_url($fqdn);
                $hostname = $parsed['host'] ?? $fqdn;
                $port = $app->ports_exposes_array[0] ?? 80;

                // Determine service URL based on server type
                if ($server->id === $masterServer->id) {
                    // Local app on master server
                    $service = "http://localhost:{$port}";
                } else {
                    // App on remote server via WireGuard
                    $service = "http://{$server->wireguard_ip}:{$port}";
                }

                $ingress[] = [
                    'hostname' => $hostname,
                    'service' => $service,
                ];
            }
        }

        // Saturn Platform itself
        if ($this->settings->fqdn) {
            $parsed = parse_url($this->settings->fqdn);
            $ingress[] = [
                'hostname' => $parsed['host'],
                'service' => 'http://localhost:80',
            ];
        }

        // Catch-all (required)
        $ingress[] = ['service' => 'http_status:404'];

        return $ingress;
    }

    private function syncDnsRecords(array $ingress): void
    {
        $tunnelId = $this->settings->cloudflare_tunnel_id;

        foreach ($ingress as $rule) {
            if (!isset($rule['hostname'])) continue;

            $hostname = $rule['hostname'];

            // Check if DNS record exists
            $existing = $this->listDnsRecords('CNAME', $hostname);

            if (empty($existing)) {
                // Create CNAME pointing to tunnel
                $this->createDnsRecord(
                    $hostname,
                    "{$tunnelId}.cfargotunnel.com",
                    'CNAME',
                    true
                );
            }
        }
    }

    private function deployTunnelContainer(): void
    {
        $masterServer = Server::where('id', 0)->first();
        $token = $this->settings->cloudflare_tunnel_token;

        $config = [
            'services' => [
                'saturn-cloudflared' => [
                    'container_name' => 'saturn-cloudflared',
                    'image' => 'cloudflare/cloudflared:latest',
                    'restart' => 'always',
                    'network_mode' => 'host',
                    'command' => 'tunnel run',
                    'environment' => [
                        "TUNNEL_TOKEN={$token}",
                        'TUNNEL_METRICS=127.0.0.1:60123',
                    ],
                    'healthcheck' => [
                        'test' => ['CMD', 'cloudflared', 'tunnel', '--metrics', '127.0.0.1:60123', 'ready'],
                        'interval' => '10s',
                        'timeout' => '30s',
                        'retries' => 5,
                    ],
                ],
            ],
        ];

        $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 12, 2);
        $yamlBase64 = base64_encode($yaml);

        $commands = collect([
            'mkdir -p /data/saturn/cloudflared',
            "echo '{$yamlBase64}' | base64 -d > /data/saturn/cloudflared/docker-compose.yml",
            'cd /data/saturn/cloudflared && docker compose pull',
            'cd /data/saturn/cloudflared && docker compose up -d --remove-orphans',
        ]);

        instant_remote_process($commands, $masterServer);
    }

    // ==================== Cloudflare API Methods ====================

    private function createTunnel(string $name): array
    {
        $response = $this->client()->post(
            "/accounts/{$this->settings->cloudflare_account_id}/cfd_tunnel",
            [
                'name' => $name,
                'tunnel_secret' => base64_encode(random_bytes(32)),
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Failed to create tunnel: ' . $response->body());
        }

        return $response->json('result');
    }

    private function getTunnelToken(string $tunnelId): string
    {
        $response = $this->client()->get(
            "/accounts/{$this->settings->cloudflare_account_id}/cfd_tunnel/{$tunnelId}/token"
        );

        return $response->json('result');
    }

    private function updateTunnelConfig(string $tunnelId, array $ingress): void
    {
        $this->client()->put(
            "/accounts/{$this->settings->cloudflare_account_id}/cfd_tunnel/{$tunnelId}/configurations",
            ['config' => ['ingress' => $ingress]]
        );
    }

    private function listDnsRecords(string $type, string $name): array
    {
        $response = $this->client()->get(
            "/zones/{$this->settings->cloudflare_zone_id}/dns_records",
            ['type' => $type, 'name' => $name]
        );

        return $response->json('result', []);
    }

    private function createDnsRecord(string $name, string $content, string $type, bool $proxied): array
    {
        $response = $this->client()->post(
            "/zones/{$this->settings->cloudflare_zone_id}/dns_records",
            [
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'proxied' => $proxied,
                'ttl' => 1,
            ]
        );

        return $response->json('result');
    }

    public static function getCloudflareIpRanges(): array
    {
        return Cache::remember('cloudflare_ip_ranges', 86400, function () {
            $response = Http::get('https://api.cloudflare.com/client/v4/ips');
            $result = $response->json('result', []);

            return [
                'ipv4' => $result['ipv4_cidrs'] ?? [],
                'ipv6' => $result['ipv6_cidrs'] ?? [],
            ];
        });
    }
}
```

### 1.4 Auto-Sync on Deploy

```php
// app/Jobs/ApplicationDeploymentJob.php - modification

// В методе post_deployment() добавить:

private function syncCloudflareRoute(): void
{
    try {
        CloudflareService::instance()->addApplicationRoute($this->application);
    } catch (\Throwable $e) {
        // Log but don't fail deployment
        ray('Cloudflare sync failed: ' . $e->getMessage());
    }
}

// Вызвать в post_deployment():
$this->syncCloudflareRoute();
```

### 1.5 Auto-Sync on Domain Change

```php
// app/Models/Application.php - add observer

protected static function booted()
{
    static::updated(function ($application) {
        if ($application->wasChanged('fqdn')) {
            // Sync routes when domains change
            dispatch(new SyncCloudflareRoutesJob());
        }
    });

    static::deleted(function ($application) {
        dispatch(new SyncCloudflareRoutesJob());
    });
}
```

### 1.6 SyncCloudflareRoutesJob

```php
// app/Jobs/SyncCloudflareRoutesJob.php

<?php

namespace App\Jobs;

use App\Services\CloudflareService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncCloudflareRoutesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function middleware(): array
    {
        // Prevent multiple syncs running simultaneously
        return [new WithoutOverlapping('cloudflare-sync')];
    }

    public function handle(): void
    {
        CloudflareService::instance()->syncAllRoutes();
    }
}
```

### 1.7 Admin Settings UI

```typescript
// resources/js/pages/Admin/Settings/Cloudflare.tsx

import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Cloud, Shield, CheckCircle, XCircle } from 'lucide-react';

interface Props {
    settings: {
        cloudflare_api_token: string | null;
        cloudflare_account_id: string | null;
        cloudflare_zone_id: string | null;
        cloudflare_tunnel_id: string | null;
        is_cloudflare_enabled: boolean;
        cloudflare_last_synced_at: string | null;
    };
}

export default function CloudflareSettings({ settings }: Props) {
    const { data, setData, post, processing } = useForm({
        cloudflare_api_token: '',
        cloudflare_account_id: settings.cloudflare_account_id || '',
        cloudflare_zone_id: settings.cloudflare_zone_id || '',
    });

    const isConfigured = settings.cloudflare_api_token && settings.cloudflare_account_id && settings.cloudflare_zone_id;
    const isActive = settings.is_cloudflare_enabled && settings.cloudflare_tunnel_id;

    const handleSave = () => {
        post('/admin/settings/cloudflare');
    };

    const handleInitialize = () => {
        post('/admin/settings/cloudflare/initialize');
    };

    const handleSync = () => {
        post('/admin/settings/cloudflare/sync');
    };

    return (
        <div className="space-y-6">
            {/* Status Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Cloud className="h-5 w-5" />
                        Cloudflare IP Protection
                    </CardTitle>
                    <CardDescription>
                        Automatic IP hiding for all deployed applications
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            {isActive ? (
                                <Badge variant="success" className="flex items-center gap-1">
                                    <CheckCircle className="h-3 w-3" />
                                    Active
                                </Badge>
                            ) : isConfigured ? (
                                <Badge variant="warning">Configured (not initialized)</Badge>
                            ) : (
                                <Badge variant="secondary" className="flex items-center gap-1">
                                    <XCircle className="h-3 w-3" />
                                    Not Configured
                                </Badge>
                            )}
                        </div>
                        {settings.cloudflare_last_synced_at && (
                            <span className="text-sm text-muted-foreground">
                                Last sync: {new Date(settings.cloudflare_last_synced_at).toLocaleString()}
                            </span>
                        )}
                    </div>

                    {isActive && (
                        <Alert className="mt-4">
                            <Shield className="h-4 w-4" />
                            <AlertDescription>
                                All application traffic is routed through Cloudflare.
                                Server IPs are hidden from public access.
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            {/* Configuration Card */}
            <Card>
                <CardHeader>
                    <CardTitle>Cloudflare Credentials</CardTitle>
                    <CardDescription>
                        Enter your Cloudflare API credentials. These are used globally for all servers.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="api_token">API Token</Label>
                        <Input
                            id="api_token"
                            type="password"
                            placeholder={settings.cloudflare_api_token ? '••••••••' : 'Enter API token'}
                            value={data.cloudflare_api_token}
                            onChange={(e) => setData('cloudflare_api_token', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">
                            Required permissions: Zone:DNS:Edit, Account:Cloudflare Tunnel:Edit
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="account_id">Account ID</Label>
                        <Input
                            id="account_id"
                            placeholder="32-character account ID"
                            value={data.cloudflare_account_id}
                            onChange={(e) => setData('cloudflare_account_id', e.target.value)}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="zone_id">Zone ID</Label>
                        <Input
                            id="zone_id"
                            placeholder="32-character zone ID"
                            value={data.cloudflare_zone_id}
                            onChange={(e) => setData('cloudflare_zone_id', e.target.value)}
                        />
                    </div>

                    <div className="flex gap-2">
                        <Button onClick={handleSave} disabled={processing}>
                            Save Credentials
                        </Button>

                        {isConfigured && !isActive && (
                            <Button variant="outline" onClick={handleInitialize} disabled={processing}>
                                Initialize Tunnel
                            </Button>
                        )}

                        {isActive && (
                            <Button variant="outline" onClick={handleSync} disabled={processing}>
                                Force Sync Routes
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
```

### 1.8 Admin Controller

```php
// app/Http/Controllers/Inertia/AdminController.php - additions

public function cloudflareSettings()
{
    return inertia('Admin/Settings/Cloudflare', [
        'settings' => [
            'cloudflare_api_token' => InstanceSettings::get()->cloudflare_api_token ? '***' : null,
            'cloudflare_account_id' => InstanceSettings::get()->cloudflare_account_id,
            'cloudflare_zone_id' => InstanceSettings::get()->cloudflare_zone_id,
            'cloudflare_tunnel_id' => InstanceSettings::get()->cloudflare_tunnel_id,
            'is_cloudflare_enabled' => InstanceSettings::get()->is_cloudflare_enabled,
            'cloudflare_last_synced_at' => InstanceSettings::get()->cloudflare_last_synced_at,
        ],
    ]);
}

public function saveCloudflareSettings(Request $request)
{
    $validated = $request->validate([
        'cloudflare_api_token' => 'nullable|string|min:40',
        'cloudflare_account_id' => 'nullable|string|size:32',
        'cloudflare_zone_id' => 'nullable|string|size:32',
    ]);

    $settings = InstanceSettings::get();

    if ($validated['cloudflare_api_token']) {
        $settings->cloudflare_api_token = $validated['cloudflare_api_token'];
    }
    $settings->cloudflare_account_id = $validated['cloudflare_account_id'];
    $settings->cloudflare_zone_id = $validated['cloudflare_zone_id'];
    $settings->save();

    return back()->with('success', 'Credentials saved');
}

public function initializeCloudflare()
{
    try {
        CloudflareService::instance()->initializeTunnel();
        return back()->with('success', 'Cloudflare tunnel initialized');
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage());
    }
}

public function syncCloudflare()
{
    dispatch(new SyncCloudflareRoutesJob());
    return back()->with('success', 'Sync started');
}
```

---

## Этап 2: Edge Proxy для VPS (~20 часов)

> Автоматически активируется когда Saturn создаёт Hetzner VPS

### 2.1 Интеграция с Auto-Provisioning

```php
// app/Jobs/AutoProvisionServerJob.php - modification

// После создания VPS добавить:

private function configureWireGuard(Server $newServer): void
{
    $masterServer = Server::where('id', 0)->first();

    // Generate WireGuard keys for new server
    $keys = WireGuardService::generateKeyPair();
    $ip = WireGuardService::allocateIp();

    $newServer->update([
        'wireguard_private_key' => $keys['private_key'],
        'wireguard_public_key' => $keys['public_key'],
        'wireguard_ip' => $ip,
    ]);

    // Deploy WireGuard on new server
    WireGuardService::deployToServer($newServer, $masterServer);

    // Update master server's WireGuard config
    WireGuardService::addPeer($masterServer, $newServer);

    // Sync Cloudflare routes to include new server
    dispatch(new SyncCloudflareRoutesJob());
}
```

### 2.2 WireGuard Service

```php
// app/Services/WireGuardService.php

<?php

namespace App\Services;

use App\Models\Server;

class WireGuardService
{
    private static string $networkPrefix = '10.100.0';
    private static int $masterOctet = 1;
    private static int $vpsStartOctet = 10;

    public static function generateKeyPair(): array
    {
        $privateKey = trim(shell_exec('wg genkey'));
        $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey"));

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public static function allocateIp(): string
    {
        $usedOctets = Server::whereNotNull('wireguard_ip')
            ->pluck('wireguard_ip')
            ->map(fn($ip) => (int) last(explode('.', $ip)))
            ->toArray();

        $octet = self::$vpsStartOctet;
        while (in_array($octet, $usedOctets) && $octet < 255) {
            $octet++;
        }

        return self::$networkPrefix . '.' . $octet;
    }

    public static function deployToServer(Server $server, Server $masterServer): void
    {
        $config = self::generateClientConfig($server, $masterServer);
        $configBase64 = base64_encode($config);

        $commands = collect([
            'apt-get update && apt-get install -y wireguard',
            'mkdir -p /etc/wireguard',
            "echo '{$configBase64}' | base64 -d > /etc/wireguard/wg0.conf",
            'chmod 600 /etc/wireguard/wg0.conf',
            'systemctl enable wg-quick@wg0',
            'systemctl restart wg-quick@wg0',
        ]);

        instant_remote_process($commands, $server);
    }

    public static function addPeer(Server $masterServer, Server $peerServer): void
    {
        // Regenerate master config with all peers
        $config = self::generateMasterConfig($masterServer);
        $configBase64 = base64_encode($config);

        $commands = collect([
            "echo '{$configBase64}' | base64 -d > /etc/wireguard/wg0.conf",
            'wg syncconf wg0 <(wg-quick strip wg0)',
        ]);

        instant_remote_process($commands, $masterServer);
    }

    private static function generateClientConfig(Server $client, Server $master): string
    {
        return <<<CONFIG
[Interface]
PrivateKey = {$client->wireguard_private_key}
Address = {$client->wireguard_ip}/24

[Peer]
PublicKey = {$master->wireguard_public_key}
Endpoint = {$master->ip}:51820
AllowedIPs = {self::$networkPrefix}.0/24
PersistentKeepalive = 25
CONFIG;
    }

    private static function generateMasterConfig(Server $master): string
    {
        $peers = Server::whereNotNull('wireguard_ip')
            ->where('id', '!=', $master->id)
            ->get();

        $config = <<<CONFIG
[Interface]
PrivateKey = {$master->wireguard_private_key}
Address = {self::$networkPrefix}.{self::$masterOctet}/24
ListenPort = 51820
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT

CONFIG;

        foreach ($peers as $peer) {
            $config .= <<<PEER

[Peer]
# {$peer->name}
PublicKey = {$peer->wireguard_public_key}
AllowedIPs = {$peer->wireguard_ip}/32
PEER;
        }

        return $config;
    }
}
```

### 2.3 Server Model Updates

```php
// database/migrations/2026_01_28_add_wireguard_to_servers.php

Schema::table('servers', function (Blueprint $table) {
    $table->string('wireguard_private_key')->nullable();
    $table->string('wireguard_public_key')->nullable();
    $table->string('wireguard_ip')->nullable(); // 10.100.0.x
});
```

---

## Checklist

### Этап 1: Cloudflare Auto-Protection (~15 часов)
- [ ] Миграция: add_cloudflare_to_instance_settings
- [ ] InstanceSettings model updates
- [ ] CloudflareService (singleton)
- [ ] SyncCloudflareRoutesJob
- [ ] Integration with ApplicationDeploymentJob
- [ ] Application model observer (domain changes)
- [ ] Admin UI: Cloudflare settings page
- [ ] Admin routes
- [ ] Unit tests for CloudflareService
- [ ] Feature tests

### Этап 2: Edge Proxy для VPS (~20 часов)
- [ ] Миграция: add_wireguard_to_servers
- [ ] WireGuardService
- [ ] Integration with AutoProvisionServerJob
- [ ] CloudflareService updates for remote servers
- [ ] Testing with Hetzner provisioning

---

## User Flow

### Админ (один раз при установке):
```
1. Admin → Settings → Cloudflare
2. Вводит API Token, Account ID, Zone ID
3. Нажимает "Initialize Tunnel"
4. Готово! Вся защита работает автоматически
```

### Разработчик (каждый день):
```
1. Создаёт приложение в Saturn
2. Указывает домен: app.company.com
3. Нажимает Deploy
4. Система автоматически:
   - Деплоит контейнер
   - Обновляет Cloudflare Tunnel ingress
   - Создаёт DNS запись
5. Приложение доступно, IP сервера скрыт
```

### При масштабировании (автоматически):
```
1. Saturn видит: disk > 80%
2. Автоматически создаёт Hetzner VPS
3. Настраивает WireGuard между серверами
4. Следующий деплой идёт на новый VPS
5. Трафик: Cloudflare → Master → WireGuard → VPS
6. IP нового VPS тоже скрыт
```

---

**Документ создан:** 2026-01-22
**Последнее обновление:** 2026-01-28
**Версия:** 3.0
