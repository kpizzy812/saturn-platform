# Saturn Platform — Production Readiness Audit V5

**Date:** 2026-02-23
**Branch:** `dev` (commit `326eeb0`)
**Methodology:** Deep static analysis across 5 dimensions by parallel agents
**Previous audits:** V1 (89%), V2 (92%), V3 (71%), V4 (security-focused)

---

## Executive Summary

| Dimension | Score | Prev (V3) | Delta | Critical | High | Medium |
|-----------|-------|-----------|-------|----------|------|--------|
| Security | 68 | 82 | -14 | 3 | 5 | 2 |
| Database & Performance | 62 | 68 | -6 | 2 | 14 | 14 |
| Code Quality & Architecture | 55 | 72 | -17 | 4 | 12 | 11 |
| Testing & Coverage | 58 | 62 | -4 | 4 | 15 | 5 |
| API & Frontend | 65 | 78 | -13 | 0 | 6 | 12 |
| **Resilience** | **52** | **45** | **+7** | 1 | 8 | 8 |
| **Overall** | **60** | **71** | **-11** | **14** | **60** | **52** |

> **Note:** Score decrease vs V3 reflects deeper analysis methodology (more files examined, new code paths audited), NOT regression. V4 security fixes remain in place. New features (AI Chat, Database Metrics, Status Pages, Deployment Approvals) introduced new surface area.

**Verdict: 60% Production Ready. 14 CRITICAL issues must be resolved before production.**

---

## CRITICAL Issues (14) — Must Fix Before Production

### SEC-CRIT-1: SQL Injection in PostgreSQL table search/filter
**File:** `app/Services/DatabaseMetrics/PostgresMetricsService.php:428-452`
**Impact:** Full database compromise via `$search` and `$filters` params injected into raw SQL
**Fix:** Whitelist-validate `tableName` via regex, parameterize `$search`, remove raw `$filters` from API

### SEC-CRIT-2: SQL Injection in MySQL/MariaDB table search/filter
**File:** `app/Services/DatabaseMetrics/MysqlMetricsService.php:376-413`
**Impact:** Same as CRIT-1 for MySQL databases
**Fix:** Same approach — regex validation + parameterized queries

### SEC-CRIT-3: Command/Query Injection in MongoDB search
**File:** `app/Services/DatabaseMetrics/MongoMetricsService.php:342-355`
**Impact:** Arbitrary JavaScript execution in mongosh via `$search` param
**Fix:** Validate `$collectionName` via regex, escape `$search` for MongoDB regex context

### PERF-CRIT-1: N+1 in ScheduledJobManager — `server()` without eager loading
**File:** `app/Jobs/ScheduledJobManager.php:118,154,186,209`
**Impact:** 200+ extra queries per scheduler run with 100 backups
**Fix:** `with(['database.destination.server', 'database.service.destination.server'])`

### PERF-CRIT-2: SSH in nested loop — Init::cleanupUnusedNetworkFromSaturnProxy
**File:** `app/Console/Commands/Init.php:200-234`
**Impact:** 50+ SSH connections at container startup with 10 servers x 5 networks
**Fix:** Batch SSH commands into single connection per server

### QUAL-CRIT-1: CommandExecutor.php — 2692-line god class
**File:** `app/Services/AI/Chat/CommandExecutor.php`
**Impact:** Untestable, unmaintainable AI command interpretation logic
**Fix:** Extract into per-resource command handlers

### QUAL-CRIT-2: Application.php — 2504-line god class (103 public methods)
**File:** `app/Models/Application.php`
**Impact:** 8+ responsibilities in single class, blocks testing and refactoring
**Fix:** Extract GitService, DockerComposeService, MetricsService, MonorepoService

### QUAL-CRIT-3: Service.php — 1841-line god class
**File:** `app/Models/Service.php`
**Impact:** Similar to Application.php
**Fix:** Extract compose parsing, configuration, metrics

### QUAL-CRIT-4: Laravel framework outdated — needs >=12.38.0
**File:** `composer.json:21`
**Impact:** symfony/console pin, missing security patches
**Fix:** `composer require laravel/framework:^12.38.0`, remove symfony/console pin

### TEST-CRIT-1: Stripe webhook controller — zero tests
**File:** `app/Http/Controllers/Webhook/Stripe.php`
**Impact:** Financial logic untested — subscription events, invoice failures, payment processing
**Fix:** Add Feature tests for all webhook event types

### TEST-CRIT-2: GitLab webhook controller — zero tests
**File:** `app/Http/Controllers/Webhook/Gitlab.php`
**Impact:** Deployment trigger path untested
**Fix:** Add tests for push, PR, tag events

