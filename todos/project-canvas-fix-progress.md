# Project Canvas Fix - Progress Tracker

## Current Status: 🔄 In Progress (Phase 1+2+3+4 Complete)

**Started:** 2026-01-22
**Last Updated:** 2026-01-22
**Phase 1 Completed:** 2026-01-22
**Phase 2 Completed:** 2026-01-22
**Phase 3 Completed:** 2026-01-22
**Phase 4 Completed:** 2026-01-22

---

## Phase 1: Critical Stubs (console.log → API calls) — P0 ✅ COMPLETED

### Show.tsx Context Menu Handlers
- [x] Deploy app → `handleDeploy` with API call
- [x] Restart app → `handleRestart` with API call
- [x] Stop app → `handleStop` with API call
- [x] Delete app → `handleDelete` with API call + confirmation
- [x] Fix "Deploy Changes" button → `handleDeployChanges`

### ContextMenu.tsx Database Actions
- [x] Create Backup → `onCreateBackup` callback prop
- [x] Restore Backup → `onRestoreBackup` callback prop (opens backups tab)

### CommandPalette.tsx Actions
- [x] Deploy action → `onDeploy` callback prop
- [x] Restart action → `onRestart` callback prop
- [x] View Logs action → `onViewLogs` callback prop
- [x] Add Service action → `onAddService` callback prop + fallback href
- [x] Add Database action → fallback href `/databases/create`
- [x] Add Template action → fallback href `/templates`

---

## Phase 2: Buttons Without onClick — P1 ✅ COMPLETED

- [x] Cancel Deployment → `handleCancel` with confirmation
- [x] Create Dropdown items → onClick with router.visit()
  - [x] GitHub Repo → `/applications/create?source=github`
  - [x] Docker Image → `/applications/create?source=docker`
  - [x] Database → `/databases/create`
  - [x] Empty Service → `/services/create`
  - [x] Template → `/templates`
- [x] Replicas ± buttons → state + `handleReplicasChange`
- [x] Delete domain → onClick with confirmation
- [x] Add Custom Domain → onClick with alert (modal coming soon)
- [x] Create Backup button → `handleCreateBackup` with API call
- [x] Schedule backup → `handleScheduleBackup`
- [x] Add env variable → `handleAddVariable`
- [x] Copy buttons → `handleCopyVariable` with clipboard
- [x] Deploy Now → `handleDeploy` with API call
- [x] Restart/Redeploy/Rollback/Remove → handlers added

---

## Phase 3: Missing Routes — P1 ✅ COMPLETED

- [x] Add `GET /projects/{uuid}/settings` route
- [x] Add `PATCH /projects/{uuid}` route
- [x] Add `DELETE /projects/{uuid}` route
- [x] Create `resources/js/pages/Projects/Settings.tsx`
- [x] Routes added inline in web.php (no controller methods needed)

---

## Phase 4: Copy Buttons — P2 ✅ COMPLETED

- [x] Copy env variable → `handleCopyVariable` (already in Phase 2)
- [x] Copy URL in service panel header
- [x] Copy connection string (Private Network)
- [x] Copy public hostname
- [x] Copy username/password in DatabaseCredentialsTab

---

## Phase 5: Input/Toggle Saving — P2

- [ ] Cron Schedule toggle + input (Show.tsx:1509-1525)
- [ ] Health Check toggle + inputs (Show.tsx:1545-1579)

---

## Phase 6: Real Data (Replace Mocks) — P3

- [ ] LogsViewer.tsx → WebSocket real logs
- [ ] MetricsTab → useSentinelMetrics hook
- [ ] Database Panels → real credentials from API
- [ ] Environments.tsx → real data
- [ ] Variables.tsx → real data

---

## Files Modified

| File | Status | Notes |
|------|--------|-------|
| `resources/js/pages/Projects/Show.tsx` | ✅ | Added API handlers, uuid support |
| `resources/js/components/features/ContextMenu.tsx` | ✅ | Added uuid, backup callbacks |
| `resources/js/components/features/CommandPalette.tsx` | ✅ | Added action callbacks |
| `routes/web.php` | ✅ | Added settings/update/delete routes |
| `resources/js/pages/Projects/Settings.tsx` | ✅ | Created with full CRUD functionality |

---

## Notes

- All API hooks already exist in `resources/js/hooks/`
- Backend API endpoints are ready
- Main work is connecting UI to existing hooks

---

## Verification Checklist

After completion:
- [x] Right-click on app → all actions work
- [x] Right-click on DB → all actions work
- [x] `/projects/{uuid}/settings` → page opens ✅
- [x] Cancel deployment → works
- [x] Create dropdown → all items navigate correctly
- [x] Copy buttons → copy to clipboard ✅
- [ ] Toggle Cron/Health → saves to backend (Phase 5)
- [ ] LogsViewer → shows real logs (Phase 6)
- [x] `./vendor/bin/pint && npm run build` → no errors ✅
