# Saturn Platform — Production Readiness Audit V6

**Date:** 2026-02-23
**Auditor:** Claude Opus 4.6 (automated deep analysis)
**Scope:** Full codebase analysis across 6 dimensions
**Previous audits:** V1-V5 (see MEMORY.md for history)

---

## Executive Summary

| Dimension | Score | Trend vs V5 | Key Risk |
|-----------|-------|-------------|----------|
| Security | 62/100 | -6 | SQL/Command injection in DatabaseMetrics (new feature) |
| Testing | 55/100 | -3 | 46/69 Jobs untested, 0% Stripe/payment coverage |
| DB & Performance | 65/100 | +3 | N+1 in ServerManagerJob, missing indexes on activity_log |
| Code Quality | 52/100 | -3 | 38 models with `$guarded=[]`, god classes unchanged |
| Resilience | 55/100 | +3 | Empty catch blocks, ray() in production paths |
| Infrastructure | 72/100 | +7 | CI test job disabled, APP_DEBUG=true in staging |

### Overall Score: 60/100 (unchanged from V5)

**Verdict: NOT READY for production.** 8 CRITICAL issues must be fixed first.

---

## CRITICAL Issues (Must Fix Before Production)

### CRIT-1: SQL/Command Injection in DatabaseMetrics CRUD Operations
**Severity:** CRITICAL | **Effort:** 4h | **Files:** 3

PostgresMetricsService, MysqlMetricsService, MongoMetricsService — методы `updateRow()`, `deleteRow()`, `createRow()`, `getData()` уязвимы к SQL injection через **имена колонок** (не валидируются) и **command injection** через `$user`, `$dbName`, `$password` (не используют `escapeshellarg()`).

**Файлы:**
- `app/Services/DatabaseMetrics/PostgresMetricsService.php:491-570` — `$column` подставляется напрямую в SQL
- `app/Services/DatabaseMetrics/MysqlMetricsService.php:440-540` — пароль в shell-команде без экранирования
- `app/Services/DatabaseMetrics/MongoMetricsService.php` — аналогичные проблемы

**Пример атаки:**
```
// Column name injection:
POST /database-metrics/{uuid}/update-row
{ "updates": { "id\" = 1; DROP TABLE users; --": "value" } }
```

**Fix:** Валидировать column names через whitelist из `getColumns()`, использовать `escapeshellarg()` для всех shell-параметров.

---

### CRIT-2: 38 Models with `$guarded = []`
**Severity:** CRITICAL | **Effort:** 8h | **Files:** 38

38 из ~60 моделей используют `$guarded = []` вместо explicit `$fillable`. Это позволяет массовое назначение любых полей, включая `team_id`, `is_superadmin`, `suspended_at`.

**Наиболее опасные модели:**
- `Server.php` — `$guarded = []` позволяет изменить `team_id` (IDOR)
- `Application.php` — `$guarded = []` позволяет изменить deployment-параметры
- `Team.php` — `$guarded = []`
- `Subscription.php` — `$guarded = []` позволяет изменить платёжный статус
- `User.php` — имеет `$fillable` (БЕЗОПАСНО, `$guarded=[]` в комментарии)

**Fix:** Заменить `$guarded = []` на explicit `$fillable` во всех 37 реальных моделях. Учесть: поля `status`, `last_online_at` ДОЛЖНЫ быть в `$fillable` (см. MEMORY.md о silent update failure).

---

### CRIT-3: CI/CD Test Job Disabled
**Severity:** CRITICAL | **Effort:** 2h | **Files:** 1

В `.github/workflows/deploy-to-vps.yml` test job закомментирован или отключён. Код деплоится на VPS без запуска тестов и статического анализа.

**Fix:** Включить test job в CI pipeline. Добавить `phpstan`, `pint --test`, `pest tests/Unit` как обязательные шаги перед deploy.

---

### CRIT-4: 46 Jobs Without Any Tests (67% untested)
**Severity:** CRITICAL | **Effort:** 40h | **Files:** 46

Из 69 Job-классов **46 не имеют ни одного теста**. Среди критических непокрытых:

| Job | Критичность | Строк |
|-----|-------------|-------|
| `StripeProcessJob` | P0 — платежи | 320 |
| `SubscriptionInvoiceFailedJob` | P0 — платежи | ~80 |
| `VerifyStripeSubscriptionStatusJob` | P0 — платежи | ~60 |
| `ServerManagerJob` | P0 — оркестрация | ~200 |
| `AutoProvisionServerJob` | P1 — Hetzner API | ~300 |
| `ValidateAndInstallServerJob` | P1 — установка | ~150 |
| `SendTeamWebhookJob` | P1 — интеграции | ~80 |
| `GithubAppPermissionJob` | P2 — GitHub | ~60 |

