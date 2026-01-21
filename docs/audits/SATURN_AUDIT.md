# Saturn Full Project Audit

## Executive Summary

| Component | Count | Status |
|-----------|-------|--------|
| **Backend API Endpoints** | 130+ | Ready to use |
| **Laravel Controllers** | 14 | Ready |
| **Background Jobs** | 49 | Working |
| **Broadcast Events** | 20 | 15 WebSocket, 5 internal |
| **Eloquent Models** | 60+ | Complete |
| **Livewire Components** | 187 | To be replaced |
| **Blade Views** | 150+ | To be replaced |
| **React Pages** | 119 | 70+ have mocks |
| **React Components** | 34 | Ready for API |
| **Web Routes** | 1154 lines | Hybrid Livewire/Inertia |
| **API Routes** | 219 lines | v1 with Sanctum |

---

## 1. Backend API (130+ Endpoints)

### 1.1 API Structure

- **Version**: v1
- **Base URL**: `/api/v1`
- **Auth**: Laravel Sanctum + Custom Middleware
- **Abilities**: `read`, `write`, `deploy`, `root`, `read:sensitive`

### 1.2 Endpoints by Category

#### Teams
```
GET    /v1/teams                        [read]
GET    /v1/teams/current                [read]
GET    /v1/teams/current/members        [read]
GET    /v1/teams/{id}                   [read]
GET    /v1/teams/{id}/members           [read]
```

#### Projects
```
GET    /v1/projects                     [read]
GET    /v1/projects/{uuid}              [read]
GET    /v1/projects/{uuid}/environments [read]
GET    /v1/projects/{uuid}/{env}        [read]
POST   /v1/projects                     [write]
POST   /v1/projects/{uuid}/environments [write]
PATCH  /v1/projects/{uuid}              [write]
DELETE /v1/projects/{uuid}              [write]
DELETE /v1/projects/{uuid}/environments/{env} [write]
```

#### Applications
```
GET    /v1/applications                 [read]
GET    /v1/applications/{uuid}          [read]
GET    /v1/applications/{uuid}/logs     [read]
GET    /v1/applications/{uuid}/envs     [read]
POST   /v1/applications/public          [write]
POST   /v1/applications/private-github-app [write]
POST   /v1/applications/private-deploy-key [write]
POST   /v1/applications/dockerfile      [write]
POST   /v1/applications/dockerimage     [write]
POST   /v1/applications/dockercompose   [write]
PATCH  /v1/applications/{uuid}          [write]
DELETE /v1/applications/{uuid}          [write]
POST   /v1/applications/{uuid}/envs     [write]
PATCH  /v1/applications/{uuid}/envs     [write]
PATCH  /v1/applications/{uuid}/envs/bulk [write]
DELETE /v1/applications/{uuid}/envs/{env_uuid} [write]
GET|POST /v1/applications/{uuid}/start  [write]
GET|POST /v1/applications/{uuid}/restart [write]
GET|POST /v1/applications/{uuid}/stop   [write]
```

#### Deployments
```
GET    /v1/deployments                  [read]
GET    /v1/deployments/{uuid}           [read]
GET    /v1/deployments/applications/{uuid} [read]
POST   /v1/deployments/{uuid}/cancel    [deploy]
GET|POST /v1/deploy                     [deploy]
```

#### Databases
```
GET    /v1/databases                    [read]
GET    /v1/databases/{uuid}             [read]
GET    /v1/databases/{uuid}/backups     [read]
GET    /v1/databases/{uuid}/backups/{backup_uuid}/executions [read]
POST   /v1/databases/postgresql         [write]
POST   /v1/databases/mysql              [write]
POST   /v1/databases/mariadb            [write]
POST   /v1/databases/mongodb            [write]
POST   /v1/databases/redis              [write]
POST   /v1/databases/clickhouse         [write]
POST   /v1/databases/dragonfly          [write]
POST   /v1/databases/keydb              [write]
PATCH  /v1/databases/{uuid}             [write]
DELETE /v1/databases/{uuid}             [write]
POST   /v1/databases/{uuid}/backups     [write]
PATCH  /v1/databases/{uuid}/backups/{backup_uuid} [write]
DELETE /v1/databases/{uuid}/backups/{backup_uuid} [write]
GET|POST /v1/databases/{uuid}/start     [write]
GET|POST /v1/databases/{uuid}/restart   [write]
GET|POST /v1/databases/{uuid}/stop      [write]
```

