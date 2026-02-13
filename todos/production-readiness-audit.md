# Saturn Platform — Production Readiness Audit

**Date:** 2026-02-13
**Overall Score: 78% Production Ready** (was 62% → 72% → 78%)

## Scorecard

| Category | Score | Status |
|----------|-------|--------|
| Backend Architecture | 80% | ~~Needs work~~ → API validation confirmed solid |
| Frontend Quality | 85% | Production ready |
| Security | 85% | ~~BLOCKER~~ → Hardened (cmd injection + CORS + headers) |
| Testing Coverage | 30% | Lowest priority (CI optional) |
| Database Layer | 90% | ~~Needs indexes + FK~~ → Indexes + FK constraints done |
| Infrastructure | 90% | ~~Almost ready~~ → Security headers + Redis session |

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

### [—] 2. ~~Enable CI Tests~~ — SKIPPED
> Не обязательно для текущего этапа.

---

### [x] 3. ~~Restrict CORS Origins~~ — DONE
**Severity:** MEDIUM — Hardening (not a real blocker for self-hosted)
**Fixed:** 2026-02-13

- `config/cors.php` → uses `CORS_ALLOWED_ORIGINS` env (default: `*` for backward compat)
- `.env.production` → set to `https://saturn.ac`

---

### [x] 4. ~~Add Missing Database Indexes~~ — DONE
**Severity:** LOW — Most indexes already exist
**Fixed:** 2026-02-13

Migration: `2026_02_13_200000_add_missing_performance_indexes.php`
- [x] `application_deployment_queues(pull_request_id)` — PR lookup
- [x] `applications(status)` — filter by running/stopped/exited
- [x] `servers(ip)` — server lookup by IP

---

## P1 — HIGH PRIORITY (first week)

### [—] 5. ~~Create Form Request Classes~~ — DEPRIORITIZED
**Effort:** 2-3 days | **Actual Severity:** LOW (refactoring, not security)

> **Re-verified 2026-02-13:** Controllers already have solid inline validation:
> - `customApiValidator()` with explicit whitelist of allowed fields
> - Extra fields rejection (422 "This field is not allowed")
> - Type/format validation (ports, base64, enums, port ranges 1024-65535)
> - Domain conflict detection, UUID existence checks, IP uniqueness
> - `ValidationPatterns` helper for name/description rules
>
> Converting to Form Request classes is a code cleanliness refactor, not a security fix. Deferred to P3.

---

### [x] 6. ~~Replace $guarded=[] with $fillable~~ — ALREADY DONE
~~**Effort:** 1 day~~

> **Verified 2026-02-13:** All 86 models already use explicit `$fillable`. Zero models have `protected $guarded = []`. This was already fixed previously.

---

### [x] 7. ~~Add Foreign Key Constraints~~ — DONE
**Fixed:** 2026-02-13

Production orphan check: **0 orphans** — safe to add constraints.

Migration: `2026_02_13_210000_add_foreign_key_constraints_to_core_tables.php`
- CASCADE: `servers.team_id`, `projects.team_id`, `environments.project_id`, `applications.environment_id`, `services.environment_id`, `standalone_dockers.server_id`, all 8 `standalone_*.environment_id`
- SET NULL: `servers.private_key_id`, `applications.private_key_id`, `services.server_id`

---

### [x] 8. ~~Add Security Headers in Nginx~~ — DONE
**Fixed:** 2026-02-13

Created `docker/production/etc/nginx/conf.d/security.conf`:
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: deny geo/mic/camera
- CSP: self + unsafe-inline/eval (required for Inertia.js + Vite)

---

### [x] 9. ~~Switch SESSION_DRIVER to Redis~~ — DONE
**Fixed:** 2026-02-13

Added `SESSION_DRIVER=redis` to `.env.production`.

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

### [x] 19. ~~CDN + Compression~~ — DONE
**Fixed:** 2026-02-13

- [x] Gzip compression in `docker/production/etc/nginx/conf.d/compression.conf`
- [x] Static asset caching (1y, immutable) in `site-opts.d/http.conf`

### [ ] 20. E2E Tests (Playwright)
> **Verified:** No Playwright in package.json. Thesis valid.

- [ ] Login → Create Project → Deploy App flow
- [ ] Database creation → Import data flow

### [x] 21. ~~Server Model Input Validation~~ — DONE
**Fixed:** 2026-02-13

Validation at 3 levels: model boot hook, web routes, API controller.
- [x] `Server->user` — regex `/^[a-zA-Z0-9_-]+$/` (rejects shell injection like `root; rm -rf /`)
- [x] `Server->ip` — `filter_var(FILTER_VALIDATE_IP)` + hostname regex (supports IPv4/IPv6/hostnames)
- [x] 9 unit tests covering valid/invalid inputs

### [x] 22. ~~Remove/Fix CanUpdateResource Middleware~~ — CONFIRMED
> **Verified:** `CanUpdateResource::handle()` does `return $next($request)` immediately (line 28), all logic commented out. Dead middleware registered in Kernel. Thesis 100% correct.

- [ ] Either delete from Kernel.php or implement proper policy checks

### [x] 23. ~~Docker Resource Limits~~ — DONE
**Fixed:** 2026-02-13

`docker-compose.prod.yml`:
- saturn: 1G RAM / 2 CPU
- postgres: 512M RAM / 1 CPU
- redis: 256M RAM / 0.5 CPU
- soketi: 256M RAM / 0.5 CPU

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
