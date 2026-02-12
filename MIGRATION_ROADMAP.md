# Migration System Roadmap

## Current Status: 100% complete (All phases done)

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
**Status:** ✅ Implemented (Phase 1)
- `AssignProductionDomainAction.php` — domain assignment with subdomain/custom modes
- `UpdateProxyLabelsAction.php` — Traefik/Caddy label update after FQDN change
- UI domain input in EnvironmentMigrateModal for production clone
- Promote mode skips FQDN (preserves existing domain)

### 2. SSL Certificate Auto-Provisioning
**Status:** ✅ Handled by infrastructure
- Traefik ACME resolver auto-provisions Let's Encrypt certs for HTTPS FQDNs
- Caddy has built-in automatic HTTPS (no config needed)
- `AssignProductionDomainAction` normalizes FQDN to `https://` scheme
- Docker labels include `tls.certresolver=letsencrypt` for HTTPS domains
- `MasterProxyConfigService` includes TLS config for remote app routing

### 3. Pre-Migration Validation in UI
**Status:** ✅ Implemented (Phase 1)
- UI calls `POST /api/v1/migrations/check` before migration start
- Shows warnings/errors (disk space, port conflicts, config drift, empty env vars)
- Blocks migration on critical failures (server not functional, active migration)

---

## P1 — High Priority (UX and reliability)

### 4. Migration Detail Page with Real-Time Progress
**Status:** ✅ Implemented (Phase 2)
- `pages/Migrations/Show.tsx` — dedicated migration detail page
- `useMigrationProgress.ts` — WebSocket hook for real-time progress
- Shows approval status, progress %, current step, logs
- Redirect after migration starts from modal

### 5. Diff Preview Before Migration
**Status:** ✅ Implemented (Phase 1)
- `MigrateDiffStep.tsx` — review step in migration wizard
- Color-coded: green=added, red=removed, yellow=changed
- Shows attribute changes, env var additions, UUID rewiring, ResourceLink creation

### 6. Automatic Backup Before Production Migration
**Status:** ✅ Implemented (Phase 2)
- Auto-creates backup before promote to production
- For databases: dispatches backup via existing backup system
- Backup reference stored in migration record
- Progress shown in migration detail

### 7. Auto-Detect Clone vs Promote Mode
**Status:** ✅ Implemented (Phase 1)
- Auto-detects mode per resource (no manual toggle)
- Uses `/api/v1/migrations/check` to determine existence
- Shows "New" (clone) or "Update" (promote) badges
- Domain input shown only for production clone

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
**Status:** ✅ Implemented (Phase 2)
- On first clone to production: generates new passwords for databases
- Updates all env vars referencing old credentials
- New credentials shown in migration summary

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
**Status:** ✅ Implemented (Phase 1)
- `UpdateProxyLabelsAction` replaces old FQDN with new production FQDN in custom_labels
- Called automatically after `AssignProductionDomainAction`

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