**Fix:** Начать с P0 (Stripe jobs + ServerManagerJob), затем P1. Минимум: тест на happy path + error handling.

---

### CRIT-5: APP_DEBUG=true in Staging
**Severity:** CRITICAL | **Effort:** 15min | **Files:** 1

`deploy/environments/staging/.env.example` имеет `APP_DEBUG=true`. В staging Laravel выводит полные стек-трейсы с переменными окружения на страницах ошибок.

**Fix:** `APP_DEBUG=false` в staging и production.

---

### CRIT-6: ray() Calls in Production Code
**Severity:** CRITICAL | **Effort:** 2h | **Files:** ~5 production-critical

622 вхождения `ray()` в 207 файлах. Большинство — в dev-only коде, но некоторые в production paths:
- `app/Jobs/StripeProcessJob.php` — платёжная логика
- `app/Jobs/ApplicationDeploymentJob.php` — деплой
- `app/Http/Controllers/Webhook/Github.php` — вебхуки
- `app/Services/AI/Chat/CommandExecutor.php` — AI commands

`ray()` в production: утечка данных через Laravel Ray сервер, замедление, потенциальные ошибки если ray-сервер недоступен.

**Fix:** Заменить `ray()` → `Log::debug()` в production-critical файлах. Убедиться что `laravel-ray` в `require-dev` (уже сделано).

---

### CRIT-7: Unescaped Shell Parameters in 12+ DatabaseMetrics Methods
**Severity:** CRITICAL | **Effort:** 3h | **Files:** 4

Все методы `PostgresMetricsService`, `MysqlMetricsService`, `MongoMetricsService`, `RedisMetricsService` строят shell-команды через string interpolation без `escapeshellarg()`:

```php
// PostgresMetricsService:491
$containerName = $database->uuid;        // НЕ escaped
$user = $database->postgres_user;        // НЕ escaped — ПОЛЬЗОВАТЕЛЬСКИЙ ВВОД
$dbName = $database->postgres_db;        // НЕ escaped — ПОЛЬЗОВАТЕЛЬСКИЙ ВВОД
$command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c \"{$query}\"";
```

Только `collectMetrics()` (строка 19) использует `escapeshellarg()`. Остальные 12+ методов — нет.

**Fix:** `escapeshellarg()` для ВСЕХ переменных, подставляемых в shell-команды.

---

### CRIT-8: Column Names Not Validated Against Schema Whitelist
**Severity:** CRITICAL | **Effort:** 2h | **Files:** 3

В `updateRow()`, `deleteRow()`, `createRow()` всех MetricsService — **column names из user input подставляются напрямую в SQL-запросы** без проверки по whitelist из `getColumns()`.

```php
foreach ($updates as $column => $value) {
    $setClauses[] = "\"{$column}\" = '{$escapedValue}'";  // column НЕ валидируется!
}
```

**Fix:** Получить список колонок через `getColumns()`, валидировать что каждый key в `$updates`/`$primaryKey` является допустимым именем колонки.

---

## HIGH Issues

### H-1: Missing Database Indexes on activity_log
**Effort:** 1h

`activity_log` — потенциально одна из самых больших таблиц. Отсутствуют индексы:
- `(causer_type, causer_id)` — используется в `TeamController:395`
- `(causer_id, created_at DESC)` — сортировка + фильтрация
- LIKE `%search%` по `description` — full table scan

**Fix:** Создать миграцию с композитными индексами. Рассмотреть pg_trgm для текстового поиска.

---

### H-2: N+1 in ServerManagerJob::getServers()
**Effort:** 30min

```php
// ServerManagerJob.php:78
$allServers = Server::where('ip', '!=', '1.2.3.4'); // без with('settings', 'team')!
// Затем: $server->settings, $server->team->subscription — lazy loads
```

**Fix:** `Server::where(...)->with(['settings', 'team.subscription'])->get()`

---

### H-3: N+1 in CleanupStaleMultiplexedConnections
**Effort:** 20min

```php
foreach ($muxFiles as $muxFile) {
    $server = Server::where('uuid', $serverUuid)->first(); // N+1!
}
```

**Fix:** Предзагрузить: `Server::whereIn('uuid', $uuids)->get()->keyBy('uuid')`

---

### H-4: Empty Catch Blocks in Critical Paths
**Effort:** 2h | **Files:** 6+

