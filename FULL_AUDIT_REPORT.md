# Saturn Platform - Full Audit Report

**Дата аудита**: 2026-02-04
**Версия**: 2.0 (Comprehensive)
**Статус**: В ПРОЦЕССЕ ИСПРАВЛЕНИЯ

---

## EXECUTIVE SUMMARY

| Категория | Критических | Высоких | Средних | Низких |
|-----------|-------------|---------|---------|--------|
| Безопасность | 38 | 1 | 0 | 0 |
| Frontend-Backend связь | 3 | 14 | 5 | 0 |
| Незавершённый функционал | 2 | 17 | 18 | 5 |
| Архитектурные проблемы | 4 | 8 | 6 | 0 |
| **ИТОГО** | **47** | **40** | **29** | **5** |

---

## ЧАСТЬ 1: БЕЗОПАСНОСТЬ

### 1.1 КРИТИЧЕСКОЕ: Mass Assignment (38 моделей)

**Проблема**: `protected $guarded = []` позволяет массово назначать любые поля включая критические.

**Риск**: Privilege escalation, несанкционированное изменение данных.

**Затронутые модели**:
```
КРИТИЧЕСКИЕ (могут привести к эскалации привилегий):
- app/Models/Application.php
- app/Models/Server.php
- app/Models/Service.php
- app/Models/Team.php
- app/Models/CloudProviderToken.php

ВЫСОКИЕ (могут нарушить целостность данных):
- app/Models/Subscription.php
- app/Models/Environment.php
- app/Models/DeploymentApproval.php
- app/Models/Alert.php
- app/Models/AlertHistory.php
- app/Models/S3Storage.php
- app/Models/GithubApp.php
- app/Models/ServerSetting.php
- app/Models/ApplicationSetting.php
- app/Models/ScheduledTask.php
- app/Models/ScheduledDatabaseBackup.php
- app/Models/ScheduledDatabaseBackupExecution.php

СРЕДНИЕ:
- app/Models/ServiceDatabase.php
- app/Models/ServiceApplication.php
- app/Models/LocalPersistentVolume.php
- app/Models/LocalFileVolume.php
- app/Models/DockerCleanupExecution.php
- app/Models/InstanceSettings.php
- app/Models/Tag.php
- app/Models/SharedEnvironmentVariable.php
- app/Models/AutoProvisioningEvent.php
- app/Models/MigrationHistory.php
- app/Models/ApplicationTemplate.php
- app/Models/ApplicationPreview.php
... и ещё 10+ моделей
```

**Статус**: [ ] НЕ ИСПРАВЛЕНО

---

### 1.2 ВЫСОКОЕ: SQL Injection в INTERVAL clause

**Файл**: `app/Jobs/CleanupSleepingPreviewsJob.php:39,58`

```php
// УЯЗВИМО - column name в INTERVAL без параметризации
->whereRaw('last_activity_at < NOW() - INTERVAL auto_sleep_after_minutes MINUTE')
->whereRaw('created_at < NOW() - INTERVAL auto_delete_after_days DAY')
```

**Риск**: Возможен UNION-based SQL injection при определённых условиях.

**Статус**: [ ] НЕ ИСПРАВЛЕНО

---

### 1.3 БЕЗОПАСНО (проверено)

- [x] Command Injection - все escapeshellarg() на месте
- [x] File Operations - валидация magic bytes, path traversal
- [x] eval/assert/unserialize - не используются
- [x] Hardcoded secrets - не найдены
- [x] Race conditions - lockForUpdate() используется

---

## ЧАСТЬ 2: FRONTEND-BACKEND СВЯЗНОСТЬ

### 2.1 КРИТИЧЕСКОЕ: Несуществующие routes

Frontend обращается к эндпоинтам, которых НЕТ в routes:

| Hook | Endpoint | Статус |
|------|----------|--------|
| `useGitBranches.ts` | `/web-api/git/branches` | ❌ НЕ СУЩЕСТВУЕТ |
| `useAiAnalytics.ts` | `/web-api/ai-analytics/*` (4 endpoints) | ❌ НЕ СУЩЕСТВУЕТ |
| `useAiChat.ts` | `/web-api/ai-chat/*` (5+ endpoints) | ❌ НЕ СУЩЕСТВУЕТ |

