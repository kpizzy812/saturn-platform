# Railway-like Experience Implementation Plan

## Overview
Реализация упрощённого деплоя в стиле Railway: указал репо → нажал Deploy → приложение работает.

## Phase 1: Auto-inject DATABASE_URL [P0]

### Задача
При деплое автоматически инжектить connection string если в том же Environment есть база данных.

### Файлы для изменения

1. **Migration** - `database/migrations/2026_01_23_add_auto_inject_database_url_to_applications.php`
   - Добавить колонку `auto_inject_database_url` boolean default true

2. **Application.php** - `app/Models/Application.php`
   - Добавить метод `autoInjectDatabaseUrl()` который:
     - Получает все БД из `$this->environment->databases()`
     - Для каждой БД создаёт env variable с `internal_db_url`
     - PostgreSQL/MySQL/MariaDB → DATABASE_URL
     - Redis/KeyDB/Dragonfly → REDIS_URL
     - MongoDB → MONGODB_URL
     - ClickHouse → CLICKHOUSE_URL

3. **ApplicationDeploymentJob.php** - `app/Jobs/ApplicationDeploymentJob.php`
   - В `deploy_nixpacks_buildpack()` после clone вызвать `$this->application->autoInjectDatabaseUrl()`

4. **Standalone* модели** - при создании БД триггерить auto-inject для существующих приложений

### Код
```php
// Application.php
public function autoInjectDatabaseUrl(): void
{
    if (!$this->auto_inject_database_url) return;

    foreach ($this->environment->databases() as $db) {
        $envKey = match(get_class($db)) {
            StandalonePostgresql::class, StandaloneMysql::class, StandaloneMariadb::class => 'DATABASE_URL',
            StandaloneRedis::class, StandaloneKeydb::class, StandaloneDragonfly::class => 'REDIS_URL',
            StandaloneMongodb::class => 'MONGODB_URL',
            StandaloneClickhouse::class => 'CLICKHOUSE_URL',
        };

        $this->environment_variables()->updateOrCreate(
            ['key' => $envKey, 'is_preview' => false],
            ['value' => $db->internal_db_url, 'is_build_time' => false]
        );
    }
}
```

---

## Phase 1.5: Canvas Database Links [P0]

### Задача
Визуальное связывание приложений с базами данных на канвасе проекта. При создании связи автоматически инжектить DATABASE_URL.

### Текущее состояние
- Канвас использует `@xyflow/react` v12.3.6
- Карточки: `ServiceNode` (приложения) и `DatabaseNode` (БД)
- Связи (edges) уже поддерживаются визуально, но НЕ персистируются
- Позиции элементов НЕ сохраняются в БД

### Файлы для изменения

1. **Migration** - `database/migrations/2026_01_23_create_resource_links_table.php`
```php
Schema::create('resource_links', function (Blueprint $table) {
    $table->id();
    $table->foreignId('environment_id')->constrained()->cascadeOnDelete();

    // Source (обычно приложение)
    $table->morphs('source'); // source_type, source_id

    // Target (обычно база данных)
    $table->morphs('target'); // target_type, target_id

    // Какую env variable инжектить (DATABASE_URL, REDIS_URL, etc.)
    $table->string('inject_as')->nullable();

    // Автоматически инжектить при деплое
    $table->boolean('auto_inject')->default(true);

    $table->timestamps();

    // Уникальность связи
    $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'unique_resource_link');
});
```