| File | Line | Impact |
|------|------|--------|
| `PushServerUpdateJob.php` | 202 | Молчаливый сбой обновления статуса preview-приложений |
| `SubscriptionInvoiceFailedJob.php` | 71 | Ложные email "оплата провалилась" при сбое Stripe API |
| `Application.php` | 399-425 | 4 пустых catch — прокси может рассинхронизироваться |
| `ApplicationDeploymentQueue.php` | 315 | WebSocket broadcast сбой без логирования |

**Fix:** Заменить пустые `catch` на `Log::warning()` или `report($e)`.

---

### H-5: God Classes Unchanged
**Effort:** 40h+ (долгосрочно)

| Class | Lines | Problem |
|-------|-------|---------|
| `CommandExecutor.php` | 2692 | AI service, нарушает SRP |
| `Application.php` | 2504 | God Model с деплой-логикой |
| `Service.php` | 1842 | Аналогично Application |
| `Server.php` | 1600 | SSH + status + proxy в одном |
| `ServiceComposeParser.php` | 1374 | Монолитный парсер |
| `ApplicationComposeParser.php` | 1371 | Монолитный парсер |
| `ApplicationDeploymentJob.php` | 1310 | Деплой-оркестрация |

**Recommendation:** Не блокирует production, но усложняет поддержку. Приоритизировать рефакторинг `Application.php` — извлечь deploy-логику, Docker-логику, status-вычисления в отдельные сервисы.

---

### H-6: Docker Image Pinning Without Digest
**Effort:** 1h

Все базовые Docker-образы используют tags без `@sha256:...` digest. Supply chain attack vector.

**Fix:** Добавить digest для production Dockerfile: `FROM serversideup/php:8.4-fpm-nginx-alpine@sha256:abc...`

---

### H-7: Redis Password Exposed in Health Check
**Effort:** 15min

```yaml
# docker-compose.env.yml:110
test: redis-cli -a ${REDIS_PASSWORD} ping
```

Пароль виден в `docker inspect`.

**Fix:** `redis-cli --no-auth-warning -a ${REDIS_PASSWORD} ping` или использовать REDISCLI_AUTH env var.

---

### H-8: Frontend Test Coverage ~7%
**Effort:** 40h+

137 React pages, ~26 файлов с тестами. Критические пробелы:
- Deployment UI — 0 тестов
- Server management — 0 тестов
- Payment/billing pages — 0 тестов

---

### H-9: Code Duplication in GitController
**Effort:** 1h

`fetchGitHubBranches()`, `fetchGitLabBranches()`, `fetchBitbucketBranches()` содержат дублированную логику сортировки (~20 строк x3).

**Fix:** Извлечь `sortBranchesByDefault()` приватный метод.

---

## MEDIUM Issues

### M-1: `$guarded = []` in 37 Models (Details)

Complete list of models needing `$fillable`:
`Alert`, `AlertHistory`, `Application`, `ApplicationPreview`, `ApplicationSetting`, `ApplicationTemplate`, `AutoProvisioningEvent`, `CloudProviderToken`, `DeploymentApproval`, `DockerCleanupExecution`, `Environment`, `EnvironmentMigration`, `GithubApp`, `InstanceSettings`, `LocalFileVolume`, `LocalPersistentVolume`, `MigrationHistory`, `ProjectSetting`, `RedirectRule`, `ResourceTransfer`, `S3Storage`, `ScheduledDatabaseBackup`, `ScheduledDatabaseBackupExecution`, `ScheduledTask`, `ScheduledTaskExecution`, `Server`, `ServerSetting`, `Service`, `ServiceApplication`, `ServiceDatabase`, `SharedEnvironmentVariable`, `StandaloneDocker`, `Subscription`, `SwarmDocker`, `Tag`, `Team`, `TeamResourceTransfer`

### M-2: SOKETI_DEBUG=true in dev .env
### M-3: Schema/table names not fully escaped in getColumns() SQL
### M-4: Root Dockerfile uses chmod 777
### M-5: Missing `memswap_limit` in docker-compose
### M-6: Email hardcoded in Caddyfile (admin@saturn.ac)
### M-7: saturn-platform:latest fallback tag in docker-compose.env.yml
### M-8: No off-site backup encryption (carried from V1)
### M-9: Missing circuit breaker patterns for external services
### M-10: Duplicated CNAME logic in CloudflareProtectionService
### M-11: SSRF in GithubController PATCH — `api_url` update without SSRF validation (`GithubController.php:611`)
### M-12: `$exception->getMessage()` shown to unauthenticated users in `500.blade.php:11`
### M-13: `SESSION_SECURE_COOKIE` not set in `.env.example` — cookies sent over HTTP
### M-14: `/api/feedback` public endpoint without auth — Discord spam vector (`routes/api.php:56`)
### M-15: CSP contains `'unsafe-inline'` for `style-src` (`AddCspHeaders.php:39`)
### M-16: `StrictHostKeyChecking=no` in CI/CD SSH commands (`deploy-to-vps.yml:225,237,241,252`)
### M-17: `CREATE INDEX` without `CONCURRENTLY` blocks table (`2026_02_23_100000` migration)
### M-18: `DatabaseImportJob` (timeout=7200) in `high` queue — blocks priority ops for 2 hours
### M-19: Queue health in Admin dashboard always 0 (uses `DB::table('jobs')` with Redis queue)

