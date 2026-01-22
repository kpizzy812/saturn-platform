# Auto-Provisioning Architecture

**Status:** Planning
**Priority:** P2 (Phase 2)
**Created:** 2026-01-22
**Updated:** 2026-01-22

---

## Context

> Это внутренний проект компании.
> На начальном этапе нам не нужны доп VPS, так как сам мастер сервер имеет большие мощности и может удержать 10-15 проектов.
>
> Значит надо сделать умное авто: когда следующий деплой уже будет убивать производительность → создаётся VPS.
> Но оставить возможность вручную делать VPS для исключительных случаев.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Saturn Platform                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐   │
│  │   Sentinel   │───▶│  Metrics DB  │───▶│  Threshold   │   │
│  │  (monitor)   │    │  (Postgres)  │    │   Checker    │   │
│  └──────────────┘    └──────────────┘    └──────────────┘   │
│                                                 │            │
│                                                 ▼            │
│                                        ┌──────────────┐     │
│                                        │  Decision    │     │
│                                        │   Engine     │     │
│                                        └──────────────┘     │
│                                                 │            │
│                           ┌─────────────────────┼───────────┤
│                           ▼                     ▼            │
│                    ┌──────────────┐      ┌──────────────┐   │
│                    │   Notify     │      │ Auto-Create  │   │
│                    │   Admin      │      │    VPS       │   │
│                    └──────────────┘      └──────────────┘   │
│                                                 │            │
│                                                 ▼            │
│                                        ┌──────────────┐     │
│                                        │ Hetzner/DO   │     │
│                                        │     API      │     │
│                                        └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Master Server Only (Current)

```
[Master Server: 32GB RAM, 8 CPU]
     │
     ├── Saturn Platform (самa система)
     ├── App 1 (container)
     ├── App 2 (container)
     ├── PostgreSQL (container)
     ├── Redis (container)
     └── ... до 10-15 проектов
```

**Ограничения:**
- Все приложения на одном сервере
- Изоляция через Docker networks
- Wildcard DNS: `*.saturn.company.com` → Master IP

---

## Phase 2: Smart Auto-Provisioning

### 2.1 Resource Monitoring

**Что отслеживаем:**
- CPU usage (через Sentinel)
- Memory usage (через Sentinel)
- Disk usage (через Sentinel)
- Pending deployments queue
- Container count per server

**Пороговые значения (настраиваемые):**
```php
// config/constants.php
'auto_provision' => [
    'enabled' => env('AUTO_PROVISION_ENABLED', false),
    'cpu_warning' => 70,      // %
    'cpu_critical' => 85,     // % - триггер для создания VPS
    'memory_warning' => 75,   // %
    'memory_critical' => 90,  // % - триггер для создания VPS
    'disk_warning' => 80,     // %
    'disk_critical' => 95,    // %
    'sustained_minutes' => 5, // Сколько минут держать порог для триггера
],
```

### 2.2 Decision Engine

```php
class AutoProvisionDecisionEngine
{
    public function shouldProvisionNewServer(): ProvisionDecision
    {
        $masterServer = Server::find(0);
        $metrics = $masterServer->getAverageMetrics(minutes: 5);

        // Проверяем пороги
        if ($metrics['cpu'] > config('constants.auto_provision.cpu_critical')) {
            return ProvisionDecision::create('CPU overload');
        }

        if ($metrics['memory'] > config('constants.auto_provision.memory_critical')) {
            return ProvisionDecision::create('Memory overload');
        }

        // Проверяем очередь деплоев
        $pendingDeployments = ApplicationDeploymentQueue::where('status', 'queued')->count();
        if ($pendingDeployments > 10) {
            return ProvisionDecision::create('Deployment queue overflow');
        }

        return ProvisionDecision::none();
    }
}
```

### 2.3 Auto-Provision Flow

```
1. CheckServerResourcesJob runs every minute (via Scheduler)
         │
         ▼
2. Metrics exceed threshold for 5+ minutes?
         │
    No ──┴── Yes
         │
         ▼
3. AutoProvisionDecisionEngine::shouldProvisionNewServer()
         │
         ▼
4. If yes AND auto_provision_enabled:
         │
         ├─▶ Create VPS via Hetzner/DO API
         │        │
         │        ▼
         ├─▶ Wait for VPS ready (poll status)
         │        │
         │        ▼
         ├─▶ Run InstallDocker action
         │        │
         │        ▼
         ├─▶ Configure SSH keys
         │        │
         │        ▼
         ├─▶ Add to Saturn as new Server
         │        │
         │        ▼
         └─▶ Notify admin: "New VPS created: {name}"
```

---

## Phase 3: Manual VPS Creation (Parallel)

**Для исключительных случаев** - когда нужен отдельный сервер для конкретного проекта:

### UI Flow

```
Servers → Add Server → Choose method:

┌─────────────────────────────────────────────────┐
│  How would you like to add a server?            │
│                                                  │
│  ┌──────────────────┐  ┌──────────────────┐     │
│  │  🖥️  Manual       │  │  ☁️  Auto-Create  │     │
│  │  (Existing VPS)  │  │  (Hetzner/DO)    │     │
│  └──────────────────┘  └──────────────────┘     │
│                                                  │
│  Manual: Enter IP, SSH key, configure manually  │
│  Auto: We create and configure VPS for you      │
└─────────────────────────────────────────────────┘
```

### Auto-Create Flow

