# Edge Proxy Layer - Детальный План Реализации

**Status:** Planning
**Priority:** P2 (Medium-High)
**Created:** 2026-01-28
**Estimated:** 40-50 часов разработки
**Author:** Development Team

---

## Executive Summary

Реализация собственной прокси-инфраструктуры для скрытия реальных IP-адресов backend серверов. Все публичные домены будут указывать на Edge серверы, которые проксируют трафик на backend через зашифрованный WireGuard туннель.

---

## 1. Архитектура

### 1.1 Общая схема

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ПУБЛИЧНЫЙ ИНТЕРНЕТ                              │
│                                                                         │
│    app1.company.com ──┐                                                 │
│    app2.company.com ──┼──→ DNS A record → Edge Server IP(s)            │
│    api.company.com  ──┘                                                 │
└───────────────────────────────────────────────────────────────────────┬─┘
                                                                        ↓
┌───────────────────────────────────────────────────────────────────────────┐
│                          EDGE PROXY LAYER                                 │
│                                                                           │
│  ┌─────────────────────┐         ┌─────────────────────┐                 │
│  │   Edge Server #1    │         │   Edge Server #2    │   (HA optional) │
│  │   185.x.x.1         │         │   185.x.x.2         │                 │
│  │                     │         │                     │                 │
│  │  ┌───────────────┐  │         │  ┌───────────────┐  │                 │
│  │  │    Traefik    │  │         │  │    Traefik    │  │                 │
│  │  │  :80, :443    │  │         │  │  :80, :443    │  │                 │
│  │  └───────┬───────┘  │         │  └───────┬───────┘  │                 │
│  │          │          │         │          │          │                 │
│  │  ┌───────┴───────┐  │         │  ┌───────┴───────┐  │                 │
│  │  │   WireGuard   │  │         │  │   WireGuard   │  │                 │
│  │  │  wg0 interface│  │         │  │  wg0 interface│  │                 │
│  │  │  10.100.0.1   │  │         │  │  10.100.0.1   │  │                 │
│  │  └───────────────┘  │         │  └───────────────┘  │                 │
│  └──────────┬──────────┘         └──────────┬──────────┘                 │
│             │                               │                             │
│             └───────────────┬───────────────┘                             │
│                             ↓                                             │
│                    WireGuard VPN Mesh                                     │
│                    (encrypted tunnel)                                     │
└─────────────────────────────┬─────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                      BACKEND SERVERS (Hidden IPs)                           │
│                                                                             │
│  ┌───────────────────┐   ┌───────────────────┐   ┌───────────────────┐    │
│  │  Backend #1       │   │  Backend #2       │   │  Backend #3       │    │
│  │  Real: 94.x.x.10  │   │  Real: 94.x.x.11  │   │  Real: 94.x.x.12  │    │
│  │  WG: 10.100.0.10  │   │  WG: 10.100.0.11  │   │  WG: 10.100.0.12  │    │
│  │                   │   │                   │   │                   │    │
│  │  ┌─────────────┐  │   │  ┌─────────────┐  │   │  ┌─────────────┐  │    │
│  │  │  WireGuard  │  │   │  │  WireGuard  │  │   │  │  WireGuard  │  │    │
│  │  │  wg0        │  │   │  │  wg0        │  │   │  │  wg0        │  │    │
│  │  └─────────────┘  │   │  └─────────────┘  │   │  └─────────────┘  │    │
│  │                   │   │                   │   │                   │    │
│  │  ┌─────────────┐  │   │  ┌─────────────┐  │   │  ┌─────────────┐  │    │
│  │  │ App: myapp  │  │   │  │ App: api    │  │   │  │ App: admin  │  │    │
│  │  │ Port: 3000  │  │   │  │ Port: 8080  │  │   │  │ Port: 4000  │  │    │
│  │  └─────────────┘  │   │  └─────────────┘  │   │  └─────────────┘  │    │
│  └───────────────────┘   └───────────────────┘   └───────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Поток трафика

```
1. User → https://app.company.com
2. DNS → 185.x.x.1 (Edge Server IP)
3. Edge Traefik (SSL termination, Let's Encrypt)
4. Traefik route: app.company.com → http://10.100.0.10:3000
5. WireGuard tunnel → Backend Server (10.100.0.10)
6. Backend App Container → Response
7. Response ← WireGuard ← Traefik ← User
```

### 1.3 Ключевые принципы