### TEST-CRIT-3: Bitbucket webhook controller — zero tests
**File:** `app/Http/Controllers/Webhook/Bitbucket.php`
**Impact:** Deployment trigger path untested
**Fix:** Add tests for push, PR events

### TEST-CRIT-4: InstallDocker action — zero tests
**File:** `app/Actions/Server/InstallDocker.php`
**Impact:** Server provisioning critical path untested — high regression risk
**Fix:** Add unit tests with SSH mocking

### RESIL-CRIT-1: SubscriptionInvoiceFailedJob — no $tries, $timeout, $backoff
**File:** `app/Jobs/SubscriptionInvoiceFailedJob.php`
**Impact:** Financial job with zero resilience configuration
**Fix:** Add `$tries = 3`, `$timeout = 60`, `$backoff = [10, 30, 60]`, `failed()` method

---

## HIGH Issues (60)

### Security (5)

| ID | File | Line | Description |
|----|------|------|-------------|
| SEC-H-1 | `app/Models/User.php` | 782 | Weak OTP: `mt_rand()` instead of `random_int()` — predictable 2FA codes |
| SEC-H-2 | `app/Console/Commands/CleanupNames.php` | 194-203 | Command injection in pg_dump — params not escaped with `escapeshellarg()` |
| SEC-H-3 | `app/Models/User.php` | 527 | `sha1()` for email verification hash — cryptographically broken |
| SEC-H-4 | `app/Http/Controllers/Api/GithubController.php` | 255-256 | SSRF via `api_url`/`html_url` — no host allowlist validation |
| SEC-H-5 | `app/Http/Controllers/Inertia/DatabaseMetricsController.php` | 512-600 | No `tableName` validation — passes directly to SQL via services |

### Database & Performance (14)

| ID | File | Description |
|----|------|-------------|
| PERF-H-1 | `ScheduledJobManager.php:197,220` | N+1: `$server->team->subscription` without eager loading |
| PERF-H-2 | `Emails.php:91` | N+1: `$team->members` without `with()` in loop |
| PERF-H-3 | `DatabaseBackupsController.php:621` | N+1: `destination->server` accessed in loop |
| PERF-H-4 | `AlertEvaluationJob.php:42` | Unbounded: all alerts with `team.servers` loaded into memory |
| PERF-H-5 | `DeployController.php:69-70` | Unbounded: double full load + PHP sort instead of SQL ORDER BY |
| PERF-H-6 | `TeamController.php:638` | Unbounded: `limit(10000)` audit log export into memory |
| PERF-H-7 | `SyncStripeSubscriptionsJob.php:31` | Unbounded: all subscriptions + HTTP call per item in loop |
| PERF-H-8 | `DeleteResourceJob.php:244-257` | SSH in loop: 2 SSH calls per active deployment |
| PERF-H-9 | `ScheduledJobManager.php:107,145` | Memory: all backups/tasks loaded into memory via `get()` |
| PERF-H-10 | `server_health_checks` | Missing index: `(server_id, created_at)` — full table scan on metrics |
| PERF-H-11 | `User.php:228-246` | No `DB::transaction()` on cascading team deletion |
| PERF-H-12 | `ScheduledJobManager.php` | No `$tries` — risk of duplicate backup dispatches |
| PERF-H-13 | `ResourceMonitoringManagerJob.php` | No `$tries`/`$timeout` — can block worker indefinitely |
| PERF-H-14 | `config/database.php:38-51` | No persistent connections / PgBouncer for production |

### Code Quality (12)

| ID | File | Description |
|----|------|-------------|
| QUAL-H-1 | `app/Parsers/ServiceComposeParser.php` | 1373 lines — compose parsing god class |
| QUAL-H-2 | `app/Jobs/ApplicationDeploymentJob.php` | 1310 lines, 18 traits — deployment god job |
| QUAL-H-3 | `app/Console/Commands/AdminDeleteUser.php` | 1194 lines — admin command as god class |
| QUAL-H-4 | `bootstrap/helpers/shared.php` | 1660 lines of global functions |
| QUAL-H-5 | `bootstrap/helpers/docker.php` | 1525 lines of global functions |
| QUAL-H-6 | `app/Actions/Database/Start*.php` (x8) | 8 near-identical database start actions — no abstract base |
| QUAL-H-7 | `app/Models/Standalone*.php` (x5) | Duplicated `getCpuMetrics`/`getMemoryMetrics` across 5 models |
| QUAL-H-8 | `app/Http/Controllers/Webhook/Stripe.php:27` | `ray()` instead of `Log::error()` — errors invisible in production |
| QUAL-H-9 | `app/Parsers/ServiceComposeParser.php:1366` | `ray()` instead of `Log::error()` — compose parse errors lost |
| QUAL-H-10 | `config/constants.php:13-29` | Hardcoded Coolify CDN/GitHub URLs as defaults — upstream dependency |
| QUAL-H-11 | `package.json:26,65` | `@types/react: ^19` vs `react: ^18` — major version mismatch |
| QUAL-H-12 | `app/Models/Application.php` | Mixed `snake_case` + `camelCase` method names in same class |

