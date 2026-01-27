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
| Services/Variables | (tab in Show) | EMPTY | Needs env vars from `service.environment_variables()` |
| Services/Domains | (tab in Show) | EMPTY | Needs FQDNs from `service.applications().pluck('fqdn')` |
| Services/BuildLogs | (tab in Show) | EMPTY | Needs build logs from deployment queue |

**TODO:** Services tabs need data passed through the `service` prop with eager-loaded relationships, or fetched via API calls from the tab components.

## Storage Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Storage/Backups | GET /storage/backups | EMPTY | Needs backups list |
| Storage/Snapshots | GET /storage/snapshots | EMPTY | Needs snapshots list |
| Storage/Create | POST /storage | DONE | S3Storage creation + test-connection |

## Volumes Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Volumes/Show | GET /volumes/{uuid} | DEFAULTS | `volume` passed, `snapshots` defaults to [] |

## Admin Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Admin/Index | GET /admin | DEFAULTS | `stats`, `recentActivity`, `healthChecks` all default to empty |
| Admin/Users/Index | GET /admin/users | DEFAULTS | `users` defaults to [] |
| Admin/Users/Show | GET /admin/users/{id} | DEFAULTS | needs user data |
| Admin/Logs/Index | GET /admin/logs | DEFAULTS | `logs` defaults to [] |
| Admin/Settings/Index | GET /admin/settings | DEFAULTS | `settings`, `featureFlags` default to empty |

**TODO:** Admin routes need to query real data (User::count(), Server::count(), Team::count(), etc.)

## Observability Pages

| Page | Route | Status | Data Passed |
|------|-------|--------|-------------|
| Observability/Metrics | GET /observability/metrics | DONE | `servers` list for Sentinel API |

---

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
