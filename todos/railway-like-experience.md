# Railway-Like Experience для Saturn

**Status:** Planning
**Priority:** P1
**Created:** 2026-01-22
**Context:** Внутренний проект компании, деплой на master server

---

## Цель

Сделать деплой приложений таким же простым как в Railway:
```
Указал репозиторий → Нажал Deploy → Приложение работает
```

Без необходимости:
- Настраивать порты вручную
- Копировать connection string для БД
- Понимать Docker и networking

---

## Текущее состояние vs Railway

| Функция | Railway | Saturn сейчас | Что нужно |
|---------|---------|---------------|-----------|
| Auto-detect язык | ✅ | ✅ Nixpacks | — |
| Auto-detect порт | ✅ | ⚠️ Нужно указать PORT env | Авто-определение из EXPOSE |
| Auto-generate домен | ✅ | ✅ wildcard/sslip.io | — |
| GitHub webhooks | ✅ | ✅ | — |
| PR Preview | ✅ | ✅ | — |
| Managed DB + auto inject | ✅ | ❌ Только создание | **Нужно реализовать** |
| Quick Deploy (1 click) | ✅ | 🚧 UI есть, логика нет | **Доделать** |
| Service discovery | ✅ | ⚠️ Нужно знать container name | **Улучшить UX** |
| Мониторинг ресурсов | ✅ | ✅ Sentinel | — |
| Auto-scaling | ✅ | ❌ | Отложено (Phase 2) |

---

## Phase 1: Базовый Railway-like опыт

### 1.1 Auto-inject DATABASE_URL [P0]

**Проблема:** Создаём PostgreSQL, но приложение не знает как к нему подключиться.

**Решение:**
```
При создании приложения:
1. Если в том же Environment есть БД (PostgreSQL/MySQL/etc)
2. Автоматически добавить DATABASE_URL в env приложения
3. Формат: postgresql://user:pass@{container_name}:5432/dbname
```

**Файлы:**
- [ ] `app/Models/Application.php` - метод `autoInjectDatabaseUrl()`
- [ ] `app/Jobs/ApplicationDeploymentJob.php` - вызов при deploy
- [ ] `app/Livewire/Project/Database/CreateDatabase*.php` - триггер при создании БД
- [ ] Migration: добавить `auto_inject_database_url` boolean в applications

**Код:**
```php
// В Application.php
public function autoInjectDatabaseUrl(): void
{
    // Найти БД в том же environment
    $databases = $this->environment->databases;

    foreach ($databases as $db) {
        if ($db instanceof StandalonePostgresql) {
            $url = "postgresql://{$db->postgres_user}:{$db->postgres_password}@{$db->uuid}:5432/{$db->postgres_db}";

            // Добавить если нет
            $this->environment_variables()->updateOrCreate(
                ['key' => 'DATABASE_URL'],
                ['value' => $url, 'is_build_time' => false]
            );
        }
    }
}
```

---

### 1.2 Auto-detect Port из Dockerfile/Nixpacks [P1]

**Проблема:** Пользователь должен вручную указывать `ports_exposes`.

**Решение:**
```
После генерации Nixpacks plan или парсинга Dockerfile:
1. Найти EXPOSE инструкцию
2. Автоматически установить ports_exposes
3. Показать в UI: "Detected port: 3000"
```

**Файлы:**
- [ ] `app/Jobs/ApplicationDeploymentJob.php` - парсинг EXPOSE после nixpacks plan
- [ ] `bootstrap/helpers/docker.php` - улучшить `extractExposedPort()`
- [ ] Добавить логирование: "Auto-detected port: {port}"

**Код:**
```php
// В ApplicationDeploymentJob после generate_nixpacks_confs()
private function autoDetectAndSetPort(): void
{
    // Читаем сгенерированный Dockerfile
    $dockerfile = $this->execute_remote_command([
        executeInDocker($this->deployment_uuid, "cat Dockerfile"),
        'save' => 'dockerfile_content',
        'hidden' => true
    ]);

    $port = extractExposedPort($dockerfile);

    if ($port && empty($this->application->ports_exposes)) {
        $this->application->ports_exposes = (string) $port;
        $this->application->save();
        $this->application_deployment_queue->addLogEntry("Auto-detected port: {$port}");
    }
}
```

