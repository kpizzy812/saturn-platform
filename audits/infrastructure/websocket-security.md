# Infrastructure WebSocket Security Audit

**Приоритет:** 🟡 High
**Статус:** [🔍] Проверено, найдены критические проблемы
**Дата аудита:** 2026-01-30

---

## Резюме уязвимостей

| Severity | Количество | Примеры |
|----------|-----------|---------|
| CRITICAL | 3 | Hardcoded credentials, Origin validation, Secrets exposure |
| HIGH | 3 | HTTP вместо HTTPS, Утечка email, Отсутствие TLS |
| MEDIUM | 6 | CSRF, Rate limiting, Null-checking |
| LOW | 3 | Port detection, Логирование, Типизация |

---

## Гипотезы для проверки

### Authentication

- [🔴] **WS-001**: Проверить WebSocket authentication mechanism - **Hardcoded credentials 'saturn'**
- [🔴] **WS-002**: Проверить Pusher key/secret configuration - **Одинаковые для всех**
- [✅] **WS-003**: Проверить token validation при connect - CSRF token используется

### Channel Authorization

- [✅] **WS-004**: Проверить private channel authorization - Правильно реализовано
- [⚠️] **WS-005**: Проверить presence channel authorization - Отсутствуют presence channels
- [✅] **WS-006**: Проверить channel naming - Нет injection
- [🔴] **WS-007**: Проверить user presence data - **Email утекает в broadcast events**

### Event Security

- [🔴] **WS-008**: Проверить broadcast data - **Email раскрывается**
- [✅] **WS-009**: Проверить event names - Validated
- [⚠️] **WS-010**: Проверить payload size limits - Не настроено
- [⚠️] **WS-011**: Проверить event rate limiting - Не настроено

### Channel Authorization Rules

- [✅] **WS-012**: Проверить `App.Models.Server.*` channel - Team-based auth
- [⚠️] **WS-013**: Проверить `App.Models.Application.*` channel - UUID/ID fallback
- [✅] **WS-014**: Проверить deployment status channels - OK
- [✅] **WS-015**: Проверить team-based channels - OK

### Connection Security

- [🔴] **WS-016**: Проверить WSS (WebSocket over TLS) - **HTTP по умолчанию**
- [⚠️] **WS-017**: Проверить connection limits per user - Не настроено
- [✅] **WS-018**: Проверить idle connection timeout - OK
- [⚠️] **WS-019**: Проверить reconnection handling - Слабый backoff

### Soketi Configuration

- [🔴] **WS-020**: Проверить Soketi app configuration - **Hardcoded 'saturn'**
- [⚠️] **WS-021**: Проверить metrics endpoint protection - Не проверено
- [✅] **WS-022**: Проверить debug mode disabled - OK
- [N/A] **WS-023**: Проверить cluster configuration - Не используется

### Client-Side

- [⚠️] **WS-024**: Проверить Echo configuration на фронтенде - Auto-detection может дать сбой
- [⚠️] **WS-025**: Проверить credential exposure в JS - Дефолтный key 'saturn'
- [✅] **WS-026**: Проверить error handling - OK

### Message Validation

- [✅] **WS-027**: Проверить inbound message validation - OK
- [✅] **WS-028**: Проверить client event permissions - OK
- [✅] **WS-029**: Проверить broadcast payload sanitization - OK

---

## Findings

### Критические

#### [WS-CRITICAL-001] 🔴 Жесткие учетные данные по умолчанию

**Файл:** `docker-compose.dev.yml`
**Строки:** 61-63

**Проблема:**
```yaml
PUSHER_APP_ID: saturn
PUSHER_APP_KEY: saturn
PUSHER_APP_SECRET: saturn
```

**Severity:** CRITICAL

**Рекомендация:** Генерировать случайные уникальные значения при первом запуске.

---

#### [WS-CRITICAL-002] 🔴 Отсутствие Origin-валидации в Soketi

**Файл:** `docker-compose.dev.yml`

**Severity:** CRITICAL (для production)

**Описание:** Soketi не имеет конфигурации для проверки Origin-заголовка.

**Рекомендация:**
```yaml
soketi:
  environment:
    SOKETI_ALLOWED_ORIGINS: https://yourdomain.com
```

---

#### [WS-CRITICAL-003] 🔴 Credentials могут попасть в контроль версий

**Severity:** CRITICAL

**Рекомендация:** Убедиться, что `.env` в `.gitignore`

---

### Важные

#### [WS-HIGH-001] 🟡 HTTP протокол по умолчанию

**Файл:** `config/broadcasting.php:41`

```php
'scheme' => env('PUSHER_SCHEME', 'http'),
```

**Severity:** HIGH

---

#### [WS-HIGH-002] 🟡 Утечка email в broadcast-событиях

**Файлы:**
- `app/Events/DeploymentApprovalResolved.php:76`
- `app/Events/DeploymentApprovalRequested.php:58-70`

```php
'resolvedByEmail' => $this->resolvedByEmail,
```

**Severity:** HIGH

**Рекомендация:** Использовать ID вместо email.

---

#### [WS-HIGH-003] 🟡 Отсутствие TLS конфигурации в Soketi

**Файл:** `docker-compose.dev.yml:45-64`

**Severity:** HIGH

---

### Средний приоритет

#### [WS-MEDIUM-001] ⚠️ Null-проблема в channel authorization

**Файл:** `routes/channels.php:49-61`

```php
return $database && $user->teams->pluck('id')->contains($database->team()?->id);
```

**Рекомендация:**
```php
return $database && ($teamId = $database->team()?->id) && $user->teams->pluck('id')->contains($teamId);
```

---

#### [WS-MEDIUM-002] ⚠️ Отсутствие rate-limiting на broadcast auth

**Рекомендация:**
```php
Broadcast::routes(['middleware' => ['auth:sanctum', 'throttle:60,1']]);
```

---

#### [WS-MEDIUM-003] ⚠️ Слабый exponential backoff

**Файл:** `resources/js/hooks/useRealtimeStatus.ts:425-429`

**Рекомендация:** Добавить `Math.min(2000 * (reconnectAttempts + 1), 30000)`

---

### Низкий приоритет

#### [WS-LOW-001] Port detection может работать неправильно

**Файл:** `resources/js/lib/echo.ts:52-53`

---

#### [WS-LOW-002] Отсутствие логирования WebSocket-событий

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| WS-CRITICAL-001 | Hardcoded credentials | ⏳ Pending | - |
| WS-CRITICAL-002 | Origin validation | ⏳ Pending | - |
| WS-HIGH-001 | HTTP по умолчанию | ⏳ Pending | - |
| WS-HIGH-002 | Email в events | ⏳ Pending | - |

---

## Приоритизация исправлений

### CRITICAL (немедленное действие):
1. **WS-CRITICAL-001** - Генерировать уникальные credentials
2. **WS-CRITICAL-002** - Добавить SOKETI_ALLOWED_ORIGINS
3. **WS-CRITICAL-003** - Проверить .gitignore

### HIGH (в ближайший sprint):
1. **WS-HIGH-001** - Обеспечить WSS в production
2. **WS-HIGH-002** - Использовать IDs вместо emails
3. **WS-HIGH-003** - Документировать требование reverse proxy