### 2.2 КРИТИЧЕСКОЕ: Неправильные URL в hooks

| Файл | Строка | Текущий | Должен быть |
|------|--------|---------|-------------|
| `useProjects.ts` | 90 | `/projects` | `/api/v1/projects` |
| `useDeployments.ts` | 58 | `/applications/.../json` | `/api/v1/applications/...` |

### 2.3 ВЫСОКОЕ: TODO API endpoints (17 штук)

**Preview Deployments (6 endpoints)**:
- `GET /api/v1/applications/{uuid}/previews` → returns `[]`
- `POST /api/v1/applications/{uuid}/previews` → returns 501
- `GET /api/v1/applications/{uuid}/preview-settings` → returns mock data
- `PATCH /api/v1/applications/{uuid}/preview-settings` → returns 501
- `GET /api/v1/previews/{uuid}` → returns 404
- `POST /api/v1/previews/{uuid}/redeploy` → returns 501

**Billing (11 endpoints)** - все возвращают mock data:
- `GET /api/v1/billing/info`
- `GET /api/v1/billing/payment-methods`
- `POST /api/v1/billing/payment-methods`
- `DELETE /api/v1/billing/payment-methods/{id}`
- `POST /api/v1/billing/payment-methods/{id}/default`
- `GET /api/v1/billing/invoices`
- `GET /api/v1/billing/invoices/{id}/download`
- `GET /api/v1/billing/usage`
- `POST /api/v1/billing/subscription`
- `POST /api/v1/billing/subscription/cancel`
- `POST /api/v1/billing/subscription/resume`

### 2.4 НЕИСПОЛЬЗУЕМЫЕ API endpoints (12 групп)

Эндпоинты определены, но не используются на фронтенде:
- Hetzner интеграция (6 endpoints)
- Resource Links (4 endpoints)
- Check Approval (1 endpoint)
- Cloud Tokens validation (1 endpoint)

---

## ЧАСТЬ 3: НЕЗАВЕРШЁННЫЙ ФУНКЦИОНАЛ

### 3.1 КРИТИЧЕСКОЕ: Docker Swarm

**Файлы**:
- `app/Traits/Deployment/HandlesDockerComposeBuildpack.php:206`
- `app/Jobs/PushServerUpdateJob.php:123`

```php
if ($this->server->isSwarm()) {
    // TODO
}
```

**Статус**: Docker Swarm НЕ ПОДДЕРЖИВАЕТСЯ

### 3.2 ВЫСОКОЕ: TODO комментарии (18 штук)

| Файл | Строка | Описание |
|------|--------|----------|
| `routes/api.php` | 336-373 | Preview deployments (7 endpoints) |
| `routes/api.php` | 627-744 | Billing (11 endpoints) |
| `bootstrap/helpers/remoteProcess.php` | 172 | Exception handling для Sentry |
| `app/Models/Server.php` | 1071 | Proxy force_stop logic |
| `bootstrap/helpers/docker.php` | 180 | Refactor generateApplicationContainerName |
| `bootstrap/helpers/shared.php` | 871 | Move code to shared function |
| `database/migrations/...` | 26 | Remove unused git_full_url column |

### 3.3 СРЕДНЕЕ: Закомментированный код

- `app/Console/Kernel.php:45` - CleanupStaleMultiplexedConnections job
- `app/Console/Commands/Emails.php:186-201` - Backup emails
- `app/Console/Commands/Generate/Services.php:112` - generateServiceTemplatesRaw
- `app/Actions/Service/StartService.php:24` - cd command

### 3.4 СРЕДНЕЕ: Не реализованные UI features

- `resources/js/pages/Settings/Index.tsx:22` - Security tab (sessions, IP allowlist)
- `routes/web/settings.php:123,1162` - Team avatar
- `app/Http/Controllers/Inertia/ServerController.php:522` - Custom domain management

---

## ЧАСТЬ 4: АРХИТЕКТУРНЫЕ ПРОБЛЕМЫ