```
1. Choose provider: Hetzner / DigitalOcean / AWS
         │
         ▼
2. Choose size:
   - Small (2 CPU, 4GB RAM) - $6/mo
   - Medium (4 CPU, 8GB RAM) - $12/mo
   - Large (8 CPU, 16GB RAM) - $24/mo
         │
         ▼
3. Choose region: Nuremberg / Helsinki / Ashburn
         │
         ▼
4. Choose purpose:
   - General (any apps can deploy here)
   - Dedicated (only specific project)
         │
         ▼
5. Confirm → VPS created → Auto-configured → Ready
```

---

## Implementation Plan

### Database Changes

```php
// Migration: create_cloud_providers_table
Schema::create('cloud_providers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id');
    $table->string('type'); // hetzner, digitalocean, aws
    $table->string('name');
    $table->text('api_token'); // encrypted
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});

// Migration: add_auto_provision_to_instance_settings
Schema::table('instance_settings', function (Blueprint $table) {
    $table->boolean('auto_provision_enabled')->default(false);
    $table->integer('auto_provision_cpu_threshold')->default(85);
    $table->integer('auto_provision_memory_threshold')->default(90);
    $table->string('auto_provision_provider_id')->nullable();
    $table->string('auto_provision_server_type')->default('cx21'); // Hetzner small
    $table->string('auto_provision_location')->default('nbg1'); // Nuremberg
});

// Migration: add_provisioning_info_to_servers
Schema::table('servers', function (Blueprint $table) {
    $table->boolean('is_auto_provisioned')->default(false);
    $table->string('cloud_provider_id')->nullable();
    $table->string('cloud_server_id')->nullable(); // ID в Hetzner/DO
    $table->timestamp('provisioned_at')->nullable();
});
```

### New Files

```
app/
├── Actions/
│   └── Server/
│       ├── AutoProvisionServer.php      # Main provisioning logic
│       └── DestroyCloudServer.php       # Cleanup when deleted
├── Jobs/
│   ├── CheckServerResourcesJob.php      # Periodic check (every minute)
│   ├── AutoProvisionServerJob.php       # Async provisioning
│   └── WaitForServerReadyJob.php        # Poll until ready
├── Models/
│   └── CloudProvider.php                # Provider credentials
├── Services/
│   ├── CloudProviderFactory.php         # Factory pattern
│   ├── HetznerCloudService.php          # Hetzner API
│   ├── DigitalOceanService.php          # DO API
│   └── AutoProvisionDecisionEngine.php  # Decision logic
└── Http/Controllers/
    └── Api/
        └── CloudProviderController.php  # CRUD for providers

resources/js/pages/
├── Settings/
│   └── AutoProvisioning.tsx             # Admin settings
└── Servers/
    └── CreateAuto.tsx                   # Auto-create wizard
```

### Hetzner API Integration

```php
// app/Services/HetznerCloudService.php
class HetznerCloudService implements CloudProviderInterface
{
    private string $apiToken;
    private string $baseUrl = 'https://api.hetzner.cloud/v1';

    public function createServer(CreateServerRequest $request): CloudServer
    {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/servers", [
                'name' => $request->name,
                'server_type' => $request->type, // cx21, cx31, etc
                'location' => $request->location, // nbg1, fsn1, hel1
                'image' => 'ubuntu-22.04',
                'ssh_keys' => [$request->sshKeyId],
                'labels' => [
                    'saturn' => 'true',
                    'team_id' => $request->teamId,
                ],
            ]);

        return CloudServer::fromHetznerResponse($response->json());
    }

    public function getServer(string $serverId): CloudServer
    {
        $response = Http::withToken($this->apiToken)
            ->get("{$this->baseUrl}/servers/{$serverId}");

        return CloudServer::fromHetznerResponse($response->json()['server']);
    }

    public function deleteServer(string $serverId): bool
    {
        $response = Http::withToken($this->apiToken)
            ->delete("{$this->baseUrl}/servers/{$serverId}");

        return $response->successful();
    }

    public function waitUntilReady(string $serverId, int $timeoutSeconds = 300): bool
    {
        $start = time();

        while (time() - $start < $timeoutSeconds) {
            $server = $this->getServer($serverId);

            if ($server->status === 'running') {
                return true;
            }

            sleep(5);
        }

        return false;
    }
}
```

---

## Pricing Reference (Hetzner Cloud)

| Type | CPU | RAM | Disk | Price/mo |
|------|-----|-----|------|----------|
| cx21 | 2 vCPU | 4 GB | 40 GB | €4.35 |
| cx31 | 2 vCPU | 8 GB | 80 GB | €7.85 |
| cx41 | 4 vCPU | 16 GB | 160 GB | €14.95 |
| cx51 | 8 vCPU | 32 GB | 240 GB | €29.90 |

---

## Security Considerations

1. **API Tokens** - хранить encrypted в БД
2. **SSH Keys** - генерировать уникальные для каждого сервера
3. **Firewall** - auto-configure при создании (только 22, 80, 443)
4. **Cleanup** - удалять VPS при удалении сервера из Saturn
5. **Cost limits** - максимальное количество auto-provisioned серверов

---

## Questions to Resolve

1. **Wildcard DNS для новых серверов?**
   - Вариант A: `*.s1.saturn.company.com`, `*.s2.saturn.company.com`
   - Вариант B: Cloudflare API для создания A-records

2. **Когда удалять auto-provisioned сервер?**
   - Когда все приложения удалены?
   - После N дней неактивности?
   - Только вручную?

3. **Миграция приложений между серверами?**
   - Нужна ли возможность переместить приложение на другой сервер?

---

## Related Files

- [todos/railway-like-experience.md](railway-like-experience.md) - общий план
- [Hetzner Cloud API Docs](https://docs.hetzner.cloud/)
- [DigitalOcean API Docs](https://docs.digitalocean.com/reference/api/)