---

### 1.3 Quick Deploy Flow [P1]

**Проблема:** Boarding flow имеет UI, но логика не завершена.

**Текущее состояние:**
- `resources/js/pages/Boarding/Index.tsx` - UI готов
- Логика создания сервера: `// TODO: Create server via API`

**Решение для внутреннего проекта:**

Упростить flow - использовать localhost (master server):
```
1. Welcome → Skip server setup (use localhost)
2. Connect Git → GitHub App или public repo URL
3. Deploy → Создать приложение + запустить deployment
4. Complete → Показать URL приложения
```

**Файлы:**
- [ ] `routes/web.php` - роут `boarding.quick-deploy`
- [ ] `resources/js/pages/Boarding/Index.tsx` - упрощённый flow
- [ ] Убрать шаги "Add Server" для внутренних пользователей
- [ ] Добавить опцию "Use main server" (localhost)

**Backend изменения:**
```php
// routes/web.php - новый роут для quick deploy
Route::post('/boarding/quick-deploy', function (Request $request) {
    $validated = $request->validate([
        'git_repository' => 'required|string',
        'git_branch' => 'nullable|string',
        'name' => 'required|string',
    ]);

    // Использовать localhost server (ID = 0)
    $server = Server::find(0);
    $destination = $server->standaloneDockers()->first();

    // Создать проект/environment если нет
    $team = currentTeam();
    $project = Project::firstOrCreate(
        ['team_id' => $team->id],
        ['name' => 'Default Project']
    );
    $environment = $project->environments()->firstOrCreate(
        ['name' => 'production']
    );

    // Создать приложение
    $application = Application::create([...]);

    // Auto-generate domain
    $application->fqdn = generateUrl($server, $application->uuid);
    $application->save();

    // Auto-inject DATABASE_URL если есть БД
    $application->autoInjectDatabaseUrl();

    // Запустить деплой
    queue_application_deployment($application, Str::uuid());

    return redirect()->route('applications.show', $application->uuid);
})->name('boarding.quick-deploy');
```

---

### 1.4 Service Discovery UX [P2]

**Проблема:** Чтобы backend подключился к БД, нужно знать имя контейнера (UUID).

**Текущее:** Пользователь должен найти UUID базы данных и вручную использовать его.

**Решение - показывать "Internal URL":**

В UI базы данных:
```
Internal URL: postgresql://postgres:***@hkw8s4g0cw...@:5432/main
              ↑ кликабельно, копируется в буфер

Приложения в этом Environment могут подключаться по этому адресу.
```

**Файлы:**
- [ ] `resources/js/pages/Databases/Show.tsx` - показать Internal URL
- [ ] `app/Models/StandalonePostgresql.php` - метод `internalUrl()`
- [ ] То же для MySQL, MongoDB, Redis и т.д.

---

## Phase 2: Умное Auto-Provisioning VPS

### Контекст

> На начальном этапе нам не нужны доп VPS, так как сам мастер сервер имеет большие мощности и может уже удержать 10-15 проектов.
>
> Значит надо сделать умное авто: когда следующий деплой уже будет убивать производительность → создаётся VPS.
> Но оставить возможность потом самим тоже вручную делать VPS для исключительных случаев.

### 2.1 Resource Threshold Monitoring [P1]

**Идея:** Отслеживать ресурсы master server и предупреждать/действовать.

**Пороговые значения:**
```
CPU:    Warning 70%, Critical 85%
Memory: Warning 75%, Critical 90%
Disk:   Warning 80%, Critical 95%
```

**Файлы:**
- [ ] `app/Jobs/CheckServerResourcesJob.php` - новый job
- [ ] Migration: добавить пороги в `server_settings`
- [ ] `config/constants.php` - дефолтные значения