2. **Model** - `app/Models/ResourceLink.php` (NEW)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResourceLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto_inject' => 'boolean',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the default env key based on target type.
     */
    public static function getDefaultEnvKey(string $targetClass): string
    {
        return match($targetClass) {
            StandalonePostgresql::class, StandaloneMysql::class, StandaloneMariadb::class => 'DATABASE_URL',
            StandaloneRedis::class, StandaloneKeydb::class, StandaloneDragonfly::class => 'REDIS_URL',
            StandaloneMongodb::class => 'MONGODB_URL',
            StandaloneClickhouse::class => 'CLICKHOUSE_URL',
            default => 'CONNECTION_URL',
        };
    }
}
```

3. **Application.php** - обновить `autoInjectDatabaseUrl()`
```php
// Использовать ResourceLink вместо всех БД в environment
public function autoInjectDatabaseUrl(): void
{
    if (!$this->auto_inject_database_url) return;

    // Получить связи этого приложения с БД
    $links = ResourceLink::where('source_type', self::class)
        ->where('source_id', $this->id)
        ->where('auto_inject', true)
        ->with('target')
        ->get();

    foreach ($links as $link) {
        $db = $link->target;
        if (!$db || !method_exists($db, 'getInternalDbUrlAttribute')) continue;

        $envKey = $link->inject_as ?? ResourceLink::getDefaultEnvKey(get_class($db));

        $this->environment_variables()->updateOrCreate(
            ['key' => $envKey, 'is_preview' => false],
            ['value' => $db->internal_db_url, 'is_build_time' => false]
        );
    }
}
```

4. **API Routes** - `routes/api.php`
```php
// Resource Links API
Route::prefix('environments/{environment_uuid}')->group(function () {
    Route::get('/links', [ResourceLinkController::class, 'index']);
    Route::post('/links', [ResourceLinkController::class, 'store']);
    Route::delete('/links/{link_id}', [ResourceLinkController::class, 'destroy']);
});
```

5. **Controller** - `app/Http/Controllers/Api/ResourceLinkController.php` (NEW)
```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Environment;
use App\Models\ResourceLink;
use Illuminate\Http\Request;

class ResourceLinkController extends Controller
{
    public function index(string $environment_uuid)
    {
        $environment = Environment::whereUuid($environment_uuid)->firstOrFail();
        return ResourceLink::where('environment_id', $environment->id)->get();
    }

    public function store(Request $request, string $environment_uuid)
    {
        $environment = Environment::whereUuid($environment_uuid)->firstOrFail();

        $validated = $request->validate([
            'source_type' => 'required|string',
            'source_id' => 'required|integer',
            'target_type' => 'required|string',
            'target_id' => 'required|integer',
            'inject_as' => 'nullable|string',
            'auto_inject' => 'boolean',
        ]);

        $link = ResourceLink::create([
            'environment_id' => $environment->id,
            ...$validated,
        ]);

        // Сразу инжектить если auto_inject
        if ($link->auto_inject && $link->source instanceof \App\Models\Application) {
            $link->source->autoInjectDatabaseUrl();
        }

        return $link;
    }

    public function destroy(string $environment_uuid, int $link_id)
    {
        $link = ResourceLink::findOrFail($link_id);

        // Удалить инжектированную переменную
        if ($link->source instanceof \App\Models\Application) {
            $envKey = $link->inject_as ?? ResourceLink::getDefaultEnvKey($link->target_type);
            $link->source->environment_variables()
                ->where('key', $envKey)
                ->delete();
        }

        $link->delete();
        return response()->noContent();
    }
}
```

6. **ProjectCanvas.tsx** - `resources/js/components/features/canvas/ProjectCanvas.tsx`
```typescript
// Добавить загрузку связей из API
const [resourceLinks, setResourceLinks] = useState<ResourceLink[]>([]);

useEffect(() => {
    // Загрузить сохранённые связи
    fetch(`/api/v1/environments/${environmentUuid}/links`)
        .then(res => res.json())
        .then(links => {
            setResourceLinks(links);
            // Преобразовать в edges
            const savedEdges = links.map(link => ({
                id: `link-${link.id}`,
                source: `app-${link.source_id}`,
                target: `db-${link.target_id}`,
                type: 'smoothstep',
                animated: link.auto_inject,
                data: { linkId: link.id, injectAs: link.inject_as },
                style: { stroke: '#22c55e', strokeWidth: 2 }, // Зелёный для активных связей
                markerEnd: { type: MarkerType.ArrowClosed, color: '#22c55e' },
            }));
            setEdges(savedEdges);
        });
}, [environmentUuid]);

