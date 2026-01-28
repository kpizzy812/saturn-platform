# Backend Jobs & Queues Security Audit

**Приоритет:** 🟡 High
**Статус:** [ ] Не начато

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

---

## Гипотезы для проверки

### Job Security

- [ ] **JOB-001**: Проверить что jobs не принимают user input напрямую
- [ ] **JOB-002**: Проверить сериализацию job data - нет sensitive data
- [ ] **JOB-003**: Проверить job payload size limits
- [ ] **JOB-004**: Проверить authorization в job handlers
- [ ] **JOB-005**: Проверить что failed jobs не раскрывают secrets

### Deployment Jobs

- [ ] **JOB-006**: `ApplicationDeploymentJob` - проверить command construction
- [ ] **JOB-007**: Проверить git credentials handling в deployments
- [ ] **JOB-008**: Проверить docker build args - нет secret exposure
- [ ] **JOB-009**: Проверить deployment logs - sanitization
- [ ] **JOB-010**: Проверить rollback mechanism

### Server Management Jobs

- [ ] **JOB-011**: `CheckServerJob` - проверить SSH command safety
- [ ] **JOB-012**: `ValidateServerJob` - проверить validation commands
- [ ] **JOB-013**: `InstallDocker` - проверить installation scripts
- [ ] **JOB-014**: Проверить server provision scripts

### Database Jobs

- [ ] **JOB-015**: `DatabaseBackupJob` - проверить backup command construction
- [ ] **JOB-016**: Проверить backup storage security (S3 credentials)
- [ ] **JOB-017**: `RestoreDatabaseBackup` - проверить restore safety
- [ ] **JOB-018**: Проверить temporary files cleanup после backup/restore

### Container Jobs

- [ ] **JOB-019**: Проверить container name/id validation
- [ ] **JOB-020**: Проверить docker command construction
- [ ] **JOB-021**: Проверить resource limits в container operations

### Queue Configuration

- [ ] **JOB-022**: Проверить queue connection security (Redis auth)
- [ ] **JOB-023**: Проверить queue workers isolation
- [ ] **JOB-024**: Проверить job retry policies
- [ ] **JOB-025**: Проверить failed job handling

### Scheduled Tasks

- [ ] **JOB-026**: Проверить scheduled tasks cron - user defined crons
- [ ] **JOB-027**: Проверить scheduled command injection
- [ ] **JOB-028**: Проверить scheduled task authorization

---

## Findings

### Критические

> Записывать найденные критические проблемы здесь

### Важные

> Записывать найденные важные проблемы здесь

### Низкий приоритет

> Записывать проблемы низкого приоритета здесь

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| - | - | - | - |

---

## Заметки аудитора

> Дополнительные заметки при проверке