#### Servers
```
GET    /v1/servers                      [read]
GET    /v1/servers/{uuid}               [read]
GET    /v1/servers/{uuid}/resources     [read]
GET    /v1/servers/{uuid}/domains       [read]
GET    /v1/servers/{uuid}/validate      [read]
POST   /v1/servers                      [write]
PATCH  /v1/servers/{uuid}               [write]
DELETE /v1/servers/{uuid}               [write]
```

#### Services (Docker Compose)
```
GET    /v1/services                     [read]
GET    /v1/services/{uuid}              [read]
GET    /v1/services/{uuid}/envs         [read]
POST   /v1/services                     [write]
PATCH  /v1/services/{uuid}              [write]
DELETE /v1/services/{uuid}              [write]
POST   /v1/services/{uuid}/envs         [write]
PATCH  /v1/services/{uuid}/envs         [write]
PATCH  /v1/services/{uuid}/envs/bulk    [write]
DELETE /v1/services/{uuid}/envs/{env_uuid} [write]
GET|POST /v1/services/{uuid}/start      [write]
GET|POST /v1/services/{uuid}/restart    [write]
GET|POST /v1/services/{uuid}/stop       [write]
```

#### Security & Cloud
```
GET    /v1/security/keys                [read]
POST   /v1/security/keys                [write]
GET    /v1/security/keys/{uuid}         [read]
PATCH  /v1/security/keys/{uuid}         [write]
DELETE /v1/security/keys/{uuid}         [write]

GET    /v1/cloud-tokens                 [read]
POST   /v1/cloud-tokens                 [write]
GET    /v1/cloud-tokens/{uuid}          [read]
PATCH  /v1/cloud-tokens/{uuid}          [write]
DELETE /v1/cloud-tokens/{uuid}          [write]
POST   /v1/cloud-tokens/{uuid}/validate [read]
```

#### GitHub Integration
```
GET    /v1/github-apps                  [read]
POST   /v1/github-apps                  [write]
PATCH  /v1/github-apps/{id}             [write]
DELETE /v1/github-apps/{id}             [write]
GET    /v1/github-apps/{id}/repositories [read]
GET    /v1/github-apps/{id}/repositories/{owner}/{repo}/branches [read]
```

#### Hetzner Cloud
```
GET    /v1/hetzner/locations            [read]
GET    /v1/hetzner/server-types         [read]
GET    /v1/hetzner/images               [read]
GET    /v1/hetzner/ssh-keys             [read]
POST   /v1/servers/hetzner              [write]
```

---

## 2. Background Jobs (49 Total)

### Critical Jobs (Deployment)
| Job | Description |
|-----|-------------|
| `ApplicationDeploymentJob` | Main deployment job (git, build, push) |
| `DatabaseBackupJob` | Create database backups |
| `DeleteResourceJob` | Delete application/database with artifacts |
| `ValidateAndInstallServerJob` | Server validation, Docker install |

### Infrastructure Jobs
| Job | Description |
|-----|-------------|
| `ServerCheckJob` | Check server/container status (60s timeout) |
| `ServerConnectionCheckJob` | SSH connection check |
| `ServerStorageCheckJob` | Disk space check |
| `ServerManagerJob` | Server lifecycle management |
| `RestartProxyJob` | Restart Traefik/Caddy |
| `CheckTraefikVersionJob` | Traefik version check |

### Notification Jobs
| Job | Description |
|-----|-------------|
| `SendMessageToSlackJob` | Slack notifications |
| `SendMessageToDiscordJob` | Discord notifications |
| `SendMessageToTelegramJob` | Telegram notifications |
| `SendWebhookJob` | Webhook payloads |

### Cleanup Jobs
| Job | Description |
|-----|-------------|
| `DockerCleanupJob` | Cleanup unused Docker resources |
| `CleanupHelperContainersJob` | Remove helper containers |
| `CleanupOrphanedPreviewContainersJob` | Remove orphaned previews |

---

## 3. Broadcast Events (20 Total)