// Обновить onConnect для сохранения в БД
const onConnect = useCallback(async (params: Connection) => {
    // Определить типы source и target
    const sourceType = params.source?.startsWith('app-') ? 'App\\Models\\Application' : null;
    const targetType = params.target?.startsWith('db-') ? getDatabaseType(params.target) : null;

    if (!sourceType || !targetType) return;

    const sourceId = parseInt(params.source!.replace('app-', ''));
    const targetId = parseInt(params.target!.replace(/^db-\w+-/, ''));

    // Сохранить связь в БД
    const response = await fetch(`/api/v1/environments/${environmentUuid}/links`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            source_type: sourceType,
            source_id: sourceId,
            target_type: targetType,
            target_id: targetId,
            auto_inject: true,
        }),
    });

    const link = await response.json();

    // Добавить edge визуально
    setEdges(eds => addEdge({
        ...params,
        id: `link-${link.id}`,
        type: 'smoothstep',
        animated: true,
        data: { linkId: link.id },
        style: { stroke: '#22c55e', strokeWidth: 2 },
        markerEnd: { type: MarkerType.ArrowClosed, color: '#22c55e' },
    }, eds));

    // Показать toast
    toast.success(`Connected! DATABASE_URL will be injected on next deploy.`);
}, [environmentUuid, setEdges]);

// Обновить удаление связи
const onEdgesDelete = useCallback(async (edgesToDelete: Edge[]) => {
    for (const edge of edgesToDelete) {
        if (edge.data?.linkId) {
            await fetch(`/api/v1/environments/${environmentUuid}/links/${edge.data.linkId}`, {
                method: 'DELETE',
            });
        }
    }
}, [environmentUuid]);
```

7. **Inertia Controller** - обновить `ProjectController.php`
```php
// В методе show() добавить links
public function show(string $uuid)
{
    $project = Project::whereUuid($uuid)->firstOrFail();

    return Inertia::render('Projects/Show', [
        'project' => $project,
        'environments' => $project->environments->map(fn($env) => [
            // ... existing data
            'resource_links' => ResourceLink::where('environment_id', $env->id)->get(),
        ]),
    ]);
}
```

### UI/UX

1. **Визуальное отличие связей:**
   - Зелёная линия (`#22c55e`) = активная связь с auto-inject
   - Серая пунктирная = связь без auto-inject
   - Анимация = pending injection (ещё не задеплоено)

2. **Контекстное меню на связи:**
   - "Edit Variable Name" - изменить inject_as
   - "Toggle Auto-inject" - вкл/выкл автоматическую инъекцию
   - "Delete Connection" - удалить связь

3. **Tooltip на связи:**
   - Показывать: `DATABASE_URL → postgres://...`

### Тестирование
```bash
# 1. Открыть канвас проекта
# 2. Перетащить линию от приложения к БД
# 3. Проверить что связь сохранилась (обновить страницу)
# 4. Задеплоить приложение
# 5. Проверить что DATABASE_URL появился в env vars
```

---

## Phase 2: Auto-detect Port [P1]

### Задача
Парсить EXPOSE из Dockerfile/Nixpacks и автоматически устанавливать `ports_exposes`.

### Файлы для изменения

1. **docker.php** - `bootstrap/helpers/docker.php`
   - Улучшить `get_port_from_dockerfile()` → поддержка `EXPOSE 3000 8080` и `EXPOSE 3000/tcp`
   - Добавить `get_ports_from_dockerfile()` возвращающий массив портов

