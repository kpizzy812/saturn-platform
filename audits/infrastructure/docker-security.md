# Infrastructure Docker Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [ ] Не начато

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

- [ ] **DOCKER-001**: Проверить что containers не запускаются как root
- [ ] **DOCKER-002**: Проверить read-only file systems где возможно
- [ ] **DOCKER-003**: Проверить resource limits (CPU, memory)
- [ ] **DOCKER-004**: Проверить capabilities dropping
- [ ] **DOCKER-005**: Проверить seccomp profiles
- [ ] **DOCKER-006**: Проверить no-new-privileges flag

### Image Security

- [ ] **DOCKER-007**: Проверить base images - официальные/verified
- [ ] **DOCKER-008**: Проверить image tags - pinned versions (не latest)
- [ ] **DOCKER-009**: Проверить multi-stage builds - минимальный final image
- [ ] **DOCKER-010**: Проверить что secrets не baked в images

### Volume Security

- [ ] **DOCKER-011**: Проверить volume mounts - нет sensitive host paths
- [ ] **DOCKER-012**: Проверить bind mounts permissions
- [ ] **DOCKER-013**: Проверить volume mount paths validation
- [ ] **DOCKER-014**: Проверить tmpfs usage для temporary data

### Network Security

- [ ] **DOCKER-015**: Проверить network isolation между containers
- [ ] **DOCKER-016**: Проверить exposed ports - минимально необходимые
- [ ] **DOCKER-017**: Проверить internal networks usage
- [ ] **DOCKER-018**: Проверить host network mode - не используется без необходимости

### Secrets Management

- [ ] **DOCKER-019**: Проверить environment variables vs secrets
- [ ] **DOCKER-020**: Проверить Docker secrets usage
- [ ] **DOCKER-021**: Проверить .env files - нет в image

### User Deployment Containers

- [ ] **DOCKER-022**: Проверить user container isolation
- [ ] **DOCKER-023**: Проверить resource limits для user containers
- [ ] **DOCKER-024**: Проверить network policies для user containers
- [ ] **DOCKER-025**: Проверить volume mounts для user containers

### Docker Daemon

- [ ] **DOCKER-026**: Проверить Docker daemon socket exposure
- [ ] **DOCKER-027**: Проверить TLS для Docker daemon (если remote)
- [ ] **DOCKER-028**: Проверить Docker API access control

### Build Security

- [ ] **DOCKER-029**: Проверить build args - нет secrets
- [ ] **DOCKER-030**: Проверить .dockerignore файл
- [ ] **DOCKER-031**: Проверить build context size limits

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
