# Backend Jobs & Queues Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [x] Проверено

---

## Обзор

Проверка фоновых задач (49+ jobs) и системы очередей.

### Ключевые файлы для проверки:

- `app/Jobs/*.php`
- `config/queue.php`
- `config/horizon.php` (если используется)

---

## Категории Jobs

| Категория | Jobs | Priority |
|-----------|------|----------|
| Deployment | ApplicationDeploymentJob, PullRequestDeploymentJob | 🔴 |
| Server | CheckServerJob, ValidateServerJob, InstallDocker | 🔴 |
| Database | DatabaseBackupJob, RestoreDatabaseBackup | 🔴 |
| Container | ContainerStatusJob, StopContainer, RestartContainer | 🟡 |
| Monitoring | SentinelHeartbeatJob, CheckResources | 🟡 |
| Notifications | SendNotification, SendEmail | 🟢 |
| Webhooks | SendWebhookJob, SendTeamWebhookJob | 🔴 |
| Volume | VolumeCloneJob | 🔴 |

---

## Гипотезы для проверки

### Job Security

- [x] **JOB-001**: Проверить что jobs не принимают user input напрямую - ⚠️ SOME ISSUES
- [x] **JOB-002**: Проверить сериализацию job data - нет sensitive data - ОК (ShouldBeEncrypted used)
- [x] **JOB-003**: Проверить job payload size limits - ОК (Laravel defaults)
- [x] **JOB-004**: Проверить authorization в job handlers - ОК (authorization checked before dispatch)
- [x] **JOB-005**: Проверить что failed jobs не раскрывают secrets - ОК

### Deployment Jobs

- [x] **JOB-006**: `ApplicationDeploymentJob` - проверить command construction - ОК
- [x] **JOB-007**: Проверить git credentials handling в deployments - ОК (cleanup in finally)
- [x] **JOB-008**: Проверить docker build args - нет secret exposure - ОК (ShouldBeEncrypted)
- [x] **JOB-009**: Проверить deployment logs - sanitization - ОК
- [x] **JOB-010**: Проверить rollback mechanism - ОК

### Server Management Jobs

- [x] **JOB-011**: `CheckServerJob` - проверить SSH command safety - ОК
- [x] **JOB-012**: `ValidateServerJob` - проверить validation commands - ОК
- [x] **JOB-013**: `InstallDocker` - проверить installation scripts - ОК (trusted scripts)
- [x] **JOB-014**: Проверить server provision scripts - ОК

### Database Jobs

- [x] **JOB-015**: `DatabaseBackupJob` - проверить backup command construction - ⚠️ FIXED PREVIOUSLY
- [x] **JOB-016**: Проверить backup storage security (S3 credentials) - ОК (encrypted)
- [x] **JOB-017**: `RestoreDatabaseBackup` - проверить restore safety - ⚠️ FIXED PREVIOUSLY
- [x] **JOB-018**: Проверить temporary files cleanup после backup/restore - ОК

### Container Jobs

- [x] **JOB-019**: Проверить container name/id validation - ⚠️ escapeshellarg used in most places
- [x] **JOB-020**: Проверить docker command construction - ⚠️ SEE FINDINGS
- [x] **JOB-021**: Проверить resource limits в container operations - ОК

### Queue Configuration

- [x] **JOB-022**: Проверить queue connection security (Redis auth) - ОК (password in env)
- [x] **JOB-023**: Проверить queue workers isolation - ОК
- [x] **JOB-024**: Проверить job retry policies - ОК (backoff configured)
- [x] **JOB-025**: Проверить failed job handling - ОК

### Scheduled Tasks

- [x] **JOB-026**: Проверить scheduled tasks cron - user defined crons - ОК
- [x] **JOB-027**: Проверить scheduled command injection - ⚠️ SEE FINDINGS
- [x] **JOB-028**: Проверить scheduled task authorization - ОК

### Webhook Jobs

- [x] **JOB-029**: Проверить SendWebhookJob URL validation - ⚠️ SSRF VULNERABLE
- [x] **JOB-030**: Проверить SendTeamWebhookJob URL validation - ⚠️ SSRF VULNERABLE
- [x] **JOB-031**: Проверить webhook payload sanitization - ОК

### Volume Jobs

- [x] **JOB-032**: Проверить VolumeCloneJob command construction - ⚠️ INJECTION VULNERABLE

---

## Findings

### Критические

#### JOB-001-F: SSRF в SendWebhookJob и SendTeamWebhookJob

**Файлы:**
- `app/Jobs/SendWebhookJob.php:50`
- `app/Jobs/SendTeamWebhookJob.php:61`

