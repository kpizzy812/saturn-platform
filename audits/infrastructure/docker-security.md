# Infrastructure Docker Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [x] Проверено

---

## Обзор

Проверка безопасности Docker конфигурации и операций.

### Ключевые файлы для проверки:

- `docker-compose.yml`
- `docker-compose.dev.yml`
- `docker-compose.prod.yml`
- `Dockerfile`
- `docker/` directory
- Docker-related Actions/Jobs

---

## Гипотезы для проверки

### Container Configuration

- [x] **DOCKER-001**: Проверить что containers не запускаются как root - ✅ OK (www-data user)
- [x] **DOCKER-002**: Проверить read-only file systems где возможно - ⚠️ Not implemented
- [x] **DOCKER-003**: Проверить resource limits (CPU, memory) - ⚠️ Not enforced
- [x] **DOCKER-004**: Проверить capabilities dropping - ⚠️ Not implemented
- [x] **DOCKER-005**: Проверить seccomp profiles - ⚠️ Not implemented
- [x] **DOCKER-006**: Проверить no-new-privileges flag - ⚠️ Not implemented

### Image Security

- [x] **DOCKER-007**: Проверить base images - официальные/verified - ✅ OK
- [x] **DOCKER-008**: Проверить image tags - pinned versions (не latest) - ⚠️ Some use :latest
- [x] **DOCKER-009**: Проверить multi-stage builds - минимальный final image - ✅ OK
- [x] **DOCKER-010**: Проверить что secrets не baked в images - ✅ OK

### Volume Security

- [x] **DOCKER-011**: Проверить volume mounts - нет sensitive host paths - ⚠️ docker.sock exposed
- [x] **DOCKER-012**: Проверить bind mounts permissions - ✅ OK
- [x] **DOCKER-013**: Проверить volume mount paths validation - ✅ EXCELLENT
- [x] **DOCKER-014**: Проверить tmpfs usage для temporary data - ⚠️ Not used

### Network Security

- [x] **DOCKER-015**: Проверить network isolation между containers - ✅ OK (bridge network)
- [x] **DOCKER-016**: Проверить exposed ports - минимально необходимые - ✅ OK
- [x] **DOCKER-017**: Проверить internal networks usage - ✅ OK
- [x] **DOCKER-018**: Проверить host network mode - не используется без необходимости - ✅ OK

### Secrets Management

- [x] **DOCKER-019**: Проверить environment variables vs secrets - ⚠️ Env vars used
- [x] **DOCKER-020**: Проверить Docker secrets usage - ⚠️ Not used
- [x] **DOCKER-021**: Проверить .env files - нет в image - ✅ OK

### User Deployment Containers

- [x] **DOCKER-022**: Проверить user container isolation - ⚠️ docker.sock access
- [x] **DOCKER-023**: Проверить resource limits для user containers - ⚠️ Fields exist, enforcement unclear
- [x] **DOCKER-024**: Проверить network policies для user containers - ⚠️ No explicit policies
- [x] **DOCKER-025**: Проверить volume mounts для user containers - ✅ EXCELLENT validation

### Docker Daemon

- [x] **DOCKER-026**: Проверить Docker daemon socket exposure - 🔴 CRITICAL
- [x] **DOCKER-027**: Проверить TLS для Docker daemon (если remote) - N/A
- [x] **DOCKER-028**: Проверить Docker API access control - ⚠️ No restrictions

### Build Security

- [x] **DOCKER-029**: Проверить build args - нет secrets - ✅ OK
- [x] **DOCKER-030**: Проверить .dockerignore файл - ✅ EXCELLENT
- [x] **DOCKER-031**: Проверить build context size limits - ✅ OK

---

## Findings

### Критические

#### DOCKER-001-F: Docker Socket Exposed to Containers

**Файлы:**
- `app/Jobs/ApplicationDeploymentJob.php`
- `app/Actions/Server/StartSentinel.php`

**Проблема:**
```php
// ApplicationDeploymentJob.php
$runCommand = "docker run -d --name {$this->deployment_uuid}
    -v /var/run/docker.sock:/var/run/docker.sock ...";

// StartSentinel.php
$dockerCommand = "docker run -d ...
    -v /var/run/docker.sock:/var/run/docker.sock
    --pid host ...";
```

Docker socket access allows container to:
1. Execute arbitrary Docker commands
2. Create privileged containers
3. Escape containment completely
4. Access all other containers

**Severity:** 🔴 Critical
**Mitigation:** Required for functionality, but consider Docker socket proxy

#### DOCKER-002-F: No SHA256 Verification of Binary Downloads

**Файлы:**
- `docker/production/Dockerfile`
- `docker/saturn-realtime/Dockerfile`

**Проблема:**
```dockerfile
RUN curl -sSL "https://github.com/cloudflare/cloudflared/releases/download/${CLOUDFLARED_VERSION}/cloudflared-linux-amd64" -o /usr/local/bin/cloudflared
```

Binary downloaded without integrity verification. MITM attack could inject malicious binary.

**Severity:** 🔴 Critical
**Fix:** Add SHA256 verification:
```dockerfile
RUN curl -sSL "..." -o /usr/local/bin/cloudflared \
    && echo "EXPECTED_SHA256  /usr/local/bin/cloudflared" | sha256sum -c -
```

### Важные

#### DOCKER-003-F: Sentinel Container Uses --pid host

**Файл:** `app/Actions/Server/StartSentinel.php:54`

**Проблема:**
```php
$dockerCommand = "docker run -d ... --pid host ...";
```