### WebSocket Events (Team Channel)
| Event | Channel | Purpose |
|-------|---------|---------|
| `ApplicationStatusChanged` | `team.{teamId}` | App running/stopped |
| `ApplicationConfigurationChanged` | `team.{teamId}` | Config updated |
| `ServiceStatusChanged` | `team.{teamId}` | Service status |
| `ServiceChecked` | `team.{teamId}` | Service verified |
| `DatabaseProxyStopped` | `team.{teamId}` | DB proxy stopped |
| `ProxyStatusChangedUI` | `team.{teamId}` | Proxy status for UI |
| `ServerValidated` | `team.{teamId}` | Server validated |
| `ServerPackageUpdated` | `team.{teamId}` | OS packages updated |
| `SentinelRestarted` | `team.{teamId}` | Sentinel restarted |
| `ScheduledTaskDone` | `team.{teamId}` | Cron task completed |
| `BackupCreated` | `team.{teamId}` | Backup created |
| `FileStorageChanged` | `team.{teamId}` | File storage changed |
| `CloudflareTunnelConfigured` | `team.{teamId}` | CF tunnel configured |

### WebSocket Events (User Channel)
| Event | Channel | Purpose |
|-------|---------|---------|
| `DatabaseStatusChanged` | `user.{userId}` | DB status changed |

### Internal Events (No Broadcast)
| Event | Purpose |
|-------|---------|
| `ServerReachabilityChanged` | Server online/offline |
| `ProxyStatusChanged` | Internal proxy status |
| `CloudflareTunnelChanged` | CF tunnel changed |
| `RestoreJobFinished` | Backup restore done |
| `S3RestoreJobFinished` | S3 restore done |

---

## 4. Eloquent Models (60+)

### Core Models
| Model | Size | Relationships |
|-------|------|---------------|
| `Application` | 74KB | Server, PrivateKey, Environment, Settings, Previews, Deployments |
| `Server` | 46KB | PrivateKey, Team, Settings, Services, Containers |
| `Service` | 58KB | ServiceApplications, ServiceDatabases, Environment |
| `Project` | Medium | Environments, Settings, Team |
| `Environment` | Medium | Applications, Databases, Services, Project |
| `Team` | Medium | Projects, Servers, PrivateKeys, Members, Notifications |
| `User` | Medium | Teams, Tokens, AuditLogs |

### Database Models
- `StandalonePostgresql`
- `StandaloneMysql`
- `StandaloneMariadb`
- `StandaloneMongodb`
- `StandaloneRedis`
- `StandaloneClickhouse`
- `StandaloneDragonfly`
- `StandaloneKeydb`

### Integration Models
- `GithubApp`
- `GitlabApp`
- `CloudProviderToken`
- `S3Storage`
- `PrivateKey`
- `SslCertificate`

---

## 5. Livewire Components (187 Total)

### By Category
| Category | Count | Key Components |
|----------|-------|----------------|
| Project | 69 | General, Source, Deployment, Previews |
| Application | 25 | Configuration, Advanced, Environment |
| Database | 18 | General (per type), Backups, Import |
| Server | 22 | Show, Proxy, Sentinel, Charts |
| Service | 12 | Configuration, StackForm, EditCompose |
| Settings | 8 | Email, OAuth, Updates |
| Notifications | 6 | Discord, Slack, Telegram, Email |
| Security | 6 | APITokens, PrivateKeys, CloudTokens |
| Team | 6 | Members, Invitations, Storage |
| Shared | 15 | Logs, Tags, EnvironmentVariables |

### With WebSocket Listeners (62 components)
```php
public function getListeners() {
    return [
        "echo-private:team.{$teamId},ServiceChecked" => '$refresh',
        "echo-private:user.{$userId},DatabaseStatusChanged" => '$refresh',
    ];
}
```

### Key Patterns
```php
// Data sync pattern
public function syncData(bool $toModel = false): void

// Instant save pattern
public function instantSave(): void

// Authorization pattern
use AuthorizesRequests;
$this->authorize('update', $resource);
```

---

## 6. React Frontend (119 Pages)