2. **ApplicationDeploymentJob.php** - `app/Jobs/ApplicationDeploymentJob.php`
   - Добавить метод `autoDetectPortFromNixpacks()`:
     - Читает сгенерированный `.nixpacks/Dockerfile`
     - Парсит EXPOSE директивы
     - Проверяет переменную PORT из nixpacks plan
     - Устанавливает `ports_exposes` если пусто или = '80'
   - Вызывать после `generate_nixpacks_confs()`

### Код
```php
// ApplicationDeploymentJob.php
private function autoDetectPortFromNixpacks(): void
{
    if (!empty($this->application->ports_exposes) && $this->application->ports_exposes !== '80') {
        return; // User explicitly set port
    }

    $dockerfile = $this->readRemoteFile('.nixpacks/Dockerfile');
    $ports = get_ports_from_dockerfile($dockerfile);

    if (empty($ports)) {
        $portFromPlan = data_get($this->nixpacks_plan_json, 'variables.PORT');
        if (is_numeric($portFromPlan)) $ports = [(int)$portFromPlan];
    }

    if ($ports) {
        $this->application->update(['ports_exposes' => implode(',', $ports)]);
        $this->application_deployment_queue->addLogEntry("Auto-detected port: " . implode(',', $ports));
    }
}
```

---

## Phase 3: Quick Deploy Flow [P1]

### Задача
Упростить boarding flow - использовать localhost, минимум настроек.

### Файлы для изменения

1. **routes/web.php** - обновить `POST /boarding/deploy` (~line 3938)
   - Убрать hardcoded `ports_exposes = '80'` → будет auto-detect
   - Добавить `auto_inject_database_url = true`
   - Вызывать `$application->autoInjectDatabaseUrl()` перед деплоем

2. **Boarding/Index.tsx** - `resources/js/pages/Boarding/Index.tsx`
   - ServerStep: добавить опцию "Use main server (localhost)" для быстрого старта
   - Упростить UI для internal use case

### Изменения в routes/web.php
```php
Route::post('/boarding/deploy', function (Request $request) {
    // ... validation ...

    $application = Application::create([
        'name' => $validated['name'],
        'git_repository' => $validated['git_repository'],
        'git_branch' => $validated['git_branch'] ?? 'main',
        'build_pack' => 'nixpacks',
        'environment_id' => $environment->id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
        'ports_exposes' => '80', // Will be auto-detected
        'auto_inject_database_url' => true, // NEW
    ]);

    $application->fqdn = generateUrl($server, $application->uuid);
    $application->autoInjectDatabaseUrl(); // NEW
    $application->save();

    queue_application_deployment($application, Str::uuid());
    // ...
});
```

---

## Phase 4: Service Discovery UX [P2]

### Задача
Показывать Internal URL для баз данных в UI.

### Файлы для изменения

1. **DatabaseController.php** - `app/Http/Controllers/Inertia/DatabaseController.php`
   - Добавить `internal_db_url` и `external_db_url` в response

2. **Databases/Overview.tsx** или **Connections.tsx**
   - Добавить карточку "Internal URL" с copy button
   - Показать инструкцию: "Apps in this environment can connect using this URL"

---

## Phase 5: Resource Monitoring [P1]

### Задача
Отслеживать ресурсы master server и уведомлять при превышении порогов.

### Файлы для создания/изменения

1. **Migration** - `database/migrations/2026_01_23_add_resource_thresholds.php`
   - Добавить в `instance_settings`:
     - `resource_warning_cpu_threshold` (70)
     - `resource_critical_cpu_threshold` (85)
     - `resource_warning_memory_threshold` (75)
     - `resource_critical_memory_threshold` (90)
     - `resource_warning_disk_threshold` (80)
     - `resource_critical_disk_threshold` (95)
     - `auto_provision_enabled` (false)
     - `auto_provision_provider` (nullable)
     - `auto_provision_api_key` (encrypted)

2. **CheckServerResourcesJob.php** - `app/Jobs/CheckServerResourcesJob.php` (NEW)
   - Получает метрики через Sentinel API (уже есть `Server::getCpuMetrics()`)
   - Сравнивает с порогами
   - Отправляет уведомления при превышении

