# Migration System Roadmap

## Current Status: ~98% complete (Phase 1-3 done)

The 3-stage deployment pipeline (dev -> uat -> production) is fully implemented with:
approval workflow, rollback, chain validation, UUID rewiring, ResourceLink cloning,
env var merge strategy, history tracking, FQDN/domain management, SSL provisioning,
pre-migration validation, auto-detect clone/promote, diff preview, migration detail page,
auto backup, credential rotation, cancel migration, batch migrations, disk space check,
port conflict detection, health check after deploy, test data copy, webhook reminders.

---

## Terminology

- **Clone mode** = First deployment to an environment. Creates a NEW copy of the resource.
  Copies env vars, volumes config, scheduled tasks, tags, ResourceLinks.
- **Promote mode** = Subsequent deployments. Updates EXISTING resource configuration
  (code, build settings, health checks). Does NOT touch env vars or FQDN (already configured).

---

## P0 — Blockers (must fix before production use)

### 1. Production FQDN/Domain Management
**Status:** Not implemented
**Problem:** When cloning to production for the first time, FQDN is set to null.
No UI to assign production domain. On subsequent promotes, FQDN should stay as-is.

**Requirements:**
- On FIRST clone to production: show domain input in EnvironmentMigrateModal
- Support two modes:
  - Subdomain of platform: `*.saturn.ac` (e.g., `myapp.saturn.ac`)
  - Custom domain: any user-provided domain (e.g., `app.company.com`)