### Pages with Real Data (Working)
| Page | Data Source |
|------|-------------|
| Auth/* (Login, Register, 2FA) | Inertia forms |
| Projects/Index | Inertia props |
| Databases/Index | Inertia props |
| Databases/Create | router.post |
| Servers/Index | Inertia props |
| Domains/* | router.post/delete |

### Pages with Mock Data (70+ files)
| Page | Mock Type |
|------|-----------|
| Dashboard | Hardcoded projects array |
| Deployments/Index | MOCK_DEPLOYMENTS (150 lines) |
| Deployments/BuildLogs | Mock log data |
| Activity/* | MOCK_ACTIVITY, MOCK_RELATED |
| Services/Show | mockService object |
| Services/Logs | setInterval simulation |
| Services/Variables | mockVariables array |
| Services/HealthChecks | mockHealthHistory |
| Databases/Show | Mock connection details |
| Databases/Query | Mock query results |
| Databases/Backups | Mock backups |
| Observability/* | Mock metrics, logs, alerts |
| Volumes/* | Mock volumes, snapshots |
| CronJobs/* | Mock cron data |
| Templates/* | Mock templates |
| Settings/* | Mock settings data |
| Notifications/* | Mock notifications |

### Mock Patterns Found
```typescript
// Pattern 1: Hardcoded state
const [items] = useState<Item[]>(mockItems);

// Pattern 2: Mock data objects
const MOCK_DATA: Type[] = [...];

// Pattern 3: setTimeout simulation
setTimeout(() => { setResult(mockResult); }, 1000);

// Pattern 4: setInterval for logs
const interval = setInterval(() => { addLog(); }, 500);
```

---

## 7. React Components (34 Total)

### UI Components (Ready for API)
- Button, Card, Input, Badge, Modal
- Dropdown, Checkbox, Select, Tabs
- Toast, Spinner, Progress, Slider, Chart
- CommandPalette, SaturnLogo

### Feature Components
- LogsViewer (has setInterval simulation)
- ContextMenu
- DatabaseCard
- ServiceNode, DatabaseNode

### Layout Components
- AppLayout, AuthLayout
- Header, Sidebar

---

## 8. Routing Architecture

### Web Routes (1154 lines)
- **Livewire Routes**: `/projects/*`, `/servers/*`, `/settings/*`
- **Inertia Routes**: `/new/*` prefix for React pages
- **Auth Routes**: Login, Register, OAuth, 2FA
- **Terminal Routes**: with `can.access.terminal` middleware

### API Routes (219 lines)
- **Version**: v1
- **Auth**: Sanctum tokens
- **Middleware**: `ApiAllowed`, `api.ability:{read|write|deploy}`

### Webhook Routes (22 lines)
- GitHub, GitLab, Bitbucket, Gitea events
- Stripe payment webhooks

### Broadcasting Channels
- `team.{teamId}` - Team-scoped events
- `user.{userId}` - User-specific events

---

## 9. Middleware Stack

| Middleware | Purpose |
|------------|---------|
| `auth:sanctum` | API token validation |
| `ApiAllowed` | IP whitelist for API |
| `api.ability` | Token abilities (read, write, deploy) |
| `api.sensitive` | Sensitive data access flag |
| `can.access.terminal` | Terminal access (admins only) |
| `is.superadmin` | Super admin check |
| `HandleInertiaRequests` | Inertia SSR support |
| `CheckForcePasswordReset` | Force password reset |

---

## 10. Migration Priority

### P0 - Critical (Connect First)
1. **Deployments** → `/api/v1/deployments`
2. **Real-time Logs** → WebSocket streaming
3. **Start/Stop/Restart** → `/api/v1/applications/{uuid}/start|stop|restart`
4. **Application Status** → `ApplicationStatusChanged` event

### P1 - Important
1. **Activity Feed** → Create new API endpoint
2. **Notifications** → Create new API endpoint
3. **Environment Variables** → `/api/v1/applications/{uuid}/envs`
4. **Database Operations** → `/api/v1/databases/*`

### P2 - Nice to Have
1. Settings pages
2. Templates gallery
3. Observability (Metrics, Traces)
4. Cron Jobs
5. Volumes

---

## 11. React → API → Livewire Mapping

| React Page | API Endpoint | Livewire Component |
|------------|--------------|-------------------|
| Dashboard | `/api/v1/projects` | Dashboard.php |
| Projects/Show | `/api/v1/projects/{uuid}` | Project/Show.php |
| Services/Show | `/api/v1/applications/{uuid}` | Application/General.php |
| Services/Logs | `/api/v1/applications/{uuid}/logs` | Project/Shared/Logs.php |
| Services/Variables | `/api/v1/applications/{uuid}/envs` | Shared/EnvironmentVariable/All.php |
| Deployments/Index | `/api/v1/deployments` | Application/Deployment/Index.php |
| Databases/Show | `/api/v1/databases/{uuid}` | Database/*/General.php |
| Databases/Backups | `/api/v1/databases/{uuid}/backups` | Database/Backup/Index.php |
| Servers/Show | `/api/v1/servers/{uuid}` | Server/Show.php |

