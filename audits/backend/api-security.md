# Backend API Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [ ] Не начато

---

## Обзор

Проверка безопасности REST API v1 (89+ endpoints).

### Ключевые файлы для проверки:

- `routes/api.php`
- `app/Http/Controllers/Api/*.php`
- `app/Http/Middleware/ApiAllowed.php`
- `config/cors.php`

---

## API Controllers для проверки

| Controller | Файл | Priority |
|------------|------|----------|
| ApplicationsController | `Api/ApplicationsController.php` | 🔴 |
| ServersController | `Api/ServersController.php` | 🔴 |
| DatabasesController | `Api/DatabasesController.php` | 🔴 |
| ServicesController | `Api/ServicesController.php` | 🔴 |
| DeployController | `Api/DeployController.php` | 🔴 |
| SecurityController | `Api/SecurityController.php` | 🔴 |
| ApplicationEnvsController | `Api/ApplicationEnvsController.php` | 🔴 |
| ServiceEnvsController | `Api/ServiceEnvsController.php` | 🔴 |
| TeamController | `Api/TeamController.php` | 🟡 |
| ProjectController | `Api/ProjectController.php` | 🟡 |
| GitController | `Api/GitController.php` | 🟡 |
| DatabaseBackupsController | `Api/DatabaseBackupsController.php` | 🟡 |

---

## Гипотезы для проверки

### Input Validation

- [ ] **API-001**: Проверить validation rules на всех endpoints
- [ ] **API-002**: Проверить sanitization входных данных
- [ ] **API-003**: Проверить max length limits на string inputs
- [ ] **API-004**: Проверить numeric range validation
- [ ] **API-005**: Проверить array/object depth limits (JSON bombs)
- [ ] **API-006**: Проверить file size limits где применимо

### Rate Limiting

- [ ] **API-007**: Проверить rate limiting configuration
- [ ] **API-008**: Проверить per-user/per-ip rate limits
- [ ] **API-009**: Проверить rate limit на критических endpoints (deploy, create)
- [ ] **API-010**: Проверить rate limit bypass через headers manipulation

### CORS Configuration

- [ ] **API-011**: Проверить `config/cors.php` - allowed origins
- [ ] **API-012**: Проверить allowed methods
- [ ] **API-013**: Проверить credentials handling
- [ ] **API-014**: Проверить exposed headers

### Mass Assignment

- [ ] **API-015**: Проверить $fillable/$guarded на всех моделях
- [ ] **API-016**: Проверить что sensitive fields не в $fillable
- [ ] **API-017**: Проверить validated() vs all() в controllers

### Response Security

- [ ] **API-018**: Проверить что API не возвращает sensitive data по умолчанию
- [ ] **API-019**: Проверить JSON response - нет stack traces в production
- [ ] **API-020**: Проверить security headers в API responses

### Specific Endpoints

#### Servers API
- [ ] **API-021**: `POST /servers` - проверить server creation validation
- [ ] **API-022**: `PUT /servers/{uuid}` - проверить update validation
- [ ] **API-023**: `DELETE /servers/{uuid}` - проверить cascade deletes
- [ ] **API-024**: `/servers/{uuid}/domains` - проверить domain validation

#### Applications API
- [ ] **API-025**: `POST /applications` - проверить git URL validation
- [ ] **API-026**: `PUT /applications/{uuid}` - проверить все поля
- [ ] **API-027**: `/applications/{uuid}/envs` - проверить env var handling
- [ ] **API-028**: `/applications/{uuid}/start|stop|restart` - проверить authorization

#### Databases API
- [ ] **API-029**: Database creation - проверить password generation
- [ ] **API-030**: Database update - проверить credential exposure
- [ ] **API-031**: `/databases/{uuid}/backups` - проверить S3 credentials

#### Deploy API
- [ ] **API-032**: `POST /deploy` - проверить deployment authorization
- [ ] **API-033**: Webhook deploy - проверить signature validation
- [ ] **API-034**: Deploy by tag - проверить tag validation

### Error Handling

- [ ] **API-035**: Проверить что 500 errors не раскрывают internals
- [ ] **API-036**: Проверить consistent error format
- [ ] **API-037**: Проверить logging sensitive data в errors

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
