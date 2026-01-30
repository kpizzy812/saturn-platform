# Monorepo Detection - Progress Tracker

**Started:** 2026-01-30
**Status:** Phase 1-4 Complete

---

## Phase 1: Core Components (P0)

### DTOs (Low Complexity)
- [x] `MonorepoInfo.php` - Created
- [x] `DetectedApp.php` - Created with type field (backend/frontend/fullstack/unknown)
- [x] `DetectedDatabase.php` - Created with immutable `withMergedConsumers()`
- [x] `DetectedService.php` - Created
- [x] `DetectedEnvVariable.php` - Created with category field
- [x] `DependencyAnalysisResult.php` - Created
- [x] `AnalysisResult.php` - Created (combined result DTO)
- [x] `ProvisioningResult.php` - Created

### Exceptions
- [x] `RepositoryAnalysisException.php` - Created
- [x] `ProvisioningException.php` - Created

### Detectors (High Complexity)
- [x] `MonorepoDetector.php` - Created (Turborepo, Nx, pnpm, Lerna, Rush, npm/yarn workspaces)
- [x] `AppDetector.php` - Created (20+ framework detection rules)
- [x] `DependencyAnalyzer.php` - Created (uses existing EnvExampleParser)

### Main Service (High Complexity)
- [x] `RepositoryAnalyzer.php` - Created (path validation, 500MB limit, deduplication)
- [x] `InfrastructureProvisioner.php` - Created (DB::transaction, ResourceLinks)

---

## Phase 2: API & Frontend (P1)

### API Endpoints (Medium Complexity)
- [x] `GitAnalyzerController.php` - Created
  - POST `/api/v1/git/analyze` - Repository analysis
  - POST `/api/v1/git/provision` - Infrastructure provisioning
- [x] Add routes to `routes/api.php`

### Frontend UI (Medium Complexity)
- [x] `MonorepoAnalyzer.tsx` - Created with Saturn UI components

---

## Phase 3: Database & Model Updates (P1)

### Migration (Low Complexity)
- [x] Create migration `add_monorepo_group_to_applications`
- [ ] Run migration on production (pending deployment)

### Model Updates
- [x] Update `Application.php` with monorepo methods
  - Added `monorepo_group_id` to $casts
  - Added `monorepoSiblings()`, `isPartOfMonorepo()`, `getMonorepoGroup()`, `getMonorepoGroupCount()`
  - Added scopes: `scopeInMonorepoGroup()`, `scopeMonorepoApps()`, `scopeStandaloneApps()`

---

## Phase 4: Testing

### Unit Tests
- [x] `MonorepoDetectorTest.php` - 10 tests
  - Turborepo, pnpm, Lerna, Nx, npm workspaces
  - Edge cases: empty JSON, invalid JSON, Yarn Berry format
- [x] `AppDetectorTest.php` - 11 tests
  - NestJS, Next.js, FastAPI, Vite+React, Dockerfile, Go Fiber
  - Monorepo app detection, excludeDeps logic
- [x] `DependencyAnalyzerTest.php` - 10 tests
  - PostgreSQL, Redis, MongoDB detection
  - .env.example parsing and categorization
  - Python and PHP dependency detection
- [ ] `EdgeCasesTest.php` - Optional additional edge case tests

---

## Dependencies

- [x] `yosymfony/toml` - Already in composer.json
- [x] `symfony/yaml` - Already installed

---

## Supported Frameworks (20+)

| Language | Frameworks |
|----------|------------|
| Node.js | NestJS, Next.js, Nuxt, Remix, Astro, SvelteKit, Vite+React/Vue/Svelte, Express, Fastify, Hono |
| Python | Django, FastAPI, Flask |
| Go | Fiber, Gin, Echo |
| Ruby | Rails, Sinatra |
| Rust | Axum, Actix |
| PHP | Laravel, Symfony |
| Elixir | Phoenix |
| Java | Spring Boot |

## Supported Databases

- PostgreSQL (pg, psycopg2, prisma)
- MySQL (mysql2, pymysql)
- MongoDB (mongoose, pymongo)
- Redis (ioredis, redis-py, predis)
- ClickHouse (clickhouse-client)

## Notes

- Used existing `App\Services\EnvExampleParser` for .env parsing
- Used existing database helper functions from `bootstrap/helpers/databases.php`
- Used existing `generateFqdn` helper
- All 31 unit tests passing