---

## 12. Technical Notes

### WebSocket Integration (React)
```javascript
Echo.private(`team.${teamId}`)
    .listen('ApplicationStatusChanged', (e) => {
        // Update UI status
    })
    .listen('ServiceStatusChanged', (e) => {
        // Update service status
    });
```

### API Authentication
- Bearer token via Sanctum
- Abilities: `read`, `write`, `deploy`, `sensitive`

### Inertia.js Pattern
```javascript
import { router, useForm } from '@inertiajs/react';

const { data, post, processing } = useForm({ name: '' });
post('/api/v1/projects');
```

---

## 13. Files to Replace

### Remove (After Migration)
- `app/Livewire/` - 187 components
- `resources/views/livewire/` - 150+ Blade views
- Old Blade layouts and partials

### Keep
- `app/Http/Controllers/Api/` - All API controllers
- `app/Jobs/` - All background jobs
- `app/Events/` - All broadcast events
- `app/Models/` - All Eloquent models
- `routes/api.php` - API routes

### Update
- `routes/web.php` - Remove Livewire routes, keep Inertia
- `app/Http/Middleware/HandleInertiaRequests.php` - Add shared props

---

---

## 14. Testing Requirements

### Current Test Status
- **428 tests passing** (Vitest)
- Component tests for UI elements
- Page tests for key pages

### Tests Needed

#### Unit Tests (Vitest)
| Category | Tests Needed |
|----------|--------------|
| **API Hooks** | useDeployments, useApplications, useServers, useDatabases |
| **WebSocket Hooks** | useRealtimeStatus, useLogStream |
| **Data Transformers** | formatDeployment, formatApplication, formatDatabase |
| **Utilities** | Date formatting, status parsing, error handling |

#### Component Tests (React Testing Library)
| Component | Tests Needed |
|-----------|--------------|
| DeploymentCard | Render states, click actions, status updates |
| ApplicationStatus | All status variants, animations |
| LogsViewer | Stream handling, filters, search |
| EnvironmentVariables | CRUD operations, validation |
| DatabaseCard | Connection info, actions |
| ServiceNode | Canvas interactions, status |

#### Integration Tests
| Feature | Tests Needed |
|---------|--------------|
| Deployment Flow | Create → Build → Deploy → Status |
| Database Operations | Create → Configure → Backup → Restore |
| Server Management | Add → Validate → Configure → Monitor |
| Environment Variables | Add → Edit → Delete → Bulk |

#### E2E Tests (Playwright)
| Flow | Tests Needed |
|------|--------------|
| Authentication | Login, Register, 2FA, OAuth |
| Project Creation | New project → Add service → Deploy |
| Database Setup | Create → Configure → Connect |
| Server Setup | Add server → Validate → Deploy app |

### Test File Structure
```
tests/
├── Frontend/
│   ├── components/
│   │   ├── ui/           # UI component tests (existing)
│   │   ├── features/     # Feature component tests
│   │   └── canvas/       # Canvas component tests
│   ├── pages/            # Page tests
│   ├── hooks/            # Hook tests (new)
│   ├── api/              # API integration tests (new)
│   └── utils/            # Utility tests
├── Unit/                 # PHP unit tests
├── Feature/              # PHP feature tests
└── E2E/                  # Playwright E2E tests (new)
```

### Priority
1. **P0**: API hook tests (useDeployments, useApplications)
2. **P0**: WebSocket integration tests
3. **P1**: Component tests for new features
4. **P1**: E2E tests for critical flows
5. **P2**: Full coverage for all pages

---

## 15. Completed Fixes (Session 2026-01-03)

### 15.1 Button Functionality Fixes (9 buttons)

