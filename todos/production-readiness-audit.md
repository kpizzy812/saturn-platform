# Saturn Platform — Production Readiness Audit

**Date:** 2026-02-13
**Overall Score: 62% Production Ready**

## Scorecard

| Category | Score | Status |
|----------|-------|--------|
| Backend Architecture | 74% | Needs work |
| Frontend Quality | 85% | Production ready |
| Security | 70% | BLOCKER (cmd injection) |
| Testing Coverage | 30% | BLOCKER (CI disabled) |
| Database Layer | 55% | Needs indexes + FK |
| Infrastructure | 82% | Almost ready |

---

## P0 — CRITICAL BLOCKERS (before production)

### [x] 1. ~~Fix Command Injection (escapeshellarg)~~ — DONE
**Severity:** CRITICAL — Remote Code Execution risk
**Fixed:** 2026-02-13

All `instant_remote_process()` / `remote_process()` / `execute_remote_command()` calls now use `escapeshellarg()` for dynamic values. Also fixed additional files discovered during deep scan.

**Files fixed (original audit list):**
- [x] `app/Console/Commands/CheckApplicationDeploymentQueue.php` — `escapeshellarg($deployment->deployment_uuid)`
- [x] `app/Console/Commands/CleanupApplicationDeploymentQueue.php` — same
- [x] `app/Actions/Service/DeleteService.php` — `escapeshellarg($storage->name)`, `escapeshellarg($service->uuid)`
- [x] `app/Traits/Deployment/HandlesContainerOperations.php` — `escapeshellarg($containerName)`
- [x] `app/Actions/Service/StopService.php` — `array_map('escapeshellarg', $containersToStop)`
- [x] `app/Http/Controllers/Api/DeployController.php` — `escapeshellarg($deployment_uuid)`, `(int)` cast for PID
- [x] `app/Jobs/DatabaseRestoreJob.php` — `escapeshellarg()` for container, network, paths; also fixed PHPStan type narrowing

**Additional files fixed (discovered during deep scan):**
- [x] `app/Actions/Database/StopDatabase.php` — `escapeshellarg($containerName)`
- [x] `app/Actions/Application/StopApplication.php` — `escapeshellarg($containerName)`, `escapeshellarg($application->uuid)`
- [x] `app/Actions/Application/StopApplicationOneServer.php` — `escapeshellarg($containerName)`
- [x] `app/Actions/Proxy/StopProxy.php` — `escapeshellarg($containerName)`
- [x] `app/Jobs/CleanupSleepingPreviewsJob.php` — `escapeshellarg($containerName)`
- [x] `app/Jobs/RestartProxyJob.php` — `escapeshellarg($containerName)`
- [x] `app/Jobs/ApplicationDeploymentJob.php` — `escapeshellarg($this->container_name)`
- [x] `app/Jobs/DatabaseBackupJob.php` — `escapeshellarg()` for container, network, paths, bucket

---

### [ ] 2. Enable CI Tests
**Severity:** CRITICAL — No automated quality gate
**Effort:** 1 hour

```yaml
# .github/workflows/deploy-to-vps.yml
test:
  if: false  # ← CHANGE TO: if: true
```

Also fix:
- [ ] Fix `useDeployments.test.ts` outdated API mocks
- [ ] Make test job blocking (remove `continue-on-error: true`)

---

### [ ] 3. Restrict CORS Origins
**Severity:** MEDIUM — Hardening (not a real blocker for self-hosted)
**Effort:** 5 min

> **Note:** `supports_credentials: false` prevents cookie-based CSRF. API tokens require attacker to already have the token. Real severity is MEDIUM for internal self-hosted product, not P0.

```php
// config/cors.php — CURRENT (vulnerable):
'allowed_origins' => ['*'],

// FIX:
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost')),
```

Add to `.env.production`:
```
CORS_ALLOWED_ORIGINS=https://saturn.yourcompany.com
```

---

### [ ] 4. Add Missing Database Indexes
**Severity:** LOW — Most indexes already exist
**Effort:** 15 min

> **Verified:** 14 of 17 proposed indexes already exist via `morphs()`, `foreignId()`, explicit `->index()`, or `->unique()`. Only 3 are genuinely missing.

~~Polymorphic (8)~~ — **ALL 8 EXIST** (via `nullableMorphs()` / `morphs()` / explicit `index()`)
~~Foreign Keys (3)~~ — **ALL 3 EXIST** (via `foreignId()` auto-index)
~~`deployment_uuid`~~ — **EXISTS** (unique constraint)
~~`server_id`~~ — **EXISTS** (composite index `status, server_id`)
~~`activity_log(log_name)`~~ — **EXISTS** (explicit index)

**Only 3 indexes actually missing:**
- [ ] `application_deployment_queues(pull_request_id)` — standalone index for PR lookup
- [ ] `applications(status)` — filter by running/stopped/exited
- [ ] `servers(ip)` — server lookup by IP

---

## P1 — HIGH PRIORITY (first week)

### [ ] 5. Create Form Request Classes (top-20 API endpoints)
**Effort:** 2-3 days

Currently: 0 Form Request classes across 38 API controllers.