---

## Additional HIGH Issues (from final agent pass)

### H-10: SSRF in testS3Connection — user endpoint without validation
**Effort:** 30min | **File:** `DatabaseMetricsController.php:483-513`

```php
$request->validate(['endpoint' => 'nullable|string']); // no SSRF check!
$config['endpoint'] = $endpoint; // Direct to S3 client
```

User can pass `http://169.254.169.254/latest/meta-data/` to access AWS metadata.

**Fix:** Add `validateWebhookUrl()` or IP blacklist for private ranges.

---

### H-11: ray() leaks CA certificates in Server.php
**Effort:** 15min | **File:** `Server.php:1567,1575`

```php
ray('CA certificate generated', $caCertificate); // no isDev() guard!
```

Also in `HetznerService.php:132,139` — server creation params leaked.

**Fix:** Wrap in `if (isDev()) {}` or replace with `Log::debug()`.

---

### H-12: Jobs without $tries/$timeout — can hang indefinitely
**Effort:** 2h | **Files:** 10+

| Job | $tries | $timeout | failed() |
|-----|--------|----------|----------|
| `CleanupStaleMultiplexedConnections` | NONE | NONE | NONE |
| `VolumeCloneJob` | NONE | NONE | NONE |
| `CleanupHelperContainersJob` | NONE | NONE | NONE |
| `RegenerateSslCertJob` | NONE | NONE | NONE |
| `CheckForUpdatesJob` | NONE | NONE | NONE |
| `CheckAndStartSentinelJob` | NONE | NONE | NONE |
| `GithubAppPermissionJob` | 4 | NONE | NONE |
| `CheckTraefikVersionJob` | 3 | NONE | NONE |

**Fix:** Add `$tries = 3`, `$timeout = 120`, `failed()` callback to all.

---

## Detailed Scores

### Security: 60/100 (revised down from 62)

| Area | Status | Notes |
|------|--------|-------|
| SQL Injection | FAIL | DatabaseMetrics CRUD — column name injection |
| Command Injection | FAIL | 12+ methods without escapeshellarg |
| Mass Assignment | FAIL | 37 models with $guarded=[] |
| IDOR/Authorization | PASS | Policies + team scoping |
| SSRF | FAIL | testS3Connection + GithubController PATCH |
| XSS | WARN | 500.blade.php exception message exposure |
| CSRF | PASS | Laravel middleware |
| CORS | PASS | Hardened to APP_URL |
| Encryption | PASS | DB passwords encrypted |
| Session Security | WARN | SESSION_SECURE_COOKIE not in .env.example |

### Testing: 55/100

| Area | Coverage | Notes |
|------|----------|-------|
| Unit Tests | 326 files | Good coverage for models, actions |
| Feature Tests | 84 files | Good API coverage |
| Jobs | 33% (23/69) | P0 gap: Stripe jobs 0% |
| Controllers | ~60% | API controllers well-tested |
| Frontend | ~7% | 26 test files / 137 pages |
| Webhooks | ~10% | GitHub/GitLab/Bitbucket minimal |

### DB & Performance: 65/100

| Area | Status | Notes |
|------|--------|-------|
| N+1 Queries | WARN | ServerManagerJob, CleanupStaleMultiplexedConnections |
| Indexes | WARN | activity_log missing composites |
| Unbounded Queries | PASS | Most fixed in V3 audit |
| Caching | PASS | Permissions cached, team queries cached |
| Queue Config | PASS | retry_after=4200, proper backoff |
| Pagination | PASS | API uses pagination |

### Code Quality: 52/100

| Area | Status | Notes |
|------|--------|-------|
| God Classes | FAIL | 7 files > 1000 lines, top: 2692 |
| Mass Assignment | FAIL | 37 models |
| Dead Code | WARN | ray() 622 occurrences |
| Code Duplication | WARN | GitController, CloudflareService |
| Error Handling | WARN | Empty catch blocks in 6+ critical paths |
| Type Safety | PASS | PHPStan Level 5, 0 errors |

