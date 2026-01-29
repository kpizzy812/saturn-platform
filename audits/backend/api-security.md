# Backend API Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [🔍] В процессе - найдены критические проблемы

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

- [🔴] **API-011**: Проверить `config/cors.php` - **РАЗРЕШЕНЫ ВСЕ ORIGINS (*)**
- [🔴] **API-012**: Проверить allowed methods - **РАЗРЕШЕНЫ ВСЕ МЕТОДЫ (*)**
- [⚠️] **API-013**: Проверить credentials handling
- [⚠️] **API-014**: Проверить exposed headers

### Mass Assignment

- [🔴] **API-015**: Проверить $fillable/$guarded - **EnvironmentVariable $guarded = []**
- [🔴] **API-016**: Проверить sensitive fields - **sensitive fields открыты**
- [🔴] **API-017**: Проверить validated() vs all() - **$request->all() без валидации!**

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

#### 🔴 API-011: CORS открыт для ВСЕХ источников
**Файл:** `config/cors.php:22`
**Проблема:**
```php
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```
Любой сайт может делать API запросы от имени аутентифицированного пользователя!

---

#### 🔴 API-015/017: Mass Assignment через $request->all()
**Файлы:** `ServiceEnvsController.php:224,340`, `DatabaseCreateController.php`
**Проблема:**
```php
$env = $service->environment_variables()->create($request->all());
```
Используется `$request->all()` БЕЗ валидации + модель имеет `$guarded = []`

**Вектор атаки:** Можно переписать ANY поле модели, включая ID, resourceable_type/id

---

#### 🔴 API-033-A: GitHub webhook сигнатура пропускается в dev
**Файл:** `app/Http/Controllers/Webhook/Github.php:83`
**Проблема:**
```php
if (! hash_equals(...) && ! isDev()) { // Пропуск в dev!
```
Если APP_ENV=local, сигнатура НЕ проверяется - любой может запустить деплой!

---

#### 🔴 API-033-B: GitLab webhook НЕ проверяет сигнатуру
**Файл:** `app/Http/Controllers/Webhook/Gitlab.php:34-41`
**Проблема:** Только проверяется что токен не пустой, но НЕ сравнивается с ожидаемым!

---

#### 🔴 API-033-C: Bitbucket webhook берёт алгоритм из входных данных
**Файл:** `app/Http/Controllers/Webhook/Bitbucket.php:62-64`
**Проблема:**
```php
[$algo, $hash] = explode('=', $x_bitbucket_token, 2);
```
Атакующий может выбрать слабый алгоритм!

---

#### 🔴 API-021: Server creation требует только 'read' permission
**Файл:** `routes/api.php:250`
**Проблема:** `POST /servers` требует только `api.ability:read` вместо `write`!

---

#### 🔴 API-032: Shell injection в deployment cancellation
**Файл:** `app/Http/Controllers/Api/DeployController.php:240,256,269`
**Проблема:**
```php
$kill_command = "docker rm -f {$deployment_uuid}";  // БЕЗ escapeshellarg!
```

---

### Важные

#### ⚠️ API-007/008: Недостаточный Rate Limiting
**Файл:** `config/api.php:4`
**Проблема:** 200 req/min глобально - слишком много, нет per-user limits

---

#### ⚠️ API-005: Слабая валидация Git URL
**Файл:** `bootstrap/helpers/api.php:100-109`
**Проблема:** `'git_repository' => 'string'` - нет проверки формата URL, SSRF возможен

---

#### ⚠️ API-018: Sensitive data в API responses
**Проблема:** Пароли БД возвращаются при `read:sensitive` permission без дополнительной защиты

### Низкий приоритет

> Нет проблем низкого приоритета

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| - | - | - | - |

---

## Заметки аудитора

> Дополнительные заметки при проверке