**Проблема:**
```php
// SendWebhookJob.php
$response = Http::post($this->webhookUrl, $this->payload);

// SendTeamWebhookJob.php
$response = Http::timeout(10)->post($this->webhook->url, $payload);
```

User-provided webhook URLs are used without validation. Attacker can:
1. Access internal services: `http://localhost:6379/` (Redis)
2. Access cloud metadata: `http://169.254.169.254/latest/meta-data/`
3. Scan internal network: `http://10.0.0.1:22/`
4. Exfiltrate data to external servers

**Models with no URL validation:**
- `TeamWebhook.php` - `url` field has no validation
- `WebhookNotificationSettings.php` - `webhook_url` field has no validation

**Severity:** 🔴 Critical
**CVSS:** 8.5 (SSRF with potential for credential theft)

#### JOB-002-F: Command Injection в VolumeCloneJob

**Файл:** `app/Jobs/VolumeCloneJob.php:47-49, 59-63, 77-79`

**Проблема:**
```php
instant_remote_process([
    "docker volume create $this->targetVolume",
    "docker run --rm -v $this->sourceVolume:/source -v $this->targetVolume:/target alpine sh -c 'cp -a /source/. /target/ && chown -R 1000:1000 /target'",
], $this->sourceServer);
```

Volume names are not escaped with `escapeshellarg()`. If volume name contains shell metacharacters:
- `volume; rm -rf /` → command injection
- `$(command)` → command substitution

**Severity:** 🔴 Critical
**Impact:** Remote Code Execution on target servers

#### JOB-003-F: Potential Command Injection в ScheduledTaskJob

**Файл:** `app/Jobs/ScheduledTaskJob.php:140-141`

**Проблема:**
```php
$cmd = "sh -c '".str_replace("'", "'\''", $this->task->command)."'";
$exec = 'docker exec '.escapeshellarg($containerName)." {$cmd}";
```

While container name is properly escaped, the command itself only escapes single quotes.
However, since it's wrapped in single quotes, most other characters are literal.

**Analysis:** The escaping is actually correct for POSIX shells - single quotes escape everything except single quotes themselves, and the `'\''` pattern properly handles embedded single quotes.

**Severity:** 🟢 Low (correct escaping, but could be clearer with documentation)

### Важные

#### JOB-004-F: Missing SSRF Protection in Models

**Файлы:**
- `app/Models/TeamWebhook.php`
- `app/Models/WebhookNotificationSettings.php`

**Проблема:**
No URL validation rules in models. Should add:
1. HTTPS-only requirement (or at least warning)
2. Block private IP ranges
3. Block localhost
4. Block cloud metadata endpoints

**Severity:** 🟠 High

#### JOB-005-F: Potential Secrets in Logs

**Файл:** `app/Jobs/CollectDatabaseMetricsJob.php`

**Проблема:**
Database passwords could appear in error logs if commands fail.
Commands are constructed with passwords passed as environment variables, which is good.

**Severity:** 🟢 Low (passwords passed as env vars, not in command line)

### Низкий приоритет

#### JOB-006-F: ShouldBeEncrypted used correctly

Most jobs that handle sensitive data implement `ShouldBeEncrypted`:
- `ApplicationDeploymentJob`
- `DeleteResourceJob`
- `SendWebhookJob`
- `SendTeamWebhookJob`
- `VolumeCloneJob`

This is good practice and should be documented.

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| JOB-001-F | Add SSRF protection to webhook URLs | 🔧 To Fix | - |
| JOB-002-F | Escape volume names in VolumeCloneJob | 🔧 To Fix | - |
| JOB-003-F | Document command escaping in ScheduledTaskJob | ✅ Acceptable | - |
| JOB-004-F | Add URL validation to webhook models | 🔧 To Fix | - |

---

## Заметки аудитора

### Проверено 2024-01-30

1. Most jobs use `ShouldBeEncrypted` interface for sensitive payloads - good practice
2. `escapeshellarg()` is used in most places for container names
3. DatabaseBackupJob container_name escaping was fixed in previous commit
4. SSRF is a significant risk in webhook jobs - no URL validation at all
5. VolumeCloneJob has direct string interpolation without escaping

### Рекомендации

1. **Add SSRF protection** - validate webhook URLs against:
   - Private IP ranges (10.x, 172.16-31.x, 192.168.x)
   - Localhost (127.x, ::1)
   - Cloud metadata (169.254.169.254)
   - Link-local addresses

2. **Use escapeshellarg()** consistently for all user-controlled values in shell commands

3. **Consider adding URL allowlist** for webhook destinations

4. **Add rate limiting** to webhook delivery to prevent abuse
