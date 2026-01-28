# Project Settings Frontend: Stubs & Disconnected Functionality Audit

**Date:** 2026-01-28
**Scope:** `resources/js/pages/Projects/` and `resources/js/components/features/Projects/`

---

## 1. STUBS (Hardcoded / Non-functional UI)

### 1.1 DatabaseExtensionsTab — fully hardcoded data, buttons do nothing
- **File:** `resources/js/components/features/Projects/Tabs/Database/DatabaseExtensionsTab.tsx:13-22`
- **Problem:** PostgreSQL extensions list is hardcoded in the component (pgvector, postgis, pg_trgm, etc.). "Enable" and "Disable" buttons have **no onClick handlers** — they render but do nothing.
- **Backend ready:** Routes already exist:
  - `GET /_internal/databases/{uuid}/extensions` — fetch real extensions
  - `POST /_internal/databases/{uuid}/extensions/toggle` — toggle extension
- **Note:** The `service` prop is accepted but **unused** (renamed to `_service`).
- **Fix:** Connect to backend API, fetch real extensions list, wire up Enable/Disable buttons.

### 1.2 MetricsTab — "Request Stats" section is a stub
- **File:** `resources/js/components/features/Projects/Tabs/Application/MetricsTab.tsx:198-218`
- **Problem:** Shows `--` for Total Requests, Success Rate, and Avg Latency. Footer text says "Request metrics require application instrumentation". No API calls, no data source.
- **Fix:** Either implement request metrics collection or remove the section to avoid confusion.

### 1.3 DatabaseCredentialsTab — "Regenerate Password" button does nothing
- **File:** `resources/js/components/features/Projects/Tabs/Database/DatabaseCredentialsTab.tsx:184-187`
- **Problem:** `<Button>Regenerate Password</Button>` has **no onClick handler**. Purely visual.
- **Fix:** Implement password regeneration via API call or disable button with "Coming soon" tooltip.

### 1.4 Create.tsx — "Function" card (Coming Soon)
- **File:** `resources/js/pages/Projects/Create.tsx:48-55`
- **Problem:** Card "Function / Deploy serverless functions" has badge "Coming Soon". Backend route (`/projects/create/function`) just redirects back with flash message "Functions are coming soon!".
- **Fix:** Acceptable as-is for now. Remove when/if implementing serverless functions.

---

## 2. DISCONNECTED / UNREACHABLE FUNCTIONALITY

### 2.1 Show.tsx — "Staged Changes" banner is unreachable
- **File:** `resources/js/pages/Projects/Show.tsx:45` and lines `746-778`
- **Problem:** `hasStagedChanges` is initialized as `false` and is **never set to `true`** anywhere in the code. The banner "You have staged changes" with text "3 environment variables modified, 1 service configuration updated" is hardcoded and unreachable. The "Deploy Changes" button calls `handleDeployChanges` which deploys **all** applications in the environment — also unreachable dead code.
- **Fix:** Either implement staged changes tracking or remove the banner and related code.

### 2.2 Show.tsx — Undo/Redo system is prepared but unused
- **File:** `resources/js/pages/Projects/Show.tsx:82-192`
- **Problem:** `trackStateChange` function is created but never called (line 192: `void trackStateChange`). Comment: "prepared for future use but currently unused". Undo/Redo buttons render in the toolbar, but `canUndo`/`canRedo` are always `false` since history is never populated.
- **Fix:** Either wire up undo/redo to canvas state changes or remove buttons from toolbar to avoid dead UI.

### 2.3 Show.tsx — handleNodeClick uses wrong environment
- **File:** `resources/js/pages/Projects/Show.tsx:194-231`
- **Problem:** `handleNodeClick` searches for applications and databases in `project.environments?.[0]` (first environment), ignoring the currently selected `selectedEnv`. If the user switches environment via dropdown, clicking a node on the canvas will look for the resource in the **first** environment, not the selected one.
- **Fix:** Replace `project.environments?.[0]` with `selectedEnv` in `handleNodeClick`.

---

## 3. POTENTIALLY BROKEN LINKS

### 3.1 Settings.tsx — "/shared-variables" link may not exist
- **File:** `resources/js/pages/Projects/Settings.tsx:593-598`
- **Problem:** Link "All shared variables" points to `/shared-variables`. This route was not found in the routes directory.
- **Fix:** Verify route exists or remove/update link.

### 3.2 Settings.tsx — "/settings/notifications" link
- **File:** `resources/js/pages/Projects/Settings.tsx:669`
- **Problem:** Not verified whether this Inertia route exists and renders a React page.
- **Fix:** Verify route exists.

### 3.3 Settings.tsx — "/settings/team" link
- **File:** `resources/js/pages/Projects/Settings.tsx:704`
- **Problem:** Not verified whether this Inertia route exists and renders a React page.
- **Fix:** Verify route exists.

---

## 4. MINOR ISSUES

| Issue | File | Lines |
|-------|------|-------|
| `handleDeployChanges` deploys all apps in environment, not "staged changes" | Show.tsx | 428-456 |
| `handleNodeContextMenu` also uses `environments?.[0]` instead of `selectedEnv` | Show.tsx | 236-271 |
| MetricsTab error message says "Using cached/demo metrics" — misleading | MetricsTab.tsx | 105 |
| DatabaseDataTab fetches on every mount without debounce (race condition on quick tab switches) | DatabaseDataTab.tsx | 26, 43-45 |

---

## 5. SUMMARY TABLE

| Category | Count |
|----------|-------|
| Full stubs (hardcoded data, non-functional buttons) | 4 |
| Disconnected / unreachable functionality | 3 |
| Wrong environment reference bugs | 2 |
| Potentially broken links | 2-3 |

## 6. PRIORITY ORDER

1. **HIGH** — `handleNodeClick` / `handleNodeContextMenu` using wrong environment (bug)
2. **HIGH** — DatabaseExtensionsTab: backend ready, frontend not connected
3. **MEDIUM** — "Regenerate Password" button does nothing (confusing UX)
4. **MEDIUM** — "Staged Changes" banner + Undo/Redo dead code (cleanup)
5. **LOW** — Request Stats stub in MetricsTab
6. **LOW** — Broken links verification
7. **LOW** — "Function" Coming Soon (acceptable)