> **Verified:** 5 controllers DO have inline `$request->validate()` (14 calls total). 12 controllers use `validateIncomingRequest()` but that only checks JSON format — NOT field validation. The rest have zero input validation.

Priority endpoints:
- [ ] `CreateApplicationRequest` — POST /api/v1/applications
- [ ] `UpdateApplicationRequest` — PATCH /api/v1/applications/{uuid}
- [ ] `CreateServerRequest` — POST /api/v1/servers
- [ ] `UpdateServerRequest` — PATCH /api/v1/servers/{uuid}
- [ ] `CreateDatabaseRequest` — POST /api/v1/databases/*
- [ ] `DeployRequest` — POST /api/v1/deploy
- [ ] `CreateProjectRequest` — POST /api/v1/projects
- [ ] `CreateEnvironmentRequest` — POST /api/v1/environments
- [ ] `CreateWebhookRequest` — POST /api/v1/webhooks
- [ ] `UpdateTeamRequest` — PATCH /api/v1/teams/*

---

### [x] 6. ~~Replace $guarded=[] with $fillable~~ — ALREADY DONE
~~**Effort:** 1 day~~

> **Verified 2026-02-13:** All 86 models already use explicit `$fillable`. Zero models have `protected $guarded = []`. This was already fixed previously.

---

### [ ] 7. Add Foreign Key Constraints
**Effort:** 1 day (after orphan check)

> **Verified:** 55 `foreignId()` calls WITHOUT `->constrained()` (core 2023-2024 tables). 79 already have constraints (2025-2026 tables). Core tables (servers, applications, projects, environments, services, standalone DBs) are the main gap.

**First, check for orphaned records on production:**
```sql
SELECT COUNT(*) FROM servers s LEFT JOIN teams t ON s.team_id = t.id WHERE t.id IS NULL;
SELECT COUNT(*) FROM applications a LEFT JOIN environments e ON a.environment_id = e.id WHERE e.id IS NULL;
```

If clean, add FK constraints with `constrained()->cascadeOnDelete()` or `nullOnDelete()`.

---

### [ ] 8. Add Security Headers in Nginx
**Effort:** 30 min

> **Verified:** Zero security headers exist anywhere — not in nginx config, not via Laravel middleware. Path `docker/production/etc/nginx/conf.d/security.conf` is correct (next to existing `custom.conf`). Note: `unsafe-inline` + `unsafe-eval` in CSP is acceptable for Inertia.js + Vite stack.

Create `docker/production/etc/nginx/conf.d/security.conf`:
```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' wss:;" always;
```

---

### [ ] 9. Switch SESSION_DRIVER to Redis
**Effort:** 5 min
**Severity:** LOW — Minimal impact for self-hosted with few users

> **Verified:** Production `.env` does NOT set `SESSION_DRIVER` → uses default `database`. Dev example has `redis` but it was never applied to production.

```env
# .env.production
SESSION_DRIVER=redis  # Default fallback is 'database'
```

---

## P2 — MEDIUM PRIORITY (within a month)

### [ ] 10. Tests for Deployment Flow
**Effort:** 3-5 days

> **Verified:** 18 deployment test files exist but cover periphery only (models, events, notifications, UI). The core `ApplicationDeploymentJob` (SSH → git → docker build → container) has **0 tests**. Thesis is correct.

Currently ~10% coverage on the core feature (peripheral only).
- [ ] `tests/Feature/Deployment/ApplicationDeploymentFlowTest.php`
- [ ] `tests/Unit/Jobs/ApplicationDeploymentJobTest.php`
- [ ] `tests/Feature/Deployment/RollbackMechanismTest.php`
- [ ] `tests/Feature/Deployment/PreviewDeploymentTest.php`

---

### [ ] 11. Create Factories for Core Models (10+)
**Effort:** 2 days

> **Verified:** Exactly 7 factories for 88 models (8%). All 10 proposed factories are genuinely missing. Numbers are accurate.

Currently 7 factories: `User`, `Team`, `Server`, `Application`, `Project`, `Environment`, `PrivateKey`.
- [ ] `ServiceFactory.php`
- [ ] `StandaloneDockerFactory.php`
- [ ] `ApplicationDeploymentQueueFactory.php`
- [ ] `ScheduledDatabaseBackupFactory.php`
- [ ] `ApplicationPreviewFactory.php`
- [ ] `EnvironmentVariableFactory.php`
- [ ] `CloudProviderTokenFactory.php`
- [ ] `LocalPersistentVolumeFactory.php`
- [ ] `GithubAppFactory.php`
- [ ] `InstanceSettingsFactory.php`

---

### [ ] 12. Create API Resource Classes
**Effort:** 3 days

> **Verified:** 0 Resource classes. API uses custom `serializeApiResponse()` helper (37 calls across 15 controllers) instead of Laravel Resources.

Standardize API responses:
- [ ] `ApplicationResource.php`
- [ ] `ServerResource.php`
- [ ] `DatabaseResource.php`
- [ ] `ServiceResource.php`
- [ ] `TeamResource.php`
- [ ] `ProjectResource.php`
- [ ] `DeploymentResource.php`

---

### [~] 13. Blue-Green Deployment — PARTIALLY ALREADY DONE
**Effort:** 1 day (for edge cases only)

> **Verified:** Rolling update already exists in `HandlesHealthCheck::rolling_update()` — new container starts, health-checks, then old stops. This IS blue-green for standard deploys. Downtime only occurs when rolling update is unsupported (port mappings, consistent name, custom IP, PR deploys). The "30-60s downtime" claim applies to edge cases, not default flow.

Remaining work: improve edge cases where rolling update is unsupported.

---

### [ ] 14. Structured Logging (JSON)
**Effort:** 1 day

> **Verified:** All channels use plain text. Default LOG_LEVEL is `debug`. No JSON formatter, no correlation IDs.

```php
// config/logging.php
'production' => [
    'driver' => 'daily',
    'level' => 'warning',
    'days' => 30,
    'formatter' => \Monolog\Formatter\JsonFormatter::class,
],
```

Add RequestIdProcessor for correlation IDs.

---

### [ ] 15. Add Eager Loading to Models
**Effort:** 1 day

> **Verified:** 0 models use `protected $with`. Ad-hoc eager loading (466 `::with()`/`->load()` calls) is used everywhere. Note: model-level `$with` always loads relations even when unneeded — consider if this is actually desirable vs ad-hoc approach.

Candidates:
- [ ] `Application.php` → `$with = ['environment']`
- [ ] `ApplicationDeploymentQueue.php` → `$with = ['application']`
- [ ] `Server.php` → `$with = ['settings']`

---

## P3 — NICE TO HAVE (gradually)

### [ ] 16. React Performance Optimization
> **Verified:** `React.memo` — 1 usage (LogsContainer). `React.lazy` — 1 usage (AiChat). `any` — **64 occurrences** (not 154 as claimed). Numbers overstated.

- [ ] Add `React.memo` to heavy components (ProjectCanvas, LogsViewer)
- [ ] Lazy load heavy components (Terminal, Canvas)
- [ ] Add `react-window` for large lists (100+ items)
- [ ] Remove 64 `any` usages in TypeScript (not 154)

### [ ] 17. Global State Management
> **Verified:** No Zustand/React Query/TanStack in `package.json`. Thesis valid.

- [ ] Evaluate Zustand or React Query
- [ ] Centralize server state caching
- [ ] Reduce prop drilling in ProjectShow (20+ props)

### [ ] 18. PgBouncer for Connection Pooling
> **Verified:** No PgBouncer in any docker-compose. For self-hosted internal PaaS with few users, benefit is minimal. LOW priority.

- [ ] Add PgBouncer service to docker-compose
- [ ] Configure POOL_MODE=transaction, DEFAULT_POOL_SIZE=25

### [ ] 19. CDN + Compression
> **Verified:** No gzip/brotli in nginx config. Valid.

- [ ] Add Gzip/Brotli in Nginx (60-80% reduction)
- [ ] Static asset caching (Cache-Control: immutable)

### [ ] 20. E2E Tests (Playwright)
> **Verified:** No Playwright in package.json. Thesis valid.

- [ ] Login → Create Project → Deploy App flow
- [ ] Database creation → Import data flow

### [ ] 21. Server Model Input Validation
> **Verified PARTIALLY DONE:** `Server` already uses `HasSafeStringAttribute` trait (strips HTML from name/description). But no `FILTER_VALIDATE_IP` for `ip` field, no regex for `user` field. SSH-specific validation still missing.

- [ ] `Server->user` — regex `/^[a-zA-Z0-9_-]+$/`
- [ ] `Server->ip` — `filter_var(FILTER_VALIDATE_IP)` or hostname regex

### [x] 22. ~~Remove/Fix CanUpdateResource Middleware~~ — CONFIRMED
> **Verified:** `CanUpdateResource::handle()` does `return $next($request)` immediately (line 28), all logic commented out. Dead middleware registered in Kernel. Thesis 100% correct.

- [ ] Either delete from Kernel.php or implement proper policy checks

### [ ] 23. Docker Resource Limits
> **Verified:** No resource limits in any docker-compose. Valid for hardening.

### [ ] 24. Automated Rollback on Failed Deploy
> **Verified:** `deploy/scripts/deploy.sh` exists (referenced in CI). Note: application-level rollback already has `MonitorDeploymentHealthJob`. This thesis is about PLATFORM deploy rollback, not app deploy. Valid.

---

## Metrics to Track

| Metric | Current | Target (3mo) | Target (6mo) |
|--------|---------|--------------|--------------|
| Backend test coverage | ~25% | 50% | 65% |
| Frontend test coverage | ~10% | 40% | 60% |
| TypeScript `any` usage | 154 | 50 | 0 |
| Models with $fillable | ~50% | 100% | 100% |
| API endpoints with validation | ~10% | 50% | 90% |
| DB indexes coverage | ~26% FK | 80% | 95% |
| CI test job | DISABLED | ENABLED (blocking) | ENABLED |
| Deployment downtime | 30-60s | 10s | 0s (blue-green) |