| File | Button | Fix Applied |
|------|--------|-------------|
| `Services/Show.tsx` | Redeploy | Added `router.post()` to `/api/v1/services/{uuid}/start` |
| `Services/Show.tsx` | Restart | Added `router.post()` to `/api/v1/services/{uuid}/restart` |
| `Services/Show.tsx` | Delete | Added `router.delete()` with confirmation |
| `Services/Deployments.tsx` | Deploy Now | Added `router.post()` to start deployment |
| `Services/Deployments.tsx` | Rollback | Added rollback handler with commit param |
| `Databases/Show.tsx` | Restart | Added restart handler with loading state |
| `Projects/Show.tsx` | Add Service | Added navigation to `/new/services/new` |
| `Projects/Show.tsx` | Undo | Added canvas state history undo |
| `Projects/Show.tsx` | Redo | Added canvas state history redo |

### 15.2 Mock Fallback Removal

| File | Change |
|------|--------|
| `Dashboard.tsx` | Receives `projects` prop, shows empty state when no data |
| `Projects/Show.tsx` | Removed `mockProject`, added loading state |
| `Services/Show.tsx` | Removed `mockService`, added loading state |
| `Services/Deployments.tsx` | Removed mock array, fetches from API or uses props |

### 15.3 Canvas Edge Deletion

**File**: `ProjectCanvas.tsx`

Features added:
- Click on edge to select (highlights in purple)
- Right-click on edge shows context menu with "Delete Connection"
- Press `Delete` or `Backspace` to remove selected edge
- Press `Escape` to deselect
- Visual hint shows keyboard shortcuts when edge is selected
- `onEdgeDelete` callback prop for parent component

### 15.4 Database Logos

Added SVG logo components for:
- PostgreSQL (blue)
- Redis (red)
- MySQL (orange)
- MongoDB (green)
- MariaDB (amber)
- ClickHouse (yellow)
- KeyDB (rose)
- Dragonfly (purple)

Used in:
- `DatabaseCard.tsx` - Card icons
- `Projects/Show.tsx` - Right panel header

---

## 16. Session 2 Completed (2026-01-03 - Parallel Agents)

### 16.1 Mock Fallbacks Removed (20+ files)

| Category | Files Fixed |
|----------|-------------|
| Databases | Query.tsx, Tables.tsx |
| Observability | Index.tsx, Alerts.tsx |
| Settings | Team/Invite.tsx, Members/Show.tsx, Usage.tsx |
| ScheduledTasks | History.tsx, Index.tsx |
| CronJobs | Show.tsx, Index.tsx |
| Projects | LocalSetup.tsx, Variables.tsx |
| Services | Networking.tsx |
| Environments | Secrets.tsx, Variables.tsx |
| Volumes | Create.tsx |
| Domains | Redirects.tsx |

### 16.2 API Hooks Created (18 hooks, 4400+ lines)

**Location:** `resources/js/hooks/`

| Hook | Purpose | Mutations |
|------|---------|-----------|
| `useApplications` | List/manage apps | start, stop, restart, update |
| `useApplication` | Single app | same + delete |
| `useDeployments` | List deployments | startDeployment, cancelDeployment |
| `useDeployment` | Single deployment | cancel |
| `useDatabases` | List databases | create (8 types), restart, backup |
| `useDatabase` | Single database | update, delete, start, stop |
| `useDatabaseBackups` | Backup management | create, restore |
| `useServices` | List services | create, start, stop |
| `useService` | Single service | update, restart, delete |
| `useServiceEnvs` | Environment vars | CRUD, bulk update |
| `useProjects` | List projects | create, update, delete |
| `useProject` | Single project | update, delete |
| `useProjectEnvironments` | Environments | create, delete |
| `useServers` | List servers | create, validateServer |
| `useServer` | Single server | update, delete |
| `useServerResources` | Deployed resources | - |
| `useServerDomains` | Domain config | - |

**Documentation created:**
- `hooks/README.md` - Full API docs (16KB)
- `hooks/EXAMPLES.tsx` - 13 working examples
- `hooks/QUICK_REFERENCE.md` - Cheat sheet
- `hooks/API_HOOKS_SUMMARY.md` - Implementation guide

### 16.3 WebSocket Real-time Support

**Files created:**
- `lib/echo.ts` - Laravel Echo initialization
- `hooks/useRealtimeStatus.ts` - Status subscriptions
- `hooks/useLogStream.ts` - Log streaming

**Events supported:**
- ApplicationStatusChanged
- DatabaseStatusChanged
- ServiceStatusChanged
- ServerReachabilityChanged
- DeploymentCreated
- DeploymentFinished