### Testing (15)

| ID | Category | Description |
|----|----------|-------------|
| TEST-H-1 | Actions | `StartPostgresql/Mysql/Mongo/Redis/Maria/Key/Dragon/Click` — zero tests (8 files) |
| TEST-H-2 | Actions | `StopDatabase`, `StartDatabaseProxy`, `StopDatabaseProxy` — zero tests |
| TEST-H-3 | Actions | `StartService`, `RestartService`, `StopService` — zero tests |
| TEST-H-4 | Actions | `ComplexStatusCheck`, `GetContainersStatus` — zero tests |
| TEST-H-5 | Actions | `ExportProjectAction`, `CloneProjectAction` — zero tests |
| TEST-H-6 | Actions | `CancelSubscription` — financial logic, zero tests |
| TEST-H-7 | Jobs | `BackupRestoreTestJob`, `BackupVerificationJob` — zero tests |
| TEST-H-8 | Jobs | `VolumeCloneJob`, `DatabaseImportJob` — zero tests |
| TEST-H-9 | Jobs | `AutoProvisionServerJob`, `CheckAndStartSentinelJob` — zero tests |
| TEST-H-10 | Jobs | `SubscriptionInvoiceFailedJob`, `VerifyStripeSubscriptionStatusJob` — zero tests |
| TEST-H-11 | Controllers | `OauthController` — zero tests for OAuth flow |
| TEST-H-12 | Controllers | Inertia `ApplicationController`, `ServerController` — zero tests |
| TEST-H-13 | Services | All AI providers (`OpenAI`, `Anthropic`, `Ollama`) — zero tests |
| TEST-H-14 | Services | All DatabaseMetrics services (Postgres, Mysql, Mongo, Redis, Clickhouse) — zero tests |
| TEST-H-15 | Frontend | No tests for error/loading/empty states in most page components |

### API & Frontend (6)

| ID | File | Description |
|----|------|-------------|
| API-H-1 | `routes/api.php:53` | `/api/feedback` — public, no auth, no throttle, no validation |
| API-H-2 | `routes/api.php:45-46` | `/api/health` — public, no throttle, executes DB/Redis queries |
| API-H-3 | All API controllers | Inconsistent error response format (`errors` vs `message` vs `success:true` on 403) |
| API-H-4 | `DeploymentApprovalController.php` | `comment`/`reason` params accepted without length validation |
| FE-H-1 | `resources/js/lib/api.ts:14,21,28` | Core API functions accept `data: any` — type safety lost |
| FE-H-2 | All `.tsx` files | Only 21 ARIA attributes across 137 pages — critically inaccessible |

### Resilience (8)

| ID | File | Description |
|----|------|-------------|
| RESIL-H-1 | `CheckHelperImageJob.php:17` | `$timeout = 1000` (16 min!) — likely typo, should be 10-100 |
| RESIL-H-2 | `CheckForUpdatesJob.php:84-86` | Empty `catch (\Throwable)` swallows all errors silently |
| RESIL-H-3 | `VolumeCloneJob.php` | No `$tries`/`$timeout` for potentially hours-long volume clone |
| RESIL-H-4 | `OtherController.php:155` | `Http::post()` to Discord without timeout/retry/error handling |
| RESIL-H-5 | `Webhook/Github.php:673` | `Http::get()` without explicit timeout for GitHub API |
| RESIL-H-6 | `SyncStripeSubscriptionsJob.php` | No `failed()` for 30-minute Stripe sync job |
| RESIL-H-7 | `docker-compose.prod.yml:43-44` | Soketi required for app start — no graceful degradation |
| RESIL-H-8 | `AI/Providers/OpenAIProvider.php` | No retry/fallback when OpenAI unavailable |

---

## MEDIUM Issues (52) — Summary by Category

### Security (2)
- `UploadController.php:241` — `md5(time())` for filename generation (predictable)
- `DatabaseMetricsController.php:476-481` — S3 error details exposed to user