### 4.1 КРИТИЧЕСКОЕ: God Classes (>1000 строк)

| Файл | Строк | Методов | Нарушения SRP |
|------|-------|---------|---------------|
| `Application.php` | 2,293 | 106 | relations + config + status + storage + webhooks + deploy + health |
| `CommandExecutor.php` | 2,348 | 60 | 117 условных операторов, 11 команд |
| `Service.php` | 1,789 | 41 | config + status + images + storage + network |
| `Server.php` | 1,542 | 93 | SSH + Docker + monitoring + certs + proxy |
| `ApplicationDeploymentJob.php` | 1,244 | 29 | 18 traits, 50+ private свойств |

### 4.2 ВЫСОКОЕ: Дублирование кода

**Database Start Actions (8 файлов, ~2,419 строк, 80% дублирования)**:
```
app/Actions/Database/StartPostgresql.php - 344 строк
app/Actions/Database/StartMysql.php - 305 строк
app/Actions/Database/StartMongodb.php - 358 строк
app/Actions/Database/StartRedis.php - 334 строк
app/Actions/Database/StartMariadb.php - 302 строк
app/Actions/Database/StartDragonfly.php - 275 строк
app/Actions/Database/StartKeydb.php - 320 строк
app/Actions/Database/StartClickhouse.php - 181 строк
```

**Standalone Database Models (8 файлов, ~3,445 строк)**:
- deleteVolumes(), deleteConfigurations() 100% идентичны

**DatabaseMetrics Services (4 файла, ~2,271 строк)**:
- collectMetrics() дублирует логику

### 4.3 ВЫСОКОЕ: Цикломатическая сложность

| Метод | Условных операторов | Уровней вложенности |
|-------|---------------------|---------------------|
| `CommandExecutor::executeCommand()` | 117+ | 4-6 |
| `GetContainersStatus::handle()` | 166 строк | 3-4 |
| `ApplicationDeploymentJob::handle()` | 18 traits | N/A |

### 4.4 СРЕДНЕЕ: Hardcoded значения

| Файл | Строка | Значение | Должно быть |
|------|--------|----------|-------------|
| `Server.php` | 724,745 | `http://localhost:8888` | `config('services.sentinel.url')` |
| `Server.php` | 1006 | `127.0.0.1` | `config('app.dev_ip')` |

---

## ПЛАН ИСПРАВЛЕНИЙ

### Phase 1: КРИТИЧЕСКИЕ (Week 1)

1. [ ] **SEC-001**: Заменить $guarded = [] на $fillable в 38 моделях
2. [ ] **SEC-002**: Исправить SQL Injection в CleanupSleepingPreviewsJob.php
3. [ ] **FE-001**: Создать недостающие /web-api routes (git/branches, ai-analytics, ai-chat)
4. [ ] **FE-002**: Исправить URL в useProjects.ts и useDeployments.ts

### Phase 2: ВЫСОКИЕ (Week 2)

5. [ ] **FUNC-001**: Реализовать Preview Deployments API (6 endpoints)
6. [ ] **FUNC-002**: Реализовать Billing API (11 endpoints) или удалить UI
7. [ ] **ARCH-001**: Рефакторинг Start*.php Actions в Strategy паттерн
8. [ ] **ARCH-002**: Создать DatabaseModelTrait для общих методов

### Phase 3: СРЕДНИЕ (Week 3-4)

9. [ ] **ARCH-003**: Разделить Application.php на сервисы
10. [ ] **ARCH-004**: Разделить CommandExecutor.php на Command паттерн
11. [ ] **FUNC-003**: Реализовать Docker Swarm support
12. [ ] **FUNC-004**: Удалить неиспользуемую колонку git_full_url

---

## МЕТРИКИ

**Покрытие тестами**: Требуется проверка
**Технический долг**: ~8,000 строк дублированного кода
**Security debt**: 39 уязвимостей (38 mass assignment + 1 SQL injection)

---

## CHANGELOG

### 2026-02-04
- Создан полный отчёт аудита v2.0
- Выявлено 47 критических, 40 высоких, 29 средних проблем