1. **Backend серверы НЕ имеют публичных портов 80/443** - только WireGuard
2. **Edge серверы НЕ запускают приложения** - только проксирование
3. **Все роуты синхронизируются автоматически** при деплое
4. **SSL сертификаты** управляются на Edge (Let's Encrypt)
5. **WireGuard** обеспечивает шифрование внутреннего трафика

---

## 2. Database Schema

### 2.1 Миграция: add_edge_proxy_fields_to_servers

```php
// database/migrations/2026_01_28_000001_add_edge_proxy_fields_to_servers.php

Schema::table('servers', function (Blueprint $table) {
    // Edge server fields
    $table->boolean('is_edge_server')->default(false)->after('ip');
    $table->foreignId('edge_server_id')->nullable()->after('is_edge_server')
        ->constrained('servers')->nullOnDelete();

    // WireGuard fields
    $table->string('wireguard_private_key')->nullable()->after('edge_server_id');
    $table->string('wireguard_public_key')->nullable()->after('wireguard_private_key');
    $table->string('wireguard_ip')->nullable()->after('wireguard_public_key'); // 10.100.0.x
    $table->string('wireguard_endpoint')->nullable()->after('wireguard_ip'); // public_ip:51820

    // Previous IP storage (for rollback)
    $table->string('ip_previous')->nullable()->after('ip');

    // Indexes
    $table->index('is_edge_server');
    $table->index('edge_server_id');
    $table->index('wireguard_ip');
});
```

### 2.2 Миграция: create_edge_routes_table

```php
// database/migrations/2026_01_28_000002_create_edge_routes_table.php

Schema::create('edge_routes', function (Blueprint $table) {
    $table->id();
    $table->string('uuid')->unique();

    // Relations
    $table->foreignId('edge_server_id')->constrained('servers')->cascadeOnDelete();
    $table->foreignId('backend_server_id')->constrained('servers')->cascadeOnDelete();
    $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();

    // Route configuration
    $table->string('domain'); // app.company.com
    $table->string('path')->default('/'); // /api, /admin, etc.
    $table->string('backend_ip'); // WireGuard IP: 10.100.0.10
    $table->integer('backend_port'); // 3000
    $table->string('protocol')->default('http'); // http, https, grpc

    // SSL
    $table->boolean('ssl_enabled')->default(true);
    $table->string('ssl_cert_resolver')->default('letsencrypt');

    // Status
    $table->string('status')->default('pending'); // pending, active, error
    $table->text('status_message')->nullable();
    $table->timestamp('last_synced_at')->nullable();

    // Traefik config hash (for change detection)
    $table->string('config_hash')->nullable();

    $table->timestamps();

    // Indexes
    $table->index(['edge_server_id', 'domain']);
    $table->index(['backend_server_id']);
    $table->unique(['edge_server_id', 'domain', 'path']);
});
```

### 2.3 Миграция: add_edge_settings_to_server_settings

```php
// database/migrations/2026_01_28_000003_add_edge_settings_to_server_settings.php

Schema::table('server_settings', function (Blueprint $table) {
    // Edge proxy settings
    $table->boolean('is_edge_proxy_enabled')->default(false);
    $table->boolean('is_wireguard_configured')->default(false);
    $table->integer('wireguard_port')->default(51820);
    $table->string('wireguard_network')->default('10.100.0.0/24');

    // Auto-sync settings
    $table->boolean('auto_sync_routes')->default(true);
    $table->integer('route_sync_interval_seconds')->default(60);
});
```

---

## 3. Models

### 3.1 Server Model Updates

```php
// app/Models/Server.php - additions

// Relations
public function edgeServer(): BelongsTo
{
    return $this->belongsTo(Server::class, 'edge_server_id');
}

public function backendServers(): HasMany
{
    return $this->hasMany(Server::class, 'edge_server_id');
}

public function edgeRoutes(): HasMany
{
    return $this->hasMany(EdgeRoute::class, 'edge_server_id');
}

public function backendRoutes(): HasMany
{
    return $this->hasMany(EdgeRoute::class, 'backend_server_id');
}

// Scopes
public function scopeEdgeServers($query)
{
    return $query->where('is_edge_server', true);
}

public function scopeBackendServers($query)
{
    return $query->where('is_edge_server', false)
        ->whereNotNull('edge_server_id');
}

// Helpers
public function isEdgeServer(): bool
{
    return $this->is_edge_server === true;
}

public function hasEdgeServer(): bool
{
    return $this->edge_server_id !== null;
}

public function getWireGuardConfig(): array
{
    return [
        'private_key' => decrypt($this->wireguard_private_key),
        'public_key' => $this->wireguard_public_key,
        'ip' => $this->wireguard_ip,
        'endpoint' => $this->wireguard_endpoint,
        'port' => $this->settings->wireguard_port ?? 51820,
    ];
}

// Accessors
public function wireguardPrivateKey(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ? decrypt($value) : null,
        set: fn ($value) => $value ? encrypt($value) : null,
    );
}
```

### 3.2 EdgeRoute Model

```php
// app/Models/EdgeRoute.php

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeRoute extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // Relations
    public function edgeServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'edge_server_id');
    }

    public function backendServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'backend_server_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Helpers
    public function getTraefikRouterName(): string
    {
        return 'edge-' . $this->uuid;
    }

    public function getTraefikServiceName(): string
    {
        return 'edge-svc-' . $this->uuid;
    }

    public function getBackendUrl(): string
    {
        return "{$this->protocol}://{$this->backend_ip}:{$this->backend_port}";
    }

    public function generateTraefikConfig(): array
    {
        $routerName = $this->getTraefikRouterName();
        $serviceName = $this->getTraefikServiceName();

        $config = [
            'http' => [
                'routers' => [
                    "{$routerName}-https" => [
                        'rule' => $this->buildTraefikRule(),
                        'entryPoints' => ['https'],
                        'service' => $serviceName,
                        'tls' => [
                            'certResolver' => $this->ssl_cert_resolver,
                        ],
                    ],
                    "{$routerName}-http" => [
                        'rule' => $this->buildTraefikRule(),
                        'entryPoints' => ['http'],
                        'middlewares' => ['redirect-to-https'],
                    ],
                ],
                'services' => [
                    $serviceName => [
                        'loadBalancer' => [
                            'servers' => [
                                ['url' => $this->getBackendUrl()],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $config;
    }

    private function buildTraefikRule(): string
    {
        $rule = "Host(`{$this->domain}`)";

        if ($this->path !== '/') {
            $rule .= " && PathPrefix(`{$this->path}`)";
        }

        return $rule;
    }

    public function markAsSynced(): void
    {
        $this->update([
            'status' => 'active',
            'last_synced_at' => now(),
            'config_hash' => $this->calculateConfigHash(),
        ]);
    }

    public function markAsError(string $message): void
    {
        $this->update([
            'status' => 'error',
            'status_message' => $message,
        ]);
    }

    public function needsSync(): bool
    {
        return $this->config_hash !== $this->calculateConfigHash();
    }

    private function calculateConfigHash(): string
    {
        return md5(json_encode([
            $this->domain,
            $this->path,
            $this->backend_ip,
            $this->backend_port,
            $this->protocol,
            $this->ssl_enabled,
        ]));
    }
}
```

---

## 4. WireGuard Management

### 4.1 WireGuard Helper Service

```php
// app/Services/WireGuardService.php

<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Str;

class WireGuardService
{
    private string $networkCidr = '10.100.0.0/24';
    private int $defaultPort = 51820;

    /**
     * Generate WireGuard keypair
     */
    public function generateKeyPair(): array
    {
        // Generate private key
        $privateKey = trim(shell_exec('wg genkey'));

        // Derive public key
        $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey"));

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Allocate next available WireGuard IP
     */
    public function allocateIp(Server $server): string
    {
        // Edge servers get .1, .2, etc.
        // Backend servers get .10, .11, etc.

        if ($server->isEdgeServer()) {
            $usedIps = Server::edgeServers()
                ->whereNotNull('wireguard_ip')
                ->pluck('wireguard_ip')
                ->map(fn ($ip) => (int) Str::afterLast($ip, '.'))
                ->toArray();

            $nextOctet = 1;
            while (in_array($nextOctet, $usedIps) && $nextOctet < 10) {
                $nextOctet++;
            }
        } else {
            $usedIps = Server::backendServers()
                ->whereNotNull('wireguard_ip')
                ->pluck('wireguard_ip')
                ->map(fn ($ip) => (int) Str::afterLast($ip, '.'))
                ->toArray();

            $nextOctet = 10;
            while (in_array($nextOctet, $usedIps) && $nextOctet < 255) {
                $nextOctet++;
            }
        }

        return '10.100.0.' . $nextOctet;
    }

    /**
     * Generate WireGuard config for Edge server
     */
    public function generateEdgeConfig(Server $edgeServer): string
    {
        $config = $this->getWireGuardConfig($edgeServer);
        $peers = $this->getBackendPeers($edgeServer);

        $wgConfig = <<<CONFIG
[Interface]
PrivateKey = {$config['private_key']}
Address = {$config['ip']}/24
ListenPort = {$config['port']}
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE

CONFIG;

        foreach ($peers as $peer) {
            $wgConfig .= <<<PEER

[Peer]
# {$peer['name']}
PublicKey = {$peer['public_key']}
AllowedIPs = {$peer['ip']}/32
PEER;

            // Если есть endpoint (для статических IP)
            if (!empty($peer['endpoint'])) {
                $wgConfig .= "\nEndpoint = {$peer['endpoint']}";
            }
        }

        return $wgConfig;
    }

    /**
     * Generate WireGuard config for Backend server
     */
    public function generateBackendConfig(Server $backendServer): string
    {
        $config = $this->getWireGuardConfig($backendServer);
        $edgeServer = $backendServer->edgeServer;
        $edgeConfig = $this->getWireGuardConfig($edgeServer);

        return <<<CONFIG
[Interface]
PrivateKey = {$config['private_key']}
Address = {$config['ip']}/24

[Peer]
# Edge Server: {$edgeServer->name}
PublicKey = {$edgeConfig['public_key']}
Endpoint = {$edgeServer->ip}:{$edgeConfig['port']}
AllowedIPs = 10.100.0.0/24
PersistentKeepalive = 25
CONFIG;
    }

    private function getWireGuardConfig(Server $server): array
    {
        return [
            'private_key' => $server->wireguard_private_key,
            'public_key' => $server->wireguard_public_key,
            'ip' => $server->wireguard_ip,
            'port' => $server->settings->wireguard_port ?? $this->defaultPort,
            'endpoint' => $server->wireguard_endpoint,
        ];
    }

    private function getBackendPeers(Server $edgeServer): array
    {
        return $edgeServer->backendServers()
            ->whereNotNull('wireguard_public_key')
            ->get()
            ->map(fn ($server) => [
                'name' => $server->name,
                'public_key' => $server->wireguard_public_key,
                'ip' => $server->wireguard_ip,
                'endpoint' => $server->wireguard_endpoint,
            ])
            ->toArray();
    }
}
```

### 4.2 ConfigureWireGuard Action

```php
// app/Actions/Server/ConfigureWireGuard.php

<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Services\WireGuardService;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfigureWireGuard
{
    use AsAction;

    public function __construct(
        private WireGuardService $wireGuardService
    ) {}

    public function handle(Server $server): void
    {
        // 1. Generate keypair if not exists
        if (!$server->wireguard_public_key) {
            $keys = $this->wireGuardService->generateKeyPair();
            $server->update([
                'wireguard_private_key' => $keys['private_key'],
                'wireguard_public_key' => $keys['public_key'],
            ]);
        }

        // 2. Allocate IP if not exists
        if (!$server->wireguard_ip) {
            $ip = $this->wireGuardService->allocateIp($server);
            $server->update(['wireguard_ip' => $ip]);
        }

        // 3. Set endpoint for edge servers
        if ($server->isEdgeServer() && !$server->wireguard_endpoint) {
            $port = $server->settings->wireguard_port ?? 51820;
            $server->update([
                'wireguard_endpoint' => "{$server->ip}:{$port}",
            ]);
        }

        // 4. Generate and deploy config
        $config = $server->isEdgeServer()
            ? $this->wireGuardService->generateEdgeConfig($server)
            : $this->wireGuardService->generateBackendConfig($server);

        $configBase64 = base64_encode($config);

        // 5. Install WireGuard and deploy config
        $commands = collect([
            // Install WireGuard
            'which wg || (apt-get update && apt-get install -y wireguard)',

            // Create config directory
            'mkdir -p /etc/wireguard',

            // Write config
            "echo '{$configBase64}' | base64 -d > /etc/wireguard/wg0.conf",
            'chmod 600 /etc/wireguard/wg0.conf',

            // Enable and start WireGuard
            'systemctl enable wg-quick@wg0',
            'systemctl restart wg-quick@wg0',

            // Verify
            'wg show wg0',
        ]);

        instant_remote_process($commands, $server);

        // 6. Update settings
        $server->settings->update([
            'is_wireguard_configured' => true,
        ]);
    }
}
```

### 4.3 SyncWireGuardPeers Action

```php
// app/Actions/Server/SyncWireGuardPeers.php

<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Services\WireGuardService;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncWireGuardPeers
{
    use AsAction;

    public function __construct(
        private WireGuardService $wireGuardService
    ) {}

    /**
     * Sync WireGuard peers on Edge server after backend changes
     */
    public function handle(Server $edgeServer): void
    {
        if (!$edgeServer->isEdgeServer()) {
            throw new \InvalidArgumentException('Server is not an edge server');
        }

        // Regenerate Edge config with all current peers
        $config = $this->wireGuardService->generateEdgeConfig($edgeServer);
        $configBase64 = base64_encode($config);

        $commands = collect([
            "echo '{$configBase64}' | base64 -d > /etc/wireguard/wg0.conf",
            'chmod 600 /etc/wireguard/wg0.conf',
            'wg syncconf wg0 <(wg-quick strip wg0)', // Hot reload without restart
        ]);

        instant_remote_process($commands, $edgeServer);
    }
}
```

---

## 5. Edge Route Sync

### 5.1 SyncEdgeRoutes Action

```php
// app/Actions/EdgeProxy/SyncEdgeRoutes.php

<?php

namespace App\Actions\EdgeProxy;

use App\Models\Application;
use App\Models\EdgeRoute;
use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Yaml\Yaml;

class SyncEdgeRoutes
{
    use AsAction;

    /**
     * Sync all routes for an application to its edge server
     */
    public function handle(Application $application): void
    {
        $server = $application->destination->server;

        // Skip if server doesn't use edge proxy
        if (!$server->hasEdgeServer()) {
            return;
        }

        $edgeServer = $server->edgeServer;
        $domains = $application->fqdns;

        foreach ($domains as $fqdn) {
            $url = parse_url($fqdn);
            $domain = $url['host'] ?? $fqdn;
            $path = $url['path'] ?? '/';
            $port = $application->ports_exposes_array[0] ?? 80;

            // Create or update edge route
            $route = EdgeRoute::updateOrCreate(
                [
                    'edge_server_id' => $edgeServer->id,
                    'application_id' => $application->id,
                    'domain' => $domain,
                    'path' => $path,
                ],
                [
                    'uuid' => $this->generateRouteUuid($application, $domain),
                    'backend_server_id' => $server->id,
                    'backend_ip' => $server->wireguard_ip,
                    'backend_port' => $port,
                    'protocol' => 'http',
                    'ssl_enabled' => str_starts_with($fqdn, 'https'),
                    'status' => 'pending',
                ]
            );
        }

        // Remove orphaned routes
        EdgeRoute::where('application_id', $application->id)
            ->whereNotIn('domain', collect($domains)->map(fn ($d) => parse_url($d)['host'] ?? $d))
            ->delete();

        // Sync to edge server
        $this->syncToEdgeServer($edgeServer);
    }

    /**
     * Sync all routes to edge server's Traefik
     */
    public function syncToEdgeServer(Server $edgeServer): void
    {
        $routes = $edgeServer->edgeRoutes()->where('status', '!=', 'error')->get();

        // Generate combined Traefik config
        $config = [
            'http' => [
                'routers' => [],
                'services' => [],
                'middlewares' => [
                    'redirect-to-https' => [
                        'redirectScheme' => [
                            'scheme' => 'https',
                            'permanent' => true,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($routes as $route) {
            $routeConfig = $route->generateTraefikConfig();
            $config['http']['routers'] = array_merge(
                $config['http']['routers'],
                $routeConfig['http']['routers']
            );
            $config['http']['services'] = array_merge(
                $config['http']['services'],
                $routeConfig['http']['services']
            );
        }

        // Convert to YAML and deploy
        $yaml = Yaml::dump($config, 10, 2);
        $yamlBase64 = base64_encode($yaml);
        $dynamicConfigPath = '/data/saturn/proxy/dynamic/edge-routes.yaml';

        $commands = collect([
            "mkdir -p /data/saturn/proxy/dynamic",
            "echo '{$yamlBase64}' | base64 -d > {$dynamicConfigPath}",
        ]);

        instant_remote_process($commands, $edgeServer);

        // Mark routes as synced
        foreach ($routes as $route) {
            $route->markAsSynced();
        }
    }

    private function generateRouteUuid(Application $application, string $domain): string
    {
        return md5($application->uuid . '-' . $domain);
    }
}
```

### 5.2 Integration with Deployment

```php
// app/Traits/Deployment/HandlesPostDeployment.php - additions

private function post_deployment(): void
{
    // ... existing code ...

    // Sync edge routes if using edge proxy
    $this->syncEdgeRoutesIfNeeded();
}

private function syncEdgeRoutesIfNeeded(): void
{
    $server = $this->application->destination->server;

    if ($server->hasEdgeServer()) {
        $this->application_deployment_queue->addLogEntry('Syncing routes to edge proxy...');

        try {
            SyncEdgeRoutes::run($this->application);
            $this->application_deployment_queue->addLogEntry('Edge routes synced successfully.');
        } catch (\Throwable $e) {
            $this->application_deployment_queue->addLogEntry(
                'Warning: Failed to sync edge routes: ' . $e->getMessage()
            );
        }
    }
}
```

---

## 6. Jobs

### 6.1 SyncEdgeRoutesJob

```php
// app/Jobs/SyncEdgeRoutesJob.php

<?php

namespace App\Jobs;

use App\Actions\EdgeProxy\SyncEdgeRoutes;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEdgeRoutesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public Server $edgeServer
    ) {}

    public function handle(): void
    {
        SyncEdgeRoutes::make()->syncToEdgeServer($this->edgeServer);
    }
}
```

### 6.2 ConfigureBackendForEdgeJob

```php
// app/Jobs/ConfigureBackendForEdgeJob.php

<?php

namespace App\Jobs;

use App\Actions\Server\ConfigureWireGuard;
use App\Actions\Server\SyncWireGuardPeers;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ConfigureBackendForEdgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(
        public Server $backendServer,
        public Server $edgeServer
    ) {}

    public function handle(): void
    {
        // 1. Assign edge server
        $this->backendServer->update([
            'edge_server_id' => $this->edgeServer->id,
        ]);

        // 2. Configure WireGuard on backend
        ConfigureWireGuard::run($this->backendServer);

        // 3. Sync peers on edge server
        SyncWireGuardPeers::run($this->edgeServer);

        // 4. Disable public proxy on backend
        if ($this->backendServer->proxyType() !== 'none') {
            // Stop Traefik/Caddy on backend - no longer needed
            instant_remote_process([
                'docker stop saturn-proxy || true',
                'docker rm saturn-proxy || true',
            ], $this->backendServer);
        }

        // 5. Update settings
        $this->backendServer->settings->update([
            'is_edge_proxy_enabled' => true,
        ]);
    }
}
```

---

## 7. API Endpoints

### 7.1 Edge Server Controller

```php
// app/Http/Controllers/Api/EdgeServerController.php

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Server\ConfigureWireGuard;
use App\Http\Controllers\Controller;
use App\Jobs\ConfigureBackendForEdgeJob;
use App\Jobs\SyncEdgeRoutesJob;
use App\Models\Server;
use Illuminate\Http\Request;

class EdgeServerController extends Controller
{
    /**
     * Configure server as Edge proxy
     */
    public function configureAsEdge(Request $request, Server $server)
    {
        $this->authorize('update', $server);

        // Validate server requirements
        if ($server->applications()->exists()) {
            return response()->json([
                'error' => 'Server has applications. Edge servers should not run applications.',
            ], 422);
        }

        // Mark as edge server
        $server->update(['is_edge_server' => true]);

        // Configure WireGuard
        ConfigureWireGuard::run($server);

        // Ensure Traefik is running
        if ($server->proxyType() === 'none') {
            $server->proxy->set('type', 'traefik');
            $server->save();
        }

        return response()->json([
            'message' => 'Server configured as edge proxy',
            'wireguard_ip' => $server->wireguard_ip,
            'wireguard_public_key' => $server->wireguard_public_key,
        ]);
    }

    /**
     * Attach backend server to edge
     */
    public function attachBackend(Request $request, Server $edgeServer)
    {
        $this->authorize('update', $edgeServer);

        $validated = $request->validate([
            'backend_server_id' => 'required|exists:servers,id',
        ]);

        $backendServer = Server::findOrFail($validated['backend_server_id']);

        // Validate
        if (!$edgeServer->isEdgeServer()) {
            return response()->json(['error' => 'Server is not an edge server'], 422);
        }

        if ($backendServer->isEdgeServer()) {
            return response()->json(['error' => 'Cannot attach edge server as backend'], 422);
        }

        if ($backendServer->edge_server_id) {
            return response()->json(['error' => 'Backend already attached to an edge server'], 422);
        }

        // Dispatch configuration job
        ConfigureBackendForEdgeJob::dispatch($backendServer, $edgeServer);

        return response()->json([
            'message' => 'Backend server attachment started',
            'backend_server_id' => $backendServer->id,
        ]);
    }

    /**
     * Detach backend from edge
     */
    public function detachBackend(Request $request, Server $edgeServer, Server $backendServer)
    {
        $this->authorize('update', $edgeServer);

        if ($backendServer->edge_server_id !== $edgeServer->id) {
            return response()->json(['error' => 'Backend not attached to this edge'], 422);
        }

        // Remove WireGuard config from backend
        instant_remote_process([
            'systemctl stop wg-quick@wg0 || true',
            'systemctl disable wg-quick@wg0 || true',
            'rm -f /etc/wireguard/wg0.conf',
        ], $backendServer);

        // Clear edge association
        $backendServer->update([
            'edge_server_id' => null,
            'wireguard_ip' => null,
        ]);

        $backendServer->settings->update([
            'is_edge_proxy_enabled' => false,
            'is_wireguard_configured' => false,
        ]);

        // Remove routes
        $backendServer->backendRoutes()->delete();

        // Sync edge server
        SyncWireGuardPeers::run($edgeServer);
        SyncEdgeRoutesJob::dispatch($edgeServer);

        return response()->json(['message' => 'Backend detached successfully']);
    }

    /**
     * List edge routes
     */
    public function routes(Server $edgeServer)
    {
        $this->authorize('view', $edgeServer);

        return response()->json([
            'routes' => $edgeServer->edgeRoutes()
                ->with(['backendServer:id,name', 'application:id,name'])
                ->get(),
        ]);
    }

    /**
     * Force sync routes
     */
    public function syncRoutes(Server $edgeServer)
    {
        $this->authorize('update', $edgeServer);

        SyncEdgeRoutesJob::dispatch($edgeServer);

        return response()->json(['message' => 'Route sync started']);
    }
}
```

### 7.2 Routes

```php
// routes/api.php - additions

Route::prefix('edge-servers')->group(function () {
    Route::post('{server}/configure', [EdgeServerController::class, 'configureAsEdge']);
    Route::post('{server}/backends', [EdgeServerController::class, 'attachBackend']);
    Route::delete('{server}/backends/{backend}', [EdgeServerController::class, 'detachBackend']);
    Route::get('{server}/routes', [EdgeServerController::class, 'routes']);
    Route::post('{server}/routes/sync', [EdgeServerController::class, 'syncRoutes']);
});
```

---

## 8. Frontend Components

### 8.1 Edge Server Configuration Page

```typescript
// resources/js/pages/Servers/[id]/EdgeProxy.tsx

import { useState } from 'react';
import { useServer } from '@/hooks/useServers';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Shield, Server, Link, RefreshCw } from 'lucide-react';

export default function EdgeProxyPage() {
    const { server, refetch } = useServer();
    const [selectedBackend, setSelectedBackend] = useState('');
    const [isConfiguring, setIsConfiguring] = useState(false);

    const configureAsEdge = async () => {
        setIsConfiguring(true);
        try {
            await api.post(`/edge-servers/${server.id}/configure`);
            toast.success('Server configured as edge proxy');
            refetch();
        } catch (error) {
            toast.error('Failed to configure edge server');
        } finally {
            setIsConfiguring(false);
        }
    };

    const attachBackend = async () => {
        if (!selectedBackend) return;

        try {
            await api.post(`/edge-servers/${server.id}/backends`, {
                backend_server_id: selectedBackend,
            });
            toast.success('Backend server attachment started');
            refetch();
        } catch (error) {
            toast.error('Failed to attach backend');
        }
    };

    const syncRoutes = async () => {
        try {
            await api.post(`/edge-servers/${server.id}/routes/sync`);
            toast.success('Route sync started');
        } catch (error) {
            toast.error('Failed to sync routes');
        }
    };

    if (!server.is_edge_server) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5" />
                        Edge Proxy
                    </CardTitle>
                    <CardDescription>
                        Configure this server as an edge proxy to hide backend server IPs
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Alert>
                        <AlertDescription>
                            This server is not configured as an edge proxy.
                            Edge servers handle incoming traffic and proxy it to backend servers
                            through encrypted WireGuard tunnels.
                        </AlertDescription>
                    </Alert>

                    <div className="mt-4">
                        <Button onClick={configureAsEdge} disabled={isConfiguring}>
                            {isConfiguring ? 'Configuring...' : 'Configure as Edge Proxy'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Edge Server Status */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5" />
                        Edge Proxy Status
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">WireGuard IP</p>
                            <p className="font-mono">{server.wireguard_ip}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Public Key</p>
                            <p className="font-mono text-xs truncate">{server.wireguard_public_key}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Backend Servers</p>
                            <p>{server.backend_servers?.length || 0}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Active Routes</p>
                            <p>{server.edge_routes?.length || 0}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Backend Servers */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Server className="h-5 w-5" />
                        Backend Servers
                    </CardTitle>
                    <CardDescription>
                        Servers connected to this edge proxy via WireGuard
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>WireGuard IP</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {server.backend_servers?.map((backend) => (
                                <TableRow key={backend.id}>
                                    <TableCell>{backend.name}</TableCell>
                                    <TableCell className="font-mono">{backend.wireguard_ip}</TableCell>
                                    <TableCell>
                                        <Badge variant={backend.settings.is_wireguard_configured ? 'success' : 'warning'}>
                                            {backend.settings.is_wireguard_configured ? 'Connected' : 'Configuring'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="ghost" size="sm">Detach</Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    <div className="mt-4 flex gap-2">
                        <Select value={selectedBackend} onValueChange={setSelectedBackend}>
                            <SelectTrigger className="w-64">
                                <SelectValue placeholder="Select backend server" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableBackends.map((s) => (
                                    <SelectItem key={s.id} value={s.id.toString()}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button onClick={attachBackend} disabled={!selectedBackend}>
                            Attach Backend
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Edge Routes */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Link className="h-5 w-5" />
                                Edge Routes
                            </CardTitle>
                            <CardDescription>
                                Domain routes proxied through this edge server
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" onClick={syncRoutes}>
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Sync Routes
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Domain</TableHead>
                                <TableHead>Backend</TableHead>
                                <TableHead>Port</TableHead>
                                <TableHead>SSL</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {server.edge_routes?.map((route) => (
                                <TableRow key={route.id}>
                                    <TableCell className="font-mono">{route.domain}{route.path}</TableCell>
                                    <TableCell>{route.backend_server?.name}</TableCell>
                                    <TableCell>{route.backend_port}</TableCell>
                                    <TableCell>
                                        <Badge variant={route.ssl_enabled ? 'success' : 'secondary'}>
                                            {route.ssl_enabled ? 'Enabled' : 'Disabled'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={route.status === 'active' ? 'success' : 'warning'}>
                                            {route.status}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    );
}
```

---

## 9. Тестирование

### 9.1 Unit Tests

```php
// tests/Unit/Services/WireGuardServiceTest.php

<?php

use App\Services\WireGuardService;
use App\Models\Server;

it('generates valid keypair', function () {
    $service = new WireGuardService();
    $keys = $service->generateKeyPair();

    expect($keys)->toHaveKeys(['private_key', 'public_key']);
    expect($keys['private_key'])->toMatch('/^[A-Za-z0-9+\/=]{44}$/');
    expect($keys['public_key'])->toMatch('/^[A-Za-z0-9+\/=]{44}$/');
});

it('allocates correct IPs for edge servers', function () {
    $service = new WireGuardService();

    $edgeServer = Mockery::mock(Server::class);
    $edgeServer->shouldReceive('isEdgeServer')->andReturn(true);

    $ip = $service->allocateIp($edgeServer);

    expect($ip)->toMatch('/^10\.100\.0\.[1-9]$/');
});

it('allocates correct IPs for backend servers', function () {
    $service = new WireGuardService();

    $backendServer = Mockery::mock(Server::class);
    $backendServer->shouldReceive('isEdgeServer')->andReturn(false);

    $ip = $service->allocateIp($backendServer);

    expect($ip)->toMatch('/^10\.100\.0\.(1[0-9]|2[0-4][0-9]|25[0-4])$/');
});
```

### 9.2 Feature Tests

```php
// tests/Feature/EdgeProxy/EdgeServerConfigurationTest.php

<?php

use App\Models\Server;
use App\Models\User;

it('can configure server as edge proxy', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create(['team_id' => $user->currentTeam()->id]);

    $this->actingAs($user)
        ->postJson("/api/v1/edge-servers/{$server->id}/configure")
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'wireguard_ip',
            'wireguard_public_key',
        ]);

    $server->refresh();
    expect($server->is_edge_server)->toBeTrue();
    expect($server->wireguard_ip)->not->toBeNull();
});

it('prevents configuring server with applications as edge', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create(['team_id' => $user->currentTeam()->id]);
    Application::factory()->create(['destination_id' => $server->destinations()->first()->id]);

    $this->actingAs($user)
        ->postJson("/api/v1/edge-servers/{$server->id}/configure")
        ->assertStatus(422);
});
```

---

## 10. Checklist реализации

### Phase 1: Database & Models (4-6 часов)
- [ ] Создать миграции
- [ ] Обновить Server model
- [ ] Создать EdgeRoute model
- [ ] Обновить ServerSetting model
- [ ] Написать unit tests для моделей

### Phase 2: WireGuard Service (6-8 часов)
- [ ] Реализовать WireGuardService
- [ ] Создать ConfigureWireGuard action
- [ ] Создать SyncWireGuardPeers action
- [ ] Тестирование на dev серверах
- [ ] Unit tests

### Phase 3: Edge Route Sync (8-10 часов)
- [ ] Реализовать SyncEdgeRoutes action
- [ ] Интеграция с ApplicationDeploymentJob
- [ ] Создать SyncEdgeRoutesJob
- [ ] Тестирование синхронизации
- [ ] Feature tests

### Phase 4: API Endpoints (4-6 часов)
- [ ] Создать EdgeServerController
- [ ] Добавить routes
- [ ] Документация OpenAPI
- [ ] Feature tests для API

### Phase 5: Frontend (8-10 часов)
- [ ] Создать EdgeProxy page
- [ ] Добавить в навигацию
- [ ] Интеграция с hooks
- [ ] UI тестирование

### Phase 6: Integration & Testing (6-8 часов)
- [ ] End-to-end тестирование
- [ ] Тестирование на production-like окружении
- [ ] Документация
- [ ] Миграция существующих серверов

---

## 11. Риски и митигация

| Риск | Вероятность | Влияние | Митигация |
|------|-------------|---------|-----------|
| WireGuard несовместимость с ОС | Low | High | Проверка поддержки перед конфигурацией |
| Потеря связи при неправильной конфигурации | Medium | High | Сохранение backup конфигов, rollback механизм |
| Производительность WireGuard | Low | Medium | Мониторинг latency, оптимизация MTU |
| SSL сертификаты на Edge | Low | Medium | Rate limit Let's Encrypt, использование wildcard |
| Сложность отладки | Medium | Medium | Детальное логирование, status endpoints |

---

## 12. Будущие улучшения

1. **High Availability Edge**
   - Несколько Edge серверов с одинаковыми роутами
   - DNS round-robin или Cloudflare load balancing

2. **Географическое распределение**
   - Edge серверы в разных локациях
   - GeoDNS для роутинга к ближайшему Edge

3. **Мониторинг и алерты**
   - Prometheus metrics для WireGuard
   - Алерты при потере связи с backend

4. **Автоматическое масштабирование**
   - Добавление Edge серверов при росте нагрузки
   - Автоматическая балансировка

---

**Документ создан:** 2026-01-28
**Последнее обновление:** 2026-01-28
**Версия:** 1.0