### Database & Performance (14)
- Multiple `::all()` calls without pagination in Init, ServicesDelete, ClearGlobalSearchCache
- Missing indexes on `scheduled_database_backup_executions.status`, `environments(project_id, name)`
- AlertEvaluationJob AVG queries without caching (every minute)
- SyncStripeSubscriptionsJob `usleep()` blocking worker
- StatusPageSnapshotJob `updateOrCreate` in loop without batching
- `whereDate()` preventing index usage in StatusPageSnapshotJob
- Redis `cluster='redis'` config for standalone instance

### Code Quality (11)
- `ioredis` in production dependencies (unused by frontend)
- Dead code: `resources/js/project-map/*.jsx`, `EXAMPLES.tsx`, `CoolifyTask.php`
- `any` types in production TypeScript (50+ occurrences in 12 files)
- `symfony/console` pin outdated, triple-duplicate constraint in composer.json
- Vue + `@vitejs/plugin-vue` in package.json without usage
- 9160 lines of global helper functions in `bootstrap/helpers/`

### Testing (5)
- `phpunit.xml` uses `QUEUE_CONNECTION=sync` hiding queue behavior
- vitest config includes two directories — potential test discovery issues
- Most frontend tests only verify render, not error/loading states
- `CheckForUpdatesJobTest` doesn't mock `Http::retry()` — network-dependent
- No Dusk E2E tests despite package installed

### API & Frontend (12)
- No `FormRequest` classes — all validation inline across 40 controllers
- Notification channels accept unlimited-length sensitive fields
- Inline route handlers with business logic in `routes/api.php`
- CORS `max_age: 0` — no preflight caching
- `key={index}` in 30+ dynamic lists — incorrect React reconciliation
- 15+ inline arrow functions in `Projects/Show.tsx` JSX
- No individual `ErrorBoundary` for widgets (AiChatGlobal crashes entire app)
- No "Skip to main content" link for keyboard users
- No global state management (prop drilling risk)

### Resilience (8)
- SSH command timeout hardcoded at 3600s (1 hour)
- CheckAndStartSentinelJob: no `$tries`, `$backoff`, `failed()`
- 34 broadcast events use `ShouldBroadcast` — no graceful degradation if Soketi down
- Multiple jobs missing `$backoff` (CheckTraefikVersionJob, RestartProxyJob, etc.)
- Health endpoint combines liveness + readiness (no `/alive` vs `/ready` split)
- AI providers throw on timeout instead of returning graceful failure

---

## Remediation Roadmap

### P0 — Week 1 (Blocks Production)

| # | Task | Files | Est. |
|---|------|-------|------|
| 1 | **Fix SQL injection in DatabaseMetrics** (SEC-CRIT-1/2/3) | PostgresMetricsService, MysqlMetricsService, MongoMetricsService, DatabaseMetricsController | 4h |
| 2 | **Add tableName validation** (SEC-H-5) | DatabaseMetricsController + route constraints | 1h |
| 3 | **Replace mt_rand with random_int** (SEC-H-1) | User.php:782 | 5m |
| 4 | **Replace ray() with Log::error()** (QUAL-H-8/9) | Stripe.php, ServiceComposeParser, ApplicationComposeParser, WebhookChannel, GlobalSearch, ClearsGlobalSearchCache | 30m |
| 5 | **Add $tries/$timeout to critical jobs** (RESIL-CRIT-1, PERF-H-12/13) | SubscriptionInvoiceFailedJob, ScheduledJobManager, ResourceMonitoringManagerJob | 30m |
| 6 | **Fix CheckHelperImageJob timeout** (RESIL-H-1) | CheckHelperImageJob.php:17 (`1000` → `120`) | 5m |
| 7 | **Fix empty catch in CheckForUpdatesJob** (RESIL-H-2) | CheckForUpdatesJob.php:84-86 (add `Log::warning()`) | 5m |
| 8 | **Add auth+throttle to /api/feedback** (API-H-1) | routes/api.php:53 | 10m |
| 9 | **Add throttle to /api/health** (API-H-2) | routes/api.php:45-46 | 10m |
| 10 | **Fix SSRF in GithubController** (SEC-H-4) | GithubController.php — add host allowlist | 30m |

**Estimated P0 total: ~7 hours**

### P1 — Week 2-3 (Production Stability)

