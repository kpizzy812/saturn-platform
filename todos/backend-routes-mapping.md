# Backend Routes -> Frontend Pages Mapping

Quick reference for which Inertia pages have backend data and which still need it.

## Status Legend
- **DONE** - Backend passes real data via Inertia::render
- **EMPTY** - Frontend accepts props, backend passes empty/no data (needs backend)
- **DEFAULTS** - Uses default values in frontend (needs backend to pass real data)

---

## Activity Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Activity/Index | GET /activity | DONE | `activities` via ActivityHelper::getTeamActivities(50) |
| Activity/Timeline | GET /activity/timeline | DONE | `activities`, `currentPage`, `totalPages` |
| Activity/Show | GET /activity/{uuid} | DONE | `activity`, `relatedActivities` |
| Activity/ProjectActivity | GET /activity/project/{uuid} | DONE | `project`, `environments`, `activities` |

## Deployment Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Deployments/Index | GET /deployments | DONE | `deployments` from ApplicationDeploymentQueue |
| Deployments/Show | GET /deployments/{uuid} | DONE | `deployment` with build_logs, deploy_logs, duration |
| Deployments/BuildLogs | GET /deployments/{uuid}/logs | DONE | `deployment` (uuid, status, application_name) |

## Services Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Services/Show | GET /services/{uuid} | DONE | `service` model |
| Services/Variables | GET /services/{uuid}/variables | DONE | `service`, `variables` (from environment_variables()) |
| Services/Domains | GET /services/{uuid}/domains | DONE | `service`, `domains` (from service.applications FQDNs) |
| Services/BuildLogs | GET /services/{uuid}/build-logs | DONE | `service`, `buildSteps` (from Spatie activity log) |

## Storage Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Storage/Backups | GET /storage/backups | EMPTY | Needs backups list (no volume/backup model exists yet) |
| Storage/Snapshots | GET /storage/snapshots | EMPTY | Needs snapshots list (no snapshot model exists yet) |
| Storage/Create | POST /storage | DONE | S3Storage creation + test-connection |

## Volumes Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Volumes/Show | GET /volumes/{uuid} | DEFAULTS | `volume` passed, `snapshots` defaults to [] |

## Admin Pages (routes/web/admin.php)

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Admin/Index | GET /admin | DONE | `stats` (User/Server/Team/Deployment counts), `recentActivity` (Spatie), `healthChecks` (DB/Redis ping) |
| Admin/Users/Index | GET /admin/users | DONE | `users` paginated with teams |
| Admin/Users/Show | GET /admin/users/{id} | DONE | `user` with teams.projects.environments |
| Admin/Logs/Index | GET /admin/logs | DONE | `logs` parsed from laravel.log |
| Admin/Settings/Index | GET /admin/settings | DONE | `settings` from InstanceSettings |
| Admin/Applications/Index | GET /admin/applications | DONE | `applications` paginated |
| Admin/Databases/Index | GET /admin/databases | DONE | `databases` (all 8 types merged) |
| Admin/Services/Index | GET /admin/services | DONE | `services` paginated |
| Admin/Servers/Index | GET /admin/servers | DONE | `servers` paginated |
| Admin/Deployments/Index | GET /admin/deployments | DONE | `deployments` paginated |
| Admin/Teams/Index | GET /admin/teams | DONE | `teams` with member/project/server counts |

## Observability Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Observability/Metrics | GET /observability/metrics | DONE | `servers` list for Sentinel API |

---

## Still Needs Backend (future work)

- **Storage/Backups** and **Storage/Snapshots** — no Volume/Backup/Snapshot models exist in Coolify yet. These are placeholder pages for future functionality.
- **Volumes/Show** — `volume` model is passed but `snapshots` and `usageData` need real data when volume management is implemented.

## Key Backend Patterns

### Inertia Data Passing
```php
Route::get('/page', function () {
    return Inertia::render('Page/Name', [
        'data' => $queryResult,
    ]);
});
```

### Team-Scoped Queries
```php
$applications = Application::ownedByCurrentTeam()->get();
$servers = Server::ownedByCurrentTeam()->get();
```

### ActivityHelper Methods
- `ActivityHelper::getTeamActivities(int $limit)` - team activities
- `ActivityHelper::getActivity(string $uuid)` - single activity
- `ActivityHelper::getRelatedActivities(string $uuid)` - related activities

### ApplicationDeploymentQueue
- Has `deployment_uuid`, `status`, `commit`, `commit_message`, `logs` (JSON)
- Belongs to `Application` via `application_id`
- `application_name`, `server_name` fields for display
- `is_webhook`, `rollback` flags for trigger type detection

### Service Environment Variables
- `$service->environment_variables()` — morphMany to EnvironmentVariable
- `is_literal` flag — if false, value is interpolated (treated as secret)
- FQDNs stored on ServiceApplication, comma-separated

### Admin Route File
- All admin routes in `routes/web/admin.php`
- Prefix: `/admin`
- Includes real DB counts, Spatie activity log, health checks
