# Project Canvas Fix - Progress Tracker

## Current Status: 🔄 In Progress (Phase 1 Complete)

**Started:** 2026-01-22
**Last Updated:** 2026-01-22
**Phase 1 Completed:** 2026-01-22

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

## Phase 2: Buttons Without onClick — P1

- [ ] Cancel Deployment (Show.tsx:1035-1040)
- [ ] Create Dropdown items (Show.tsx:619-677)
  - [ ] GitHub Repo
  - [ ] Docker Image
  - [ ] Database
  - [ ] Empty Service
  - [ ] Template
- [ ] Replicas ± buttons (Show.tsx:1491-1498)
- [ ] Delete domain (Show.tsx:1386)
- [ ] Add Custom Domain (Show.tsx:1390-1393)
- [ ] Create Table (Show.tsx:1618-1621)
- [ ] Create Backup button (Show.tsx:1806)
- [ ] Schedule backup (Show.tsx:1810)
- [ ] Add env variable (Show.tsx:1121-1124)

---

## Phase 3: Missing Routes — P1

- [ ] Add `GET /projects/{uuid}/settings` route
- [ ] Add `PATCH /projects/{uuid}` route
- [ ] Add `DELETE /projects/{uuid}` route
- [ ] Create `resources/js/pages/Projects/Settings.tsx`
- [ ] Add methods to ProjectController

---

## Phase 4: Copy Buttons — P2

- [ ] Copy env variable (Show.tsx:1133-1135)
- [ ] Copy URL (Show.tsx:1371)
- [ ] Copy connection string (Show.tsx:1430)
- [ ] Copy hostname (Show.tsx:1449)
- [ ] Copy password (Show.tsx:1503, 1578)

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
| `routes/web.php` | ⏳ | Pending |
| `resources/js/pages/Projects/Settings.tsx` | ⏳ | To create |

---

## Notes

- All API hooks already exist in `resources/js/hooks/`
- Backend API endpoints are ready
- Main work is connecting UI to existing hooks

---

## Verification Checklist

After completion:
- [ ] Right-click on app → all actions work
- [ ] Right-click on DB → all actions work
- [ ] `/projects/{uuid}/settings` → page opens
- [ ] Cancel deployment → works
- [ ] Create dropdown → all items navigate correctly
- [ ] Copy buttons → copy to clipboard
- [ ] Toggle Cron/Health → saves to backend
- [ ] LogsViewer → shows real logs
- [ ] `./vendor/bin/pint && npm run build` → no errors