| # | Task | Est. |
|---|------|------|
| 11 | Fix N+1 in ScheduledJobManager (PERF-CRIT-1) | 2h |
| 12 | Batch SSH commands in Init.php (PERF-CRIT-2) | 3h |
| 13 | Add DB::transaction to team deletion (PERF-H-11) | 1h |
| 14 | Add index `(server_id, created_at)` on `server_health_checks` (PERF-H-10) | 30m |
| 15 | Fix unbounded queries: DeployController, TeamController, AlertEvaluationJob (PERF-H-4/5/6) | 3h |
| 16 | Replace `->get()` with `->lazyById()` in ScheduledJobManager (PERF-H-9) | 1h |
| 17 | Add eager loading for `team.subscription` (PERF-H-1) | 30m |
| 18 | Add `failed()` to SyncStripeSubscriptionsJob (RESIL-H-6) | 30m |
| 19 | Update `laravel/framework` to `>=12.38.0` (QUAL-CRIT-4) | 2h + testing |
| 20 | Fix `@types/react` version mismatch (QUAL-H-11) | 15m |
| 21 | Add Stripe webhook tests (TEST-CRIT-1) | 4h |
| 22 | Add GitLab/Bitbucket webhook tests (TEST-CRIT-2/3) | 4h |

**Estimated P1 total: ~22 hours**

### P2 — Sprint 3-4 (Quality & Coverage)

| # | Task | Est. |
|---|------|------|
| 23 | Add tests for InstallDocker action (TEST-CRIT-4) | 3h |
| 24 | Add tests for database Start* actions (TEST-H-1) | 4h |
| 25 | Add tests for AI providers (TEST-H-13) | 3h |
| 26 | Add tests for DatabaseMetrics services (TEST-H-14) | 4h |
| 27 | Create AbstractStartDatabase base class (QUAL-H-6) | 3h |
| 28 | Extract getCpuMetrics/getMemoryMetrics into trait (QUAL-H-7) | 2h |
| 29 | Remove dead code: project-map JSX, EXAMPLES.tsx, CoolifyTask (QUAL-M) | 1h |
| 30 | Fix `ioredis` in dependencies (QUAL-M) | 5m |
| 31 | Remove Vue from package.json (QUAL-M) | 15m |
| 32 | Standardize API error response format (API-H-3) | 4h |
| 33 | Add FormRequest classes for top-10 controllers (API-M) | 6h |
| 34 | Type `lib/api.ts` functions (FE-H-1) | 2h |
| 35 | Add ARIA labels to critical UI components (FE-H-2) | 4h |

**Estimated P2 total: ~36 hours**

### P3 — Long-term (Architecture)

| # | Task | Est. |
|---|------|------|
| 36 | Break Application.php into domain services | 2-3 sprints |
| 37 | Break CommandExecutor.php into per-resource handlers | 2 sprints |
| 38 | Break Service.php into focused services | 1-2 sprints |
| 39 | Convert global helpers to injectable services | 3-4 sprints |
| 40 | Complete Livewire → React migration (8 remaining) | 1-2 sprints |
| 41 | Add PgBouncer to production infrastructure | 1 sprint |
| 42 | Replace Coolify CDN URLs with Saturn-owned | 1 sprint |
| 43 | Achieve 60% test coverage | Ongoing |

---

## Score Calculation Methodology

Each dimension scored 0-100 based on:
- **Security (68):** 3 CRITs (SQL injection in new DatabaseMetrics feature) offset V4 improvements
- **Database & Performance (62):** Systematic N+1 in scheduler, unbounded queries, missing indexes
- **Code Quality (55):** 3 god classes >2000 lines, 9160 lines of global helpers, significant duplication
- **Testing (58):** ~26% job coverage, 0% webhook controller coverage, critical financial paths untested
- **API & Frontend (65):** No FormRequest classes, public unauthenticated endpoints, poor accessibility
- **Resilience (52):** Improved from V3 (+7) but new jobs lack $tries/$timeout, empty catches, hardcoded timeouts

**Overall = weighted average: Security(25%) + Performance(20%) + Quality(15%) + Testing(20%) + API(10%) + Resilience(10%) = 60%**

---

## Comparison with Previous Audits

| Metric | V1 | V2 | V3 | V5 |
|--------|----|----|----|----|
| Overall Score | 89% | 92% | 71% | 60% |
| Total CRITs | 11 | 3 | - | 14 |
| Fixed CRITs | 10 | 3 | - | 0 (new) |
| Total HIGHs | 14 | 6 | - | 60 |
| New Code Audited | Core | Core | Full | Full+New Features |

> V5 score is lower because this audit covers significantly more surface area including new features (AI Chat, Database Metrics, Status Pages, Deployment Approvals, Code Review) that were not present in V1-V3. The 14 CRITs are primarily in new code paths (DatabaseMetrics SQL injection) and previously-unaudited areas (webhook controllers, god classes).
