# Unconnected Pages - Full Audit

All pages where `Inertia::render` passes NO data but frontend expects props.
Grouped by priority and whether a Coolify model exists.

---

## HAS MODEL - Can connect immediately

### 1. Storage/Index
- **Route**: `GET /storage` (web.php:1381)
- **Model**: `S3Storage` — `S3Storage::ownedByCurrentTeam()`
- **Frontend expects**: `storages: S3Storage[]`
- **Fix**: Query `S3Storage::ownedByCurrentTeam()->get()` and pass as `storages`

### 2. Tags/Index
- **Route**: `GET /tags` (web.php:1517)
- **Model**: `Tag` — `Tag::ownedByCurrentTeam()`, has `applications()`, `services()` (morphedByMany)
- **Frontend expects**: `tags: TagWithResources[]` (tag + resource counts)
- **Fix**: Query `Tag::ownedByCurrentTeam()->withCount(['applications', 'services'])->get()`

### 3. SharedVariables/Index
- **Route**: `GET /shared-variables` (web.php:1487)
- **Model**: `SharedEnvironmentVariable` — belongs to team/project/environment, value is encrypted
- **Frontend expects**: `variables: SharedVariable[]`, `team: { id, name }`
- **Fix**: Query `SharedEnvironmentVariable::where('team_id', currentTeam()->id)->get()`

### 4. SharedVariables/Create
- **Route**: `GET /shared-variables/create` (web.php:1491)
- **Frontend expects**: `teams`, `projects`, `environments` for dropdowns
- **Fix**: Pass `currentTeam()->projects()->with('environments')->get()`

### 5. ScheduledTasks/Index
- **Route**: `GET /scheduled-tasks` (web.php:610)
- **Model**: `ScheduledTask` — belongs to application/service, has `executions()`, `latest_log()`
- **Frontend expects**: `tasks?: ScheduledTask[]`
- **Fix**: Query via team's applications/services -> their scheduled_tasks

### 6. ScheduledTasks/History
- **Route**: `GET /scheduled-tasks/history` (web.php:614)
- **Model**: `ScheduledTaskExecution`
- **Frontend expects**: `history?: ScheduledTask[]` (tasks with execution history)
- **Fix**: Same as Index but include `executions` relationship

### 7. SSL/Index
- **Route**: `GET /ssl` (web.php:563)
- **Model**: `SslCertificate` — has `common_name`, `valid_until`, `subject_alternative_names`, belongs to server
- **Frontend expects**: `certificates: SSLCertificate[]`
- **Fix**: Query via team's servers -> `SslCertificate::whereIn('server_id', $serverIds)`

### 8. CronJobs/Index
- **Route**: `GET /cron-jobs` (web.php:597)
- **Model**: `ScheduledTask` (cron jobs ARE scheduled tasks with cron expressions)
- **Frontend expects**: `cronJobs?: CronJob[]`
- **Fix**: Query `ScheduledTask` via team apps/services, map to CronJob format

### 9. CronJobs/Show
- **Route**: `GET /cron-jobs/{uuid}` (web.php:605)
- **Model**: `ScheduledTask` with `executions()`
- **Frontend expects**: `cronJob: CronJob`, `executions?: CronJobExecution[]`
- **Fix**: Find task by uuid, load executions

### 10. CronJobs/Create
- **Route**: `GET /cron-jobs/create` (web.php:601)
- **Frontend expects**: applications/services list for assignment
- **Fix**: Pass team's applications and services

### 11. Domains/Index
- **Route**: `GET /domains` (web.php:546)
- **Model**: Application `fqdn` field (comma-separated FQDNs on each app/service)
- **Frontend expects**: `domains: Domain[]`
- **Fix**: Aggregate all FQDNs from team's applications + service applications

### 12. Domains/Show
- **Route**: `GET /domains/{uuid}` (web.php:554)
- **Frontend expects**: domain details, DNS records, SSL status
- **Fix**: Find application by domain, return details

### 13. Domains/Add
- **Route**: `GET /domains/add` (web.php:550)
- **Frontend expects**: list of applications/services to assign domain to
- **Fix**: Pass team's apps/services

### 14. Volumes/Index
- **Route**: `GET /volumes` (web.php:524)
- **Model**: `LocalPersistentVolume` — morphTo resource (application/service/database)
- **Frontend expects**: `volumes?: Volume[]`
- **Fix**: Query volumes via team's apps/services/databases

### 15. Observability/Index
- **Route**: `GET /observability` (web.php:445)
- **Frontend expects**: `metricsOverview?`, `services?`, `recentAlerts?`
- **Fix**: Aggregate from team's servers/apps — deployment count, status summary