3. **Kernel.php** - добавить в schedule
   - `CheckServerResourcesJob::dispatch()->everyFiveMinutes()`

---

## Phase 6: Auto-Provisioning [P2] ✅ COMPLETED

### Задача
При критических ресурсах автоматически создавать VPS через Hetzner API.

### Реализовано

1. **AutoProvisionServerJob.php** - `app/Jobs/AutoProvisionServerJob.php`
   - Создание VPS через существующий HetznerService
   - Ожидание готовности SSH
   - Установка Docker через InstallDocker action
   - Уведомления через ServerAutoProvisioned notification
   - Daily limit и cooldown для предотвращения спама

2. **AutoProvisioningEvent.php** - `app/Models/AutoProvisioningEvent.php`
   - Модель для tracking статуса provisioning
   - Статусы: pending, provisioning, installing, ready, failed
   - Trigger reasons: cpu_critical, memory_critical, manual

3. **ServerAutoProvisioned.php** - `app/Notifications/Server/ServerAutoProvisioned.php`
   - Multi-channel notification (email, Discord, Telegram, Slack, webhook)

4. **CheckServerResourcesJob.php** - обновлён
   - Интеграция с AutoProvisionServerJob
   - Trigger при critical CPU/Memory thresholds

5. **Настройки в InstanceSettings**:
   - auto_provision_enabled
   - auto_provision_provider (hetzner)
   - auto_provision_api_key
   - auto_provision_server_type (default: cx22)
   - auto_provision_location (default: nbg1)
   - auto_provision_max_servers_per_day (default: 3)
   - auto_provision_cooldown_minutes (default: 60)

---

## Verification Plan

### Phase 1 Testing
```bash
# 1. Create PostgreSQL in environment
# 2. Create application in same environment
# 3. Check env vars contain DATABASE_URL
./vendor/bin/pest tests/Unit/AutoInjectDatabaseUrlTest.php
```

### Phase 2 Testing
```bash
# 1. Deploy app with Dockerfile: EXPOSE 3000
# 2. Verify ports_exposes = "3000"
# 3. Deploy Nixpacks app without explicit port
# 4. Verify auto-detection from nixpacks plan
```

### Phase 3 Testing
```bash
# 1. Go to /boarding as new user
# 2. Select localhost server
# 3. Enter public GitHub repo
# 4. Deploy → verify app created and running
```

### Build/Lint Verification
```bash
./vendor/bin/pint           # PHP formatting
./vendor/bin/phpstan analyse # Static analysis
npm run build               # Frontend build
docker exec saturn php artisan test # Feature tests
```

---

## Files Summary

| Priority | File | Action |
|----------|------|--------|
| P0 | `app/Models/Application.php` | Add `autoInjectDatabaseUrl()` |
| P0 | `app/Jobs/ApplicationDeploymentJob.php` | Call auto-inject, add port detection |
| P0 | `database/migrations/*` | Add `auto_inject_database_url` column |
| P0 | `app/Models/ResourceLink.php` | NEW - связи между ресурсами |
| P0 | `database/migrations/*_create_resource_links_table.php` | NEW - таблица связей |
| P0 | `app/Http/Controllers/Api/ResourceLinkController.php` | NEW - API для связей |
| P0 | `resources/js/components/features/canvas/ProjectCanvas.tsx` | Persist edges, API integration |
| P1 | `bootstrap/helpers/docker.php` | Improve port parsing |
| P1 | `routes/web.php` | Update boarding/deploy route |
| P1 | `app/Jobs/CheckServerResourcesJob.php` | NEW - resource monitoring |
| P2 | `resources/js/pages/Databases/*.tsx` | Add Internal URL display |
| P2 | `app/Actions/Server/AutoProvisionServer.php` | NEW - Hetzner integration |