- On subsequent promotes: domain is already configured, skip this step
- After domain assignment: update Traefik/Caddy proxy labels to match new domain
- Trigger SSL certificate provisioning (Let's Encrypt) automatically

**Files to create:**
- `app/Actions/Migration/AssignProductionDomainAction.php`
- `app/Actions/Migration/UpdateProxyLabelsAction.php`

**Files to modify:**
- `resources/js/components/features/migration/EnvironmentMigrateModal.tsx` — add domain input for production
- `app/Actions/Migration/ExecuteMigrationAction.php` — call domain action after clone to prod
- `app/Actions/Migration/PromoteResourceAction.php` — skip FQDN (already works)

### 2. SSL Certificate Auto-Provisioning
**Status:** Not implemented
**Problem:** After assigning production FQDN, no Let's Encrypt certificate is requested.
Production site won't work on HTTPS.

**Requirements:**
- After successful deploy with new FQDN, dispatch certificate provisioning job
- Integrate with existing Let's Encrypt / Caddy auto-SSL system

**Files to modify:**
- `app/Actions/Migration/ExecuteMigrationAction.php` — dispatch cert job after deploy

### 3. Pre-Migration Validation in UI
**Status:** Backend exists, UI doesn't call it
**Problem:** Backend has excellent `POST /api/v1/migrations/check` endpoint that checks
disk space, port conflicts, health status, config drift. But the UI never calls it.

**Requirements:**
- Call `/api/v1/migrations/check` after user selects target environment and server
- Show warnings/errors before user clicks "Start Migration"
- Block migration if critical checks fail (server not functional, active migration exists)
- Show advisory warnings (empty env vars, config drift, disk space low)

**Files to modify:**
- `resources/js/components/features/migration/MigrateConfigureStep.tsx` — add validation call
- `resources/js/components/features/migration/EnvironmentMigrateModal.tsx` — display results

---

## P1 — High Priority (UX and reliability)

### 4. Migration Detail Page with Real-Time Progress
**Status:** Partially implemented (backend writes progress, frontend doesn't read it)
**Problem:** After migration starts, UI shows only "Started" status. No real-time updates.
User doesn't know if migration is in progress, waiting for approval, or failed.

**Requirements:**
- After migration starts, redirect to a dedicated migration detail page
- Show: approval status, progress percentage, current step, logs
- WebSocket listener for `environment_migration_progress` events
- If migration requires approval: show "Waiting for approval" state with approver info
- If migration fails: show error with log output and retry/rollback options
- If migration succeeds: show summary of what was done (rewired connections, cloned links)

**Files to create:**
- `resources/js/pages/Migrations/Show.tsx` — migration detail page
- `resources/js/hooks/useMigrationProgress.ts` — WebSocket hook for progress

**Files to modify:**
- `resources/js/components/features/migration/EnvironmentMigrateModal.tsx` — redirect after start
- `routes/web.php` — add route for migration detail page

### 5. Diff Preview Before Migration
**Status:** Backend fully implemented, UI missing
**Problem:** `MigrationDiffAction` generates detailed diff (attribute changes, env var
additions/removals, connection rewiring preview) but UI doesn't show it.

**Requirements:**
- Add a "Review Changes" step between configure and confirm in the wizard
- Show for each resource:
  - Attributes that will change (for promote mode)
  - Env vars that will be added (for clone mode)
  - Connections that will be rewired (UUID replacements)
  - ResourceLinks that will be created
- Color-coded: green=added, red=removed, yellow=changed
- Allow user to cancel if preview looks wrong

**Files to create:**
- `resources/js/components/features/migration/MigrateDiffStep.tsx`

**Files to modify:**
- `resources/js/components/features/migration/EnvironmentMigrateModal.tsx` — add diff step

### 6. Automatic Backup Before Production Migration
**Status:** Not implemented
**Problem:** When promoting to production (updating existing resources), no automatic
backup is created. If something goes wrong, rollback depends on snapshot in migration
record, but physical DB data could be lost.

**Requirements:**
- Before promote to production: automatically create backup of target resource
- For databases: trigger `pg_dump` / `mysqldump` / `mongodump` via existing backup system
- For applications: snapshot current config (already done via MigrationHistory)
- Store backup reference in migration record for easy restore
- Show backup status in migration progress

**Files to modify:**
- `app/Actions/Migration/ExecuteMigrationAction.php` — add pre-migration backup step
- `app/Actions/Migration/PromoteResourceAction.php` — integrate with backup system

### 7. Auto-Detect Clone vs Promote Mode
**Status:** Backend supports both, UI always sends clone
**Problem:** `EnvironmentMigrateModal` always sends `mode: clone`. The `promote` mode
(update existing resource config) is only available via API.

**Requirements:**
- Auto-detect mode per resource (NO manual toggle needed):
  - Resource does NOT exist in target env -> `clone` (first deployment)
  - Resource already exists in target env -> `promote` (config update)
- Use `/api/v1/migrations/check` response to determine existence
- Show informational badge per resource: "New" (clone) or "Update" (promote)
- For promote: show which fields will be updated (from diff preview)
- For clone: show that new resource will be created + domain input if production

**Files to modify:**
- `resources/js/components/features/migration/EnvironmentMigrateModal.tsx` — auto-detect from check response
- `resources/js/components/features/migration/MigrateConfigureStep.tsx` — info badges

---

## P2 — Medium Priority (robustness)

### 8. Optional Test Data Copy (non-production only)
**Status:** ✅ Implemented (Phase 3)
**Problem:** After first clone dev -> uat, UAT databases are empty. For testing,
it's useful to copy seed/test data.

**Requirements:**
- Optional checkbox "Copy test data" — ONLY for non-production targets
- **HIDDEN entirely** when target environment is production (not disabled — not rendered at all)
- Backend must also enforce: reject `copy_data: true` if target env `isProduction()`
- For databases: `docker exec` dump -> transfer -> restore
- Destructive warning before confirm: "ALL data in the target database will be replaced"
- Show estimated data size before copying
- Only applies to database resources (not applications/services)

**Files to create:**
- `app/Jobs/DatabaseDataCopyJob.php`
- `app/Actions/Database/DumpDatabaseAction.php`
- `app/Actions/Database/RestoreDatabaseAction.php`

### 9. Database Credential Rotation for Production
**Status:** Not implemented
**Problem:** When cloning to production, database credentials (passwords) are the
same as in UAT. Production should have unique credentials.

**Requirements:**
- On first clone to production: generate new passwords for database
- Update all env vars that reference the old password
- Show new credentials in migration summary (one time display)

### 10. Port Conflict Detection
**Status:** ✅ Implemented (Phase 3)
**Problem:** If target environment already has a PostgreSQL on port 5432, and we
clone another one, both will try to use 5432.

**Requirements:**
- In PreMigrationCheckAction: check for port conflicts on target server
- Suggest alternative port or warn user

### 11. Disk Space Check on Target Server
**Status:** ✅ Implemented (Phase 3)
**Problem:** Migration can fail if target server has no disk space.

**Requirements:**
- In PreMigrationCheckAction: SSH to target server, check `df -h`
- Warn if less than 20% free space
- Block if less than 5% free space

### 12. Proxy Labels Update for Production Domains
**Status:** Not implemented
**Problem:** Traefik/Caddy labels may contain UAT domain after clone.
`traefik.http.routers.*.rule` says `Host(app-uat.company.com)` but should say
`Host(app.company.com)`.

**Requirements:**
- After AssignProductionDomainAction: update all proxy labels
- Replace old FQDN with new production FQDN in custom_labels

### 13. Batch/Parallel Migrations
**Status:** ✅ Implemented (Phase 3)
**Problem:** `EnvironmentMigrateModal.tsx:242-267` awaits each migration sequentially.
Slow for 10+ resources.

**Requirements:**
- Backend batch endpoint for parallel migrations
- Frontend: parallel requests with individual progress tracking
- Respect dependency order: databases first, then services, then applications

### 14. Cancel Migration Endpoint
**Status:** ✅ Implemented (Phase 3)
**Problem:** `EnvironmentMigration::canBeCancelled()` exists but no API to call it.
User can't cancel a stuck migration.

**Requirements:**
- `POST /api/v1/migrations/{uuid}/cancel`
- Only cancellable if status is pending/approved (not in_progress)

### 15. Health Check After Auto-Deploy
**Status:** ✅ Implemented (Phase 3)
**Problem:** Migration is marked as "completed" immediately after deploy is triggered,
but the application might still be starting up or failing health checks.

**Requirements:**
- Option `wait_for_ready` (bool) in migration options
- After deploy: poll health check status for up to N minutes
- Only mark as completed when health check passes
- Mark as warning if health check fails after timeout

### 16. Webhook Configuration Handling
**Status:** ✅ Implemented (Phase 3) - Reminder log after first production clone
**Problem:** GitHub/GitLab/Bitbucket webhook URLs point to dev environment after clone.
Webhook secrets are intentionally excluded for security.

**Requirements:**
- After first clone to production: show reminder to configure webhooks
- Optionally: auto-register webhook on GitHub via API with new environment URL

---

## Implementation Order

### Phase 1 (Week 1-2): Production-Ready Core
1. FQDN/Domain Management (#1) + SSL (#2) + Proxy Labels (#12)
2. Pre-Migration Validation in UI (#3)
3. Clone vs Promote auto-detection (#7)
4. Diff Preview UI (#5)

### Phase 2 (Week 2-3): UX & Reliability
5. Migration Detail Page with WebSocket progress (#4)
6. Auto Backup Before Production (#6)
7. Credential Rotation (#9)

### Phase 3 (Week 3-4): Polish
8. Optional Test Data Copy (#8)
9. Port Conflict Detection (#10) + Disk Space Check (#11)
10. Cancel Migration (#14)
11. Health Check After Deploy (#15)
12. Batch Migrations (#13)
13. Webhook Handling (#16)

---

## What Already Works Well

- Approval workflow with race condition protection
- Rollback system with snapshot restore
- Chain validation (dev -> uat -> prod, no skipping)
- Env var merge strategy (target-only vars preserved)
- UUID connection rewiring (RewireConnectionsAction)
- ResourceLink cloning (CloneResourceLinksAction)
- History tracking with SHA256 hash and config drift detection
- Authorization (role-based + team ownership)
- Scheduled tasks (cron jobs) migration
- Tags migration
- All 8 database types supported
- Dry-run support in API