**Логика:**
```php
class CheckServerResourcesJob implements ShouldQueue
{
    public function handle(): void
    {
        $server = Server::find(0); // localhost
        $metrics = $server->getMetrics();

        $settings = InstanceSettings::get();

        if ($metrics['cpu'] > $settings->auto_provision_cpu_threshold) {
            // Отправить уведомление
            // Если auto_provision_enabled - создать VPS
        }

        if ($metrics['memory'] > $settings->auto_provision_memory_threshold) {
            // ...
        }
    }
}
```

---

### 2.2 Auto-Provision Decision Logic [P2]

**Когда создавать новый VPS:**

```
IF (
    cpu_usage > 85% sustained 5 min
    OR memory_usage > 90%
    OR disk_usage > 95%
    OR pending_deployments > 5
)
AND auto_provision_enabled = true
AND cloud_provider_configured = true
THEN
    create_new_vps()
    notify_admin("New VPS created due to resource constraints")
```

**Файлы:**
- [ ] `app/Actions/Server/AutoProvisionServer.php`
- [ ] `app/Jobs/AutoProvisionServerJob.php`
- [ ] Integration с Hetzner/DO API (см. auto-provisioning-architecture.md)

---

### 2.3 Manual VPS Creation [P2]

**Оставить возможность вручную:**
- Страница "Servers" → "Add Server" → ручная настройка
- Или: "Add Server" → "Auto-provision from Hetzner" → выбор размера

**Файлы:**
- [ ] `resources/js/pages/Servers/Create.tsx` - два режима
- [ ] `app/Http/Controllers/Api/ServerController.php` - endpoint для auto-provision

---

### 2.4 Load Balancing между серверами [P3]

**Когда есть несколько серверов:**
```
При создании приложения:
1. Проверить ресурсы всех серверов
2. Выбрать наименее загруженный
3. Или спросить пользователя
```

**Файлы:**
- [ ] `app/Services/ServerSelector.php` - логика выбора сервера
- [ ] `app/Models/Server.php` - метод `getLoadScore()`

---

## Phase 3: Polish (после Phase 1-2)

- [ ] 3.1 Красивый onboarding wizard с анимациями
- [ ] 3.2 "Import from Railway/Heroku" - миграция проектов
- [ ] 3.3 Cost estimation перед деплоем
- [ ] 3.4 Deployment previews в PR комментариях
- [ ] 3.5 Slack/Discord notifications для деплоев

---

## Приоритеты реализации

```
Week 1:
  [P0] 1.1 Auto-inject DATABASE_URL
  [P1] 1.3 Quick Deploy Flow (упрощённый)

Week 2:
  [P1] 1.2 Auto-detect Port
  [P2] 1.4 Service Discovery UX

Week 3-4:
  [P1] 2.1 Resource Threshold Monitoring
  [P2] 2.2 Auto-Provision Decision Logic

Later:
  [P2] 2.3 Manual VPS Creation с cloud API
  [P3] 2.4 Load Balancing
  Phase 3 items
```

---

## Связанные файлы

| Файл | Что менять |
|------|-----------|
| `app/Models/Application.php` | autoInjectDatabaseUrl(), autoDetectPort() |
| `app/Jobs/ApplicationDeploymentJob.php` | Вызов auto-inject, auto-detect |
| `routes/web.php` | boarding.quick-deploy route |
| `resources/js/pages/Boarding/Index.tsx` | Упрощённый flow |
| `app/Jobs/CheckServerResourcesJob.php` | Новый job для мониторинга |
| `app/Actions/Server/AutoProvisionServer.php` | Создание VPS через API |

---

## Исправления к предыдущему анализу

| Моё утверждение | Реальность |
|-----------------|------------|
| "Managed DB одним кликом" ✅ | ⚠️ Создание работает, но connection string НЕ инжектится автоматически |
| "Zero-config networking" ✅ | ✅ Верно, сети создаются автоматически |
| "Quick Deploy" ✅ | 🚧 UI есть, логика в TODO |
| "Нет автомасштабирования" ❌ | ✅ Верно, нет |
| "Auto-detect языка" ✅ | ✅ Верно через Nixpacks |
| "Нужно указывать порт" | ✅ Верно, ports_exposes обязателен |
