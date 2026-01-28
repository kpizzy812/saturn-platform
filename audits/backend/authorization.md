# Backend Authorization Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [ ] Не начато

---

## Обзор

Проверка системы авторизации: Policies, Gates, Team-based access control.

### Ключевые файлы для проверки:

- `app/Policies/*.php` (26+ policies)
- `app/Providers/AuthServiceProvider.php`
- `app/Http/Middleware/CanUpdateResource.php`
- `app/Http/Middleware/CanCreateResources.php`
- `app/Models/Team.php`
- `app/Traits/AuthorizesResourceCreation.php`

---

## Гипотезы для проверки

### Team-Based Access Control

- [ ] **AUTHZ-001**: Проверить что все ресурсы привязаны к Team
- [ ] **AUTHZ-002**: Проверить `ownedByCurrentTeam()` scope на всех моделях
- [ ] **AUTHZ-003**: Проверить что нельзя получить доступ к ресурсам чужой команды
- [ ] **AUTHZ-004**: Проверить team switching - инвалидация кэша доступов
- [ ] **AUTHZ-005**: Проверить team invitation flow

### Policies

- [ ] **AUTHZ-006**: Проверить `ServerPolicy` - критический доступ к серверам
- [ ] **AUTHZ-007**: Проверить `ApplicationPolicy` - управление приложениями
- [ ] **AUTHZ-008**: Проверить `DatabasePolicy` - доступ к базам данных
- [ ] **AUTHZ-009**: Проверить `PrivateKeyPolicy` - доступ к SSH ключам
- [ ] **AUTHZ-010**: Проверить `S3StoragePolicy` - доступ к S3 credentials
- [ ] **AUTHZ-011**: Проверить `EnvironmentVariablePolicy` - доступ к env vars
- [ ] **AUTHZ-012**: Проверить `ServicePolicy` - управление сервисами
- [ ] **AUTHZ-013**: Проверить `TeamPolicy` - управление командой
- [ ] **AUTHZ-014**: Проверить `InstanceSettingsPolicy` - глобальные настройки
- [ ] **AUTHZ-015**: Проверить `NotificationPolicy` - настройки уведомлений

### Roles & Permissions

- [ ] **AUTHZ-016**: Проверить `app/Enums/Role.php` - определение ролей
- [ ] **AUTHZ-017**: Проверить что admin роль не может обойти team boundaries
- [ ] **AUTHZ-018**: Проверить super admin доступ (отдельные routes)
- [ ] **AUTHZ-019**: Проверить механизм повышения привилегий

### Middleware Authorization

- [ ] **AUTHZ-020**: Проверить `CanUpdateResource` middleware
- [ ] **AUTHZ-021**: Проверить `CanCreateResources` middleware
- [ ] **AUTHZ-022**: Проверить применение middleware на всех protected routes

### IDOR (Insecure Direct Object Reference)

- [ ] **AUTHZ-023**: Проверить все controller методы на IDOR в servers
- [ ] **AUTHZ-024**: Проверить IDOR в applications endpoints
- [ ] **AUTHZ-025**: Проверить IDOR в databases endpoints
- [ ] **AUTHZ-026**: Проверить IDOR в services endpoints
- [ ] **AUTHZ-027**: Проверить IDOR в deployments endpoints
- [ ] **AUTHZ-028**: Проверить IDOR в backups endpoints
- [ ] **AUTHZ-029**: Проверить IDOR в environment variables endpoints

### API Authorization

- [ ] **AUTHZ-030**: Проверить API token abilities enforcement
- [ ] **AUTHZ-031**: Проверить что `read:sensitive` требуется для env vars
- [ ] **AUTHZ-032**: Проверить что `deploy` ability требуется для deployments
- [ ] **AUTHZ-033**: Проверить что `root` ability ограничен

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
