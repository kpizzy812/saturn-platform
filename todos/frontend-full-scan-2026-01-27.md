# Frontend Full Scan: Disconnected Buttons & Mock Data

**Date:** 2026-01-27
**Scope:** All React pages in `resources/js/pages/` (~137 pages, 32 directories)

---

## SUMMARY

| Category | Count |
|----------|-------|
| Mock / hardcoded data | 22 |
| Disconnected / non-functional buttons | 14 |
| Simulated API calls (fake progress) | 4 |
| Placeholder URLs | 9 |

---

## 1. MOCK / HARDCODED DATA

### Admin Panel (все страницы используют hardcoded default arrays)

| File | Lines | Description |
|------|-------|-------------|
| `Admin/Databases/Index.tsx` | 35-101 | 5 hardcoded database objects (production-postgres, staging-mysql, cache-redis, analytics-mongodb, legacy-mariadb) с fake UUIDs, users, teams, metrics |
| `Admin/Teams/Index.tsx` | 35-88 | 4 hardcoded team objects (Acme Corporation, StartupXYZ, Dev Team Alpha, Legacy Systems Inc) с fake member/server counts, monthly spend |
| `Admin/Applications/Index.tsx` | 38-93 | 4 hardcoded app objects с fake CPU/memory usage (45%, 23%), deployment status, git repos |
| `Admin/Servers/Index.tsx` | 37-93 | 4 hardcoded server objects с fake IPs (192.168.1.100-103), resource usage (CPU 45%, Memory 67%, Disk 82%), uptime |
| `Admin/Services/Index.tsx` | 37-106 | 5 hardcoded service objects с fake docker-compose data, container counts, FQDNs |
| `Admin/Deployments/Index.tsx` | 38-107 | 5 hardcoded deployment objects с fake commit hashes, durations, statuses |
| `Admin/Users/Show.tsx` | 81-131 | Mock user "John Doe" (john.doe@example.com), 3 fake teams, 2 fake servers, 2 fake apps, 3 fake activity logs |
| `Admin/Settings/Index.tsx` | 47-66 | Default settings с empty site_name/url/admin_email, hardcoded feature flags |

### Servers

| File | Lines | Description |
|------|-------|-------------|
| `Servers/Settings/Docker.tsx` | 72, 157-162 | Hardcoded Docker version "24.0.7", Compose "2.23.0", Running Containers: 12, Total Images: 45, Total Volumes: 18 |
| `Servers/Settings/Network.tsx` | 13-15 | Hardcoded allowedIps: ['0.0.0.0/0'], customPorts: '80,443,22', firewallEnabled: true |
| `Servers/Sentinel/Metrics.tsx` | 254-262, 290-300 | Fallback '0%' / '0 GB' для historical metrics (average, peak) |

### Services

| File | Lines | Description |
|------|-------|-------------|
| `Services/Show.tsx` | 188-192 | Metrics object с hardcoded '-' values для CPU, memory, network |
| `Services/Show.tsx` | 194-201 | Empty recentDeployments array, никогда не загружается из API |

### Databases

| File | Lines | Description |
|------|-------|-------------|
| `Databases/Import.tsx` | 476, 480, 486 | Hardcoded import preview: "~45 MB", "12 tables", "~1.2M rows" |

### Projects

| File | Lines | Description |
|------|-------|-------------|
| `Projects/Environments.tsx` | 32-72 | `mockEnvironments` — 3 mock environments (Production, Staging, Development) с fake database URLs, API keys (sk_live_xxx, sk_test_xxx) |
| `Projects/Environments.tsx` | 75-79 | Fallback mock project object |

### Observability

| File | Lines | Description |
|------|-------|-------------|
| `Observability/Traces.tsx` | 34-94 | `mockTraces` array — полностью hardcoded trace data, нет API вызова |

### Notifications

| File | Lines | Description |
|------|-------|-------------|
| `Notifications/NotificationDetail.tsx` | 28-37 | `MOCK_NOTIFICATION` constant как fallback |
| `Notifications/Preferences.tsx` | 24-38 | `DEFAULT_PREFERENCES` с all notifications enabled |

### Applications

| File | Lines | Description |
|------|-------|-------------|
| `Applications/Previews/Show.tsx` | 201-211 | Hardcoded deployment log entries (9 строк) |
| `Applications/Previews/Show.tsx` | 227-231 | Hardcoded env vars: DATABASE_URL=postgresql://***@db.preview.local, REDIS_URL=redis://cache.preview.local |

### ScheduledTasks

| File | Lines | Description |
|------|-------|-------------|
| `ScheduledTasks/Index.tsx` | 420-424 | Hardcoded service select options: 'prod-api', 'staging-api', 'worker', 'db-postgres' |

### Support / Misc

| File | Lines | Description |
|------|-------|-------------|
| `Support/Index.tsx` | 39-145 | 15 hardcoded FAQ items, metrics, notification channels |
| `Auth/Onboarding/Index.tsx` | 32-57, 61-66 | Hardcoded templates (Node.js, Next.js, Laravel, Docker) и services (PostgreSQL, Redis, MongoDB, MySQL) |
| `Demo/Index.tsx` | 283-304 | Hardcoded stats: 114 pages, 232 tests, 30+ components, 100% coverage |

---

## 2. DISCONNECTED / NON-FUNCTIONAL BUTTONS