Container shares host PID namespace, can see all host processes.

**Severity:** 🟠 High
**Justification:** Required for process monitoring

#### DOCKER-004-F: No Resource Limits Enforced

**Проблема:**
Resource limit fields exist in Application model but enforcement in deployment unclear:
- `limits_memory`
- `limits_cpus`
- `limits_memory_swap`

Default values are "0" (unlimited).

**Severity:** 🟠 High
**Risk:** Container DoS, resource exhaustion

#### DOCKER-005-F: No no-new-privileges Flag

**Проблема:**
User containers don't have `--security-opt no-new-privileges:true`.
Processes inside container can escalate privileges.

**Severity:** 🟠 High

#### DOCKER-006-F: Docker Config Credentials in Volume

**Файл:** `app/Jobs/ApplicationDeploymentJob.php`

**Проблема:**
```php
-v {$this->serverUserHomeDir}/.docker/config.json:/root/.docker/config.json:ro
```

Registry credentials accessible to helper container (read-only, but still exposed).

**Severity:** 🟠 High

### Средний приоритет

#### DOCKER-007-F: Some Images Use :latest Tag

**Файл:** `docker-compose.dev.yml`

```yaml
image: minio/mc:latest        # Should pin version
image: axllent/mailpit:latest # Should pin version
```

**Severity:** 🟡 Medium

#### DOCKER-008-F: No Explicit Capability Dropping

**Проблема:**
Containers don't drop unnecessary capabilities like:
- CAP_SYS_ADMIN
- CAP_NET_ADMIN
- CAP_SETUID

**Severity:** 🟡 Medium

#### DOCKER-009-F: No Read-Only Root Filesystem

**Проблема:**
Containers don't use read-only root with tmpfs for /tmp.

**Severity:** 🟡 Medium

#### DOCKER-010-F: Dev Database Weak Auth

**Файл:** `docker-compose.dev.yml:34`

```yaml
POSTGRES_HOST_AUTH_METHOD: "trust"
```

Passwordless authentication in dev environment.

**Severity:** 🟡 Medium (dev only)

### Низкий приоритет

#### Положительные находки

| Practice | Status |
|----------|--------|
| Multi-stage Docker builds | ✅ Excellent |
| Non-root execution (www-data) | ✅ OK |
| Proper .dockerignore | ✅ Excellent |
| Build secrets framework | ✅ Implemented |
| Command injection validation | ✅ Excellent |
| Volume mount validation | ✅ Excellent |
| Health checks in production | ✅ OK |
| Service dependencies | ✅ OK |
| Read-only .env mount | ✅ OK |
| Base image version pinning | ✅ OK |

---

## Volume Validation - Excellent Implementation

**Файл:** `app/Parsers/DockerVolumeParser.php`

```php
$dangerousChars = [
    '`' => 'backtick (command substitution)',
    '$(' => 'command substitution',
    '${' => 'variable substitution with potential command injection',
    '|' => 'pipe operator',
    '&' => 'background/AND operator',
    ';' => 'command separator',
    // ... etc
];
```

Comprehensive validation prevents:
- Shell injection via volume paths
- Command substitution
- Path traversal attacks

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| DOCKER-001-F | Docker socket isolation | ⏳ Architecture decision needed |
| DOCKER-002-F | Binary SHA256 verification | 🔧 To Fix |
| DOCKER-003-F | Sentinel PID namespace | ✅ Acceptable (required) |
| DOCKER-004-F | Resource limits enforcement | 🔧 To Verify |
| DOCKER-005-F | no-new-privileges flag | 🔧 To Fix |
| DOCKER-006-F | Docker config credentials | 🔧 Consider Docker secrets |
| DOCKER-007-F | Pin all image versions | 🔧 To Fix |
| DOCKER-008-F | Drop capabilities | 🔧 To Fix |

---

## Рекомендации

### Немедленные действия

1. **Add SHA256 verification for binary downloads**
   ```dockerfile
   RUN curl -sSL "..." -o /tmp/cloudflared \
       && echo "${CLOUDFLARED_SHA256}  /tmp/cloudflared" | sha256sum -c - \
       && mv /tmp/cloudflared /usr/local/bin/
   ```

2. **Add no-new-privileges to user containers**
   ```php
   $dockerOptions .= ' --security-opt no-new-privileges:true';
   ```

3. **Pin all image versions**
   ```yaml
   image: minio/mc:RELEASE.2025-01-01T00-00-00Z
   image: axllent/mailpit:v1.21
   ```

### Долгосрочные улучшения

1. **Docker socket proxy** - Use socket proxy to limit API access
2. **Resource limits** - Enforce memory/CPU limits in deployment
3. **Read-only filesystems** - Where possible
4. **Image scanning** - Integrate Trivy in CI/CD
5. **Docker secrets** - Replace env vars for sensitive data

---

## Заметки аудитора

### Проверено 2024-01-30

**Overall Assessment:** Saturn has strong foundational Docker security with excellent input validation. Main gaps are in container hardening and secrets management.

**Security Score:** B+

| Category | Score |
|----------|-------|
| Image Security | A |
| Volume Validation | A+ |
| Container Hardening | C |
| Secrets Management | B- |
| Network Isolation | B+ |

### Приоритет исправлений

1. **Critical:** Binary verification (easy fix, high impact)
2. **High:** no-new-privileges flag (easy fix)
3. **Medium:** Resource limits verification
4. **Low:** Docker secrets migration (architectural change)