### Resilience: 55/100

| Area | Status | Notes |
|------|--------|-------|
| HTTP Timeouts | PASS | Global 30s timeout in AppServiceProvider |
| Job Safety | PASS (mostly) | $tries/$timeout on critical jobs |
| failed() Callbacks | WARN | Some new jobs missing |
| Circuit Breakers | FAIL | None exist |
| Graceful Degradation | WARN | Redis down = partial failure |
| Rate Limiting | PASS | API throttle middleware |

### Infrastructure: 72/100

| Area | Status | Notes |
|------|--------|-------|
| Docker Security | GOOD | Non-root, multi-stage, health checks |
| Resource Limits | GOOD | Memory + CPU limits set |
| Environment Isolation | GOOD | Separate DB/Redis per env |
| CI/CD | FAIL | Test job disabled |
| TLS/Proxy | GOOD | Traefik with Let's Encrypt |
| Backups | WARN | Exist but no encryption |
| Monitoring | GOOD | Sentry + audit logs |
| Logging | GOOD | Structured, rotation configured |

---

## Priority Fix Plan

### Week 1 (P0 — Security Blockers)
1. [ ] CRIT-1 + CRIT-7 + CRIT-8: Fix DatabaseMetrics injection (4h)
2. [ ] CRIT-2: Replace $guarded=[] with $fillable in 37 models (8h)
3. [ ] CRIT-5: APP_DEBUG=false in staging (15min)
4. [ ] CRIT-6: Replace ray() in production paths (2h)
5. [ ] CRIT-3: Enable CI test job (2h)
6. [ ] H-4: Fix empty catch blocks (2h)
7. [ ] H-7: Fix Redis password exposure (15min)

### Week 2 (P1 — Stability)
8. [ ] CRIT-4: Write tests for Stripe jobs (16h)
9. [ ] H-1: Add activity_log indexes (1h)
10. [ ] H-2 + H-3: Fix N+1 queries (1h)
11. [ ] H-6: Pin Docker images with digest (1h)
12. [ ] H-9: Deduplicate GitController (1h)

### Week 3-4 (P2 — Quality)
13. [ ] Write tests for ServerManagerJob, AutoProvisionServerJob (8h)
14. [ ] Frontend test coverage for critical pages (16h)
15. [ ] H-5: Begin Application.php refactoring (16h)
16. [ ] M-9: Implement circuit breaker for external services (8h)

### Long-term (P3)
17. [ ] Off-site backup encryption
18. [ ] Full frontend test coverage
19. [ ] Complete god class refactoring
20. [ ] APM integration (New Relic / Datadog)

---

## Changes Since V5

### Fixed (from V5 recommendations):
- PermissionSets integrated into authorization (commit 736584b)
- IDOR fixes in CloudProviderToken/CloudInitScript policies (commit 037893e)
- PHPStan 0 errors maintained (commit 5bbd482)
- FormRequest extraction completed (31 classes)

### New issues (not in V5):
- DatabaseMetrics CRUD operations added — 3 new services with injection vulnerabilities
- CI test job found disabled (may have been disabled recently)
- 38 models still use $guarded=[] (was flagged but not fixed)

### Score comparison:

| Dimension | V3 (Feb 20) | V5 (Feb 23) | V6 (Feb 23) | Delta |
|-----------|-------------|-------------|-------------|-------|
| Security | 82 | 68 | 62 | -6 (DatabaseMetrics) |
| Testing | 62 | 58 | 55 | -3 (more jobs added) |
| DB & Perf | 68 | 62 | 65 | +3 (some N+1 fixed) |
| Code Quality | 72 | 55 | 52 | -3 (more god classes) |
| Resilience | 45 | 52 | 55 | +3 (timeouts added) |
| Infra | 82* | 65* | 72 | +7 (env isolation) |
| **Overall** | **71** | **60** | **60** | **0** |

*V3/V5 measured "Observability" + "API" instead of "Infrastructure" — not directly comparable.

---

## Conclusion

Saturn Platform at 60% readiness — **the same as V5**. The PermissionSets and IDOR fixes improved authorization, but new DatabaseMetrics features introduced critical injection vulnerabilities that offset the gains. The CI test job being disabled is a process failure that enables regressions.

**Minimum viable production (for internal company use):**
Fix CRIT-1 through CRIT-8 → raises score to ~72%. Acceptable for internal deployment with limited user base and trusted operators.

**Full production readiness:**
Fix all HIGH issues → raises score to ~82%. Required before any external or multi-tenant deployment.