**Features:**
- Auto-reconnection with exponential backoff
- Polling fallback (5s for status, 2s for logs)
- Auto-scroll, filtering, download for logs
- Memory-efficient (configurable max entries)

**Documentation:**
- `hooks/INTEGRATION_GUIDE.md` (14KB)
- `WEBSOCKET_IMPLEMENTATION.md` (14KB)
- `hooks/examples/DeploymentMonitor.example.tsx`

---

## 17. Remaining Issues (Re-Audit Results)

### P0 - Critical (Must Fix)

#### Alert() Calls to Replace with Toast (14 locations)
| File | Line | Current |
|------|------|---------|
| Services/Settings.tsx | 17-18 | alert('Settings saved!') |
| Services/Show.tsx | 46, 57 | alert('Failed to...') |
| Databases/Show.tsx | 33 | alert('Failed to restart') |
| Databases/Import.tsx | multiple | export/restore alerts |
| Databases/Connections.tsx | multiple | connection alerts |
| Databases/Settings.tsx | - | restart alerts |
| Services/Deployments.tsx | - | deployment failures |
| Services/Webhooks.tsx | - | webhook testing |
| Templates/Submit.tsx | - | form validation |

#### Buttons Without Handlers (5 locations)
| File | Button | Issue |
|------|--------|-------|
| Projects/Index.tsx:71 | MoreVertical | TODO comment |
| Databases/Index.tsx | MoreVertical | TODO comment |
| Servers/Index.tsx:97-99 | MoreVertical | empty onClick |
| Dashboard.tsx:84,91,96 | Dropdown items | e.preventDefault() only |
| Servers/Show.tsx:68-75 | Validate, Terminal | no handlers |

### P1 - High Priority

#### Placeholder UI Text (4 locations)
| File | Line | Text |
|------|------|------|
| Servers/Show.tsx | 197 | "Server logs will appear here..." |
| Databases/Show.tsx | 258 | "Historical metrics and charts..." |
| Databases/Show.tsx | 273 | "Backup configuration and history..." |
| Templates/Submit.tsx | 301 | "Template description..." |

#### Missing Loading States
- Services/Scaling.tsx:38-51 - handleApplyChanges() needs loading state

### P2 - Medium Priority

#### Missing Error State Handling
- Projects/Index.tsx - No error boundary
- Servers/Index.tsx - No error state UI
- Databases/Index.tsx - No error state UI
- Services/Show.tsx - Has handlers but just alerts

---

## 18. Feature Gap Analysis (Livewire vs React)

| Feature | Livewire Component | React Status |
|---------|-------------------|--------------|
| Global Search | GlobalSearch.php | CommandPalette exists |
| Real-time Notifications | ActivityMonitor.php | useRealtimeStatus ✅ |
| Admin Panel | Admin/Index.php | ❌ Missing |
| Interactive Terminal | Terminal/ | ❌ Placeholder only |
| Deployment Progress | DeploymentsIndicator.php | ⚠️ Partial |
| Storage/Backup UI | Storage/ | ⚠️ Minimal |
| Subscription/Billing | Subscription/ | ❌ Missing |
| Email Templates | SettingsEmail.php | ❌ Missing |
| OAuth Config | SettingsOauth.php | ❌ Missing |
| Upgrade Wizard | Upgrade.php | ❌ Missing |

---

## 19. Updated Remaining Work

### ✅ Completed
- [x] Fix 9 buttons without onClick
- [x] Remove mock fallbacks in key pages
- [x] Add edge deletion to canvas
- [x] Remove mock fallbacks in 20+ more pages
- [x] Create API hooks (18 hooks)
- [x] Add WebSocket subscriptions

### 🔄 In Progress
- [ ] Clean up Templates/Index.tsx and Show.tsx (leftover fragments)
- [ ] Replace alert() with toast (14 locations)
- [ ] Add dropdown handlers (5 buttons)
- [ ] Remove placeholder text (4 locations)

### 📋 Next Up
- [ ] Integrate API hooks into pages (replace fetch calls)
- [ ] Test WebSocket connection with Soketi
- [ ] Add error boundaries to pages
- [ ] Write tests for new hooks
- [ ] Implement missing features (Admin, Terminal, etc.)

---

*Last updated: 2026-01-03 - Session 2: Parallel agents completed API hooks, WebSocket, mock removal*