### 16. Destinations/Index
- **Route**: `GET /destinations` (web.php:1470)
- **Model**: `StandaloneDocker`, `SwarmDocker` (via Server)
- **Frontend expects**: `destinations: Destination[]`
- **Fix**: Query `StandaloneDocker`/`SwarmDocker` via team's servers

### 17. Destinations/Create
- **Route**: `GET /destinations/create` (web.php:1474)
- **Frontend expects**: servers list
- **Fix**: Pass `Server::ownedByCurrentTeam()->get()`

### 18. Storage/Backups
- **Route**: `GET /storage/backups` (web.php:537)
- **Model**: `ScheduledDatabaseBackup` — `ScheduledDatabaseBackup::ownedByCurrentTeam()`
- **Frontend expects**: backup list (page currently uses local volumeId)
- **Fix**: Query `ScheduledDatabaseBackup::ownedByCurrentTeam()->with('executions')->get()`

---

## NO MODEL / FUTURE FEATURES

### 19. Observability/Traces
- **Route**: `GET /observability/traces` (web.php:515)
- **No model**: Traces require APM integration (Jaeger/Tempo/etc.)
- **Status**: Future feature — leave as empty state

### 20. Observability/Alerts
- **Route**: `GET /observability/alerts` (web.php:519)
- **No model**: Alerts system not implemented
- **Status**: Future feature — leave as empty state

### 21. Storage/Snapshots
- **Route**: `GET /storage/snapshots` (web.php:541)
- **No model**: Volume snapshots not implemented in Coolify
- **Status**: Future feature — leave as empty state

### 22. Volumes/Create
- **Route**: `GET /volumes/create` (web.php:528)
- **Status**: Volume creation is handled by Docker, not via UI yet

### 23. Volumes/Show
- **Route**: `GET /volumes/{uuid}` (web.php:532)
- **Model**: `LocalPersistentVolume` exists but no UUID lookup implemented
- **Status**: Partially possible — can pass volume data if found

### 24. Domains/Redirects
- **Route**: `GET /domains/{uuid}/redirects` (web.php:558)
- **No model**: Redirect rules not implemented
- **Status**: Future feature

---

## STATIC PAGES (no data needed)

These pages are intentionally static or self-contained:

- `Auth/Login`, `Auth/Register`, `Auth/ForcePasswordReset`, `Auth/TwoFactor/Verify`
- `Subscription/Index`, `Subscription/Plans`, `Subscription/Checkout`, `Subscription/Success` (future billing)
- `Demo/Index`, `Templates/Index`, `Templates/Show`, `Templates/Deploy`
- `Templates/Categories`, `Templates/Submit`
- `CLI/Setup`, `CLI/Commands` (static instructions)
- `Onboarding/Welcome`
- `Support/Index` (static contact info)
- `Sources/GitHub/Create`, `Sources/GitLab/Create` (forms)
- `Storage/Create` (form, already has test-connection)
- `Errors/404`, `Errors/500`, `Errors/403`, `Errors/Maintenance`

---

## Summary

| Category | Count | Action |
|----------|-------|--------|
| Has model, needs backend query | 18 | Write Inertia data passing |
| No model / future feature | 6 | Leave as empty state |
| Static pages (no data needed) | 16+ | No action needed |

## Existing Models Reference

| Model | Key Fields | Team Scope Method |
|-------|-----------|-------------------|
| `S3Storage` | name, description, endpoint, bucket, region, key*, secret* | `ownedByCurrentTeam()` |
| `Tag` | name, team_id | `ownedByCurrentTeam()` |
| `SharedEnvironmentVariable` | key, value*, team_id, project_id, environment_id | via team_id |
| `ScheduledTask` | name, command, frequency, enabled, application_id, service_id | via app/service |
| `ScheduledTaskExecution` | scheduled_task_id, status, message | via ScheduledTask |
| `ScheduledDatabaseBackup` | database_id, database_type, frequency, s3_storage_id | `ownedByCurrentTeam()` |
| `ScheduledDatabaseBackupExecution` | scheduled_database_backup_id, status, filename, size | via backup |
| `SslCertificate` | common_name, valid_until, subject_alternative_names, server_id | via server |
| `LocalPersistentVolume` | name, mount_path, host_path, resource_type, resource_id | via app/service |
| `LocalFileVolume` | fs_path, mount_path, content, resource_type, resource_id | via app/service |
| `StandaloneDocker` | name, network, server_id | via server |
| `SwarmDocker` | name, network, server_id | via server |

*encrypted fields