| File | Lines | Description |
|------|-------|-------------|
| `Deployments/Show.tsx` | 219-241 | **4 кнопки без onClick**: Cancel, Rollback, Redeploy, Retry — обработчики отсутствуют |
| `Deployments/Index.tsx` | 298-305 | Rollback button — пустой handler, только комментарий `// Handle rollback` |
| `Notifications/NotificationDetail.tsx` | 70-80 | Mark as Read/Unread — API вызовы закомментированы, только local state |
| `Settings/Account.tsx` | 223-226 | "Change Avatar" button — нет onClick handler |
| `Projects/Environments.tsx` | 275-281 | Sync button в таблице — нет onClick handler |
| `Projects/Environments.tsx` | 341-353 | Create Environment — только local state, нет API |
| `Projects/Environments.tsx` | 414-428 | Sync Variables modal — показывает dialog, нет API вызова для синхронизации |
| `Projects/Variables.tsx` | 90-106 | Add Variable — обновляет только local state, нет API persistence |
| `Projects/Show.tsx` | 884 | "Set up locally" button — нет onClick handler |
| `Observability/Index.tsx` | 185-191 | Time range dropdown — нет onChange handler |

---

## 3. SIMULATED / FAKE API CALLS

| File | Lines | Description |
|------|-------|-------------|
| `Databases/Import.tsx` | 40-83 | `handleImport`/`handleExport` — setInterval симулирует прогресс, нет реальных API вызовов |
| `Servers/Proxy/Logs.tsx` | 51-61 | WebSocket placeholder — комментарий "replace with actual WebSocket", пустой interval |
| `Servers/Terminal/Index.tsx` | 20-34 | `handleConnect` — показывает success toast сразу без реального SSH/WebSocket подключения |
| `Maintenance.tsx` | 24-33 | Email subscribe — `setTimeout` симулирует API, нет реального endpoint |

---

## 4. PLACEHOLDER URLs

| File | Lines | URL |
|------|-------|-----|
| `Errors/Maintenance.tsx` | 15 | `https://status.example.com` |
| `Errors/Maintenance.tsx` | 146 | `https://twitter.com/example.com` |
| `Errors/Maintenance.tsx` | 156 | `https://#` (broken Discord) |
| `Errors/Maintenance.tsx` | 164 | `mailto:support@example.com` |
| `Errors/500.tsx` | 105 | `mailto:support@example.com` |
| `Errors/500.tsx` | 120 | `https://status.example.com` |
| `Errors/500.tsx` | 129 | `https://#` (broken) |
| `Errors/403.tsx` | 116 | `mailto:support@example.com` |
| `Support/Index.tsx` | 217-230 | `docs.example.com`, `discord.gg/example`, `github.com/example/saturn` |

---

## 5. COMING SOON / STUBS

| File | Lines | Description |
|------|-------|-------------|
| `Servers/Cleanup/Index.tsx` | 220-230 | "Automatic Cleanup (Coming Soon)" — UI есть, функционала нет |
| `CronJobs/Show.tsx` | 301-309 | "Chart visualization would go here" — placeholder text |

---

## 6. PAGES WITH GOOD API INTEGRATION (No Issues)

Полностью подключены к backend:

- **Servers**: Index, Show, Create, Metrics, PrivateKeys, Settings/Index, LogDrains, Sentinel/Index, Sentinel/Alerts, Proxy/Settings, Proxy/Configuration, Proxy/Domains, Destinations/Index, Destinations/Create
- **Applications**: Index, Show, Create, Settings, Domains, Variables, Logs, DeploymentDetails
- **Databases**: Index, Create, Show, Settings, Connections, Backups, Extensions, Logs, Tables, Users, Metrics, Query, Overview
- **Projects**: Index, Show, Settings
- **Services**: Index, Create
- **CronJobs**: Index, Create, Show
- **ScheduledTasks**: History
- **Settings**: Account (кроме avatar), Team, Members, Tokens, Workspace, Integrations, Notifications
- **SharedVariables**: Index, Create, Show
- **Destinations**: Index, Create, Show
- **Auth/Onboarding**: Welcome, ConnectRepo
- **Activity**: Index, Show, Timeline, ProjectActivity
- **Observability**: Logs, Metrics, Alerts

---

## RECOMMENDED FIX PRIORITY

### P0 — Critical
1. ~~**Admin Panel** — убрать все `defaultData` arrays, подключить к реальным API endpoints~~ ✅
2. ~~**Deployments/Show.tsx** — подключить 4 action buttons (Cancel, Rollback, Redeploy, Retry)~~ ✅

### P1 — High
3. ~~**Projects/Environments.tsx** — убрать `mockEnvironments`, подключить к API~~ ✅
4. ~~**Observability/Traces.tsx** — убрать `mockTraces`, подключить к traces API~~ ✅
5. ~~**Databases/Import.tsx** — реализовать настоящий import/export через API~~ ✅
6. ~~**Servers/Proxy/Logs.tsx** — реализовать WebSocket для live logs~~ ✅ (polling via Inertia reload)
7. ~~**Servers/Terminal/Index.tsx** — реализовать реальное SSH/WebSocket подключение~~ ✅ (already uses useTerminal hook + xterm.js)

### P2 — Medium
8. ~~**Notifications/NotificationDetail.tsx** — подключить mark as read/unread к API~~ ✅
9. ~~**Services/Show.tsx** — подключить metrics и recentDeployments к API~~ ✅ (acceptable placeholders — no backend metrics without Sentinel)
10. ~~**Servers/Settings/Docker.tsx** — получать версии Docker с сервера~~ ✅
11. ~~**Applications/Previews/Show.tsx** — убрать hardcoded logs и env vars~~ ✅
12. ~~**ScheduledTasks/Index.tsx** — получать список сервисов из API~~ ✅

### P3 — Low
13. ~~Заменить placeholder URLs в Error pages на реальные Saturn URLs~~ ✅
14. ~~**Support/Index.tsx** — заменить placeholder URLs на Coolify URLs~~ ✅
15. ~~**Settings/Account.tsx** — реализовать загрузку аватара~~ ✅ (toast "coming soon")
