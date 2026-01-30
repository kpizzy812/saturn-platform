# Saturn Platform Security & Bug Audit

## Master Checklist

**Дата начала аудита:** 2026-01-28
**Статус:** 🔄 В процессе

---

## Как использовать

Каждый файл в подпапках содержит гипотезы для проверки. Агенты проверяют гипотезы и помечают их статус:

- `[ ]` - Не проверено
- `[🔍]` - В процессе проверки
- `[✅]` - Проверено, проблем не найдено
- `[⚠️]` - Найдена проблема (низкий приоритет)
- `[🔴]` - Критическая уязвимость (требует немедленного исправления)
- `[🔧]` - Исправлено (указать PR/commit)

---

## Архитектурный обзор

### Критические компоненты для аудита

| Область | Приоритет | Файл чеклиста | Статус |
|---------|-----------|---------------|--------|
| **Backend** ||||
| Аутентификация & Сессии | 🔴 Critical | [backend/authentication.md](backend/authentication.md) | [🔍] 1 critical, 6 medium |
| Авторизация & Policies | 🔴 Critical | [backend/authorization.md](backend/authorization.md) | [🔴] **КРИТИЧЕСКОЕ! 10+ policies отключены** |
| API Security (89+ endpoints) | 🔴 Critical | [backend/api-security.md](backend/api-security.md) | [🔍] 7 critical found |
| SSH Operations | 🔴 Critical | [backend/ssh-operations.md](backend/ssh-operations.md) | [🔍] 4 critical found |
| Webhooks (GitHub, GitLab, etc.) | 🔴 Critical | [backend/webhooks.md](backend/webhooks.md) | [🔧] 5 critical FIXED |
| Jobs & Queues (49+ jobs) | 🟡 High | [backend/jobs-queues.md](backend/jobs-queues.md) | [🔧] 3 critical FIXED |
| File Uploads | 🟡 High | [backend/file-uploads.md](backend/file-uploads.md) | [🔍] 3 critical, 4 high |
| Environment Variables | 🔴 Critical | [backend/environment-variables.md](backend/environment-variables.md) | [🔍] 4 critical found |
| **Frontend** ||||
| XSS Prevention | 🔴 Critical | [frontend/xss-prevention.md](frontend/xss-prevention.md) | [🔧] 1 critical FIXED, 1 high |
| API Calls & Data Handling | 🟡 High | [frontend/api-calls.md](frontend/api-calls.md) | [🔍] 2 high, 5 medium |
| Authentication Flow | 🔴 Critical | [frontend/authentication-flow.md](frontend/authentication-flow.md) | [🔍] 1 critical, 8 high |
| Input Validation | 🟡 High | [frontend/input-validation.md](frontend/input-validation.md) | [🔍] 2 critical, 4 high |
| Sensitive Data Exposure | 🔴 Critical | [frontend/sensitive-data.md](frontend/sensitive-data.md) | [🔴] 2 critical found |
| **Infrastructure** ||||
| Docker Security | 🔴 Critical | [infrastructure/docker-security.md](infrastructure/docker-security.md) | [🔍] 2 critical, 4 high |
| Secrets Management | 🔴 Critical | [infrastructure/secrets-management.md](infrastructure/secrets-management.md) | [🔧] 1 critical FIXED, 2 critical, 2 high |
| Proxy Configuration (Traefik/Caddy) | 🟡 High | [infrastructure/proxy-configuration.md](infrastructure/proxy-configuration.md) | [🔍] 2 critical, 5 high |
| WebSocket Security | 🟡 High | [infrastructure/websocket-security.md](infrastructure/websocket-security.md) | [🔍] 3 critical, 3 high |
| **Database** ||||
| SQL Injection | 🔴 Critical | [database/sql-injection.md](database/sql-injection.md) | [🔍] 4 critical found |
| Data Exposure | 🔴 Critical | [database/data-exposure.md](database/data-exposure.md) | [🔧] 4 critical FIXED |
| Migrations & Schema | 🟢 Medium | [database/migrations.md](database/migrations.md) | [ ] |

---

## Прогресс

### Общая статистика

```
Total Hypotheses: 258
Checked: 188+
Issues Found: 55+
Critical: 38+
Fixed: 22
```

### Breakdown по файлам

| Файл | Гипотез |
|------|---------|
| backend/authentication.md | 25 |
| backend/authorization.md | 33 |
| backend/api-security.md | 37 |
| backend/ssh-operations.md | 34 |
| backend/webhooks.md | 26 |
| backend/jobs-queues.md | 28 |
| backend/file-uploads.md | 23 |
| backend/environment-variables.md | 26 |
| frontend/xss-prevention.md | 26 |
| frontend/api-calls.md | 23 |
| frontend/authentication-flow.md | 26 |
| frontend/input-validation.md | 28 |
| frontend/sensitive-data.md | 30 |
| infrastructure/docker-security.md | 31 |
| infrastructure/secrets-management.md | 31 |
| infrastructure/proxy-configuration.md | 30 |
| infrastructure/websocket-security.md | 29 |
| database/sql-injection.md | 26 |
| database/data-exposure.md | 28 |
| database/migrations.md | 22 |

### По категориям

| Категория | Всего | Проверено | Проблемы | Исправлено |
|-----------|-------|-----------|----------|------------|
| Backend | - | 0 | 0 | 0 |
| Frontend | - | 0 | 0 | 0 |
| Infrastructure | - | 0 | 0 | 0 |
| Database | - | 0 | 0 | 0 |

---

## Найденные проблемы (Summary)

### 🔴 Критические

1. **[SSH-006] Command Injection в git_commit_sha** - [ssh-operations.md](backend/ssh-operations.md) ✅ FIXED
   - Файл: `app/Models/Application.php:1114`
   - API позволяет установить произвольный `git_commit_sha` без валидации формата
   - **Severity: RCE (Remote Code Execution)**

2. **[SSH-005] Container name без escaping** - [ssh-operations.md](backend/ssh-operations.md) ✅ FIXED
   - Файл: `app/Jobs/ScheduledTaskJob.php:141`
   - Container name вставляется в docker exec без escapeshellarg

3. **[SSH-002] Container name без escaping в backup/restore** - [ssh-operations.md](backend/ssh-operations.md) 🆕
   - Файлы: `DatabaseBackupJob.php` (14+ мест), `DatabaseRestoreJob.php` (8 мест)
   - `$this->container_name` вставляется в docker exec без escapeshellarg
   - **Severity: RCE (Remote Code Execution)**

4. **[SSH-012] Отсутствует cleanup SSH ключей** - [ssh-operations.md](backend/ssh-operations.md) 🆕
   - Файлы: `HandlesGitOperations.php`, `Application.php`
   - SSH ключ `/root/.ssh/id_rsa` не удаляется из deployment контейнера
   - **Severity: HIGH (credential exposure)**

5. **[CMD-001] Command Injection в Redis KEYS** - [sql-injection.md](database/sql-injection.md) 🆕
   - Файл: `DatabaseMetricsController.php:1345`
   - User input `pattern` вставляется в shell без escaping
   - **Severity: RCE**

6. **[CMD-002] Command Injection в PostgreSQL query** - [sql-injection.md](database/sql-injection.md) 🆕
   - Файл: `DatabaseMetricsController.php:773`
   - Query execution через shell с неадекватным escaping
   - **Severity: RCE**

7. **[CMD-003] Command Injection в MySQL query** - [sql-injection.md](database/sql-injection.md) 🆕
   - Файл: `DatabaseMetricsController.php:800`
   - Аналогично CMD-002
   - **Severity: RCE**

8. **[CMD-004] Command Injection в ClickHouse query** - [sql-injection.md](database/sql-injection.md) 🆕
   - Файл: `DatabaseMetricsController.php:828`
   - Аналогично CMD-002
   - **Severity: RCE**

9. **[AUTHZ-ALL] ‼️ БОЛЬШИНСТВО POLICIES ОТКЛЮЧЕНЫ** - [authorization.md](backend/authorization.md) 🆕
   - 10+ Policies возвращают `true` для ВСЕХ методов
   - Пользователи могут получить доступ к ЛЮБЫМ ресурсам других команд
   - **Severity: CRITICAL - полный обход авторизации**

10. **[AUTH-015] API Токены НИКОГДА не истекают** - [authentication.md](backend/authentication.md) 🆕
    - Файл: `config/sanctum.php:49`
    - `'expiration' => null` - украденный токен работает вечно
    - **Severity: HIGH**

11. **[API-011] CORS открыт для ВСЕХ источников** - [api-security.md](backend/api-security.md) 🆕
    - Файл: `config/cors.php:22`
    - `'allowed_origins' => ['*']`
    - **Severity: HIGH - CSRF атаки**

12. **[API-033] Webhook сигнатуры НЕ проверяются** - [api-security.md](backend/api-security.md) ✅ FIXED
    - GitHub: пропускается в dev → Исправлено
    - GitLab: только проверка на не-пустой токен → Используется hash_equals
    - Bitbucket: алгоритм берется из входных данных → Валидация сигнатуры
    - **Severity: CRITICAL - поддельные deployments**

13. **[API-015/017] Mass Assignment через $request->all()** - [api-security.md](backend/api-security.md) 🆕
    - EnvironmentVariable имеет `$guarded = []`
    - Используется `$request->all()` без валидации
    - **Severity: HIGH**

14. **[ENV-006] EnvironmentVariablePolicy полностью отключена** - [environment-variables.md](backend/environment-variables.md) ✅ FIXED
    - ВСЕ методы возвращают `true` → Добавлена team-based authorization
    - Любой user может view/update/delete ЛЮБОЙ env var → Проверка через resourceable->team()
    - **Severity: CRITICAL**

15. **[ENV-007] SharedEnvironmentVariablePolicy частично отключена** - [environment-variables.md](backend/environment-variables.md) ✅ FIXED
    - Проверка team_id закомментирована → Добавлена belongsToTeam() проверка
    - **Severity: CRITICAL**

16. **[ENV-017] Injection через env variable name** - [environment-variables.md](backend/environment-variables.md) ✅ FIXED
    - Файлы: `EnvironmentVariable.php`, `SharedEnvironmentVariable.php`
    - Добавлена POSIX-валидация ключей и список PROTECTED_KEYS
    - Блокируются: PATH, LD_PRELOAD, LD_LIBRARY_PATH и др.
    - **Severity: HIGH**

17. **[SENS-009] Env values передаются в Inertia props** - [sensitive-data.md](frontend/sensitive-data.md) ✅ FIXED
    - Файл: `ApplicationController.php:519`
    - Добавлена проверка is_shown_once - скрытые значения не передаются
    - **Severity: CRITICAL - data exposure**

18. **[SENS-013] Export env vars без warning** - [sensitive-data.md](frontend/sensitive-data.md) ✅ FIXED
    - Файл: `Variables.tsx`
    - Добавлен confirmation dialog с предупреждением о sensitive data
    - **Severity: HIGH**

### ⚠️ Важные

1. **[SSH-017] Host key verification отключена** - [ssh-operations.md](backend/ssh-operations.md)
   - Файл: `app/Helpers/SshMultiplexingHelper.php:235`
   - MITM атака возможна, но приемлемо для PaaS

2. **[SSH-010] Временные SSH ключи в контейнере** - [ssh-operations.md](backend/ssh-operations.md) 🆕
   - Ключи остаются в deployment контейнере после использования

3. **[SSH-014] Частичное скрытие ключей в логах** - [ssh-operations.md](backend/ssh-operations.md) 🆕
   - hidden=true используется, но нет редактирования base64 encoded keys

4. **[SQLI-003-A] Regex Injection в ServiceComposeParser** - [sql-injection.md](database/sql-injection.md) 🆕
   - Файл: `ServiceComposeParser.php:398,430`
   - Regex metacharacters не экранируются

### 🟡 Низкий приоритет

1. **[SQLI-007] whereRaw("1=0") anti-pattern** - Безопасно, но не best practice

---

## Исправления

| ID | Описание | Файл | Статус |
|----|----------|------|--------|
| SSH-006 | Command Injection в git_commit_sha | `bootstrap/helpers/api.php`, `app/Models/Application.php` | ✅ Fixed |
| SSH-005 | Container name без escaping | `app/Jobs/ScheduledTaskJob.php` | ✅ Fixed |
| SSH-002 | Container name в backup/restore | `DatabaseBackupJob.php`, `DatabaseRestoreJob.php` | ✅ Fixed |
| SSH-012 | Cleanup SSH ключей | `HandlesGitOperations.php` | ⏳ Pending |
| CMD-001 | Redis KEYS pattern injection | `DatabaseMetricsController.php` | ✅ Fixed |
| CMD-002 | PostgreSQL query injection | `DatabaseMetricsController.php` | ✅ Fixed |
| CMD-003 | MySQL query injection | `DatabaseMetricsController.php` | ✅ Fixed |
| CMD-004 | ClickHouse query injection | `DatabaseMetricsController.php` | ✅ Fixed |
| AUTHZ-ALL | 10+ Policies отключены | `app/Policies/*.php` | ⏳ **СРОЧНО** |
| AUTH-015 | API Tokens никогда не истекают | `config/sanctum.php` | ⏳ Pending |
| API-011 | CORS открыт для всех | `config/cors.php` | ⏳ Pending |
| API-033 | Webhook сигнатуры не проверяются | `app/Http/Controllers/Webhook/*` | ✅ Fixed |
| API-015 | Mass Assignment уязвимость | `EnvironmentVariable.php`, Controllers | ⏳ Pending |
| ENV-006 | EnvironmentVariablePolicy отключена | `app/Policies/EnvironmentVariablePolicy.php` | ✅ Fixed |
| ENV-007 | SharedEnvironmentVariablePolicy отключена | `app/Policies/SharedEnvironmentVariablePolicy.php` | ✅ Fixed |
| ENV-017 | Env key injection | `EnvironmentVariable.php`, `SharedEnvironmentVariable.php` | ✅ Fixed |
| SENS-009 | Env values в Inertia props | `ApplicationController.php:519` | ✅ Fixed |
| SENS-013 | Export без warning | `Variables.tsx:94-109` | ✅ Fixed |
| WH-001-F | Webhook signature bypass в dev mode | `Github.php`, `Bitbucket.php`, `Gitea.php` | ✅ Fixed |
| WH-002-F | GitLab MR без проверки автора | `Gitlab.php` | ✅ Fixed |
| WH-003-F | Bitbucket/Gitea PR без проверки автора | `Bitbucket.php`, `Gitea.php` | ✅ Fixed |
| WH-004-F | GitLab token non-timing-safe | `Gitlab.php` | ✅ Fixed |
| WH-005-F | Stripe error message exposure | `Stripe.php` | ✅ Fixed |
| JOB-001-F | SSRF в SendWebhookJob | `SendWebhookJob.php`, `SendTeamWebhookJob.php` | ✅ Fixed |
| JOB-002-F | Command Injection в VolumeCloneJob | `VolumeCloneJob.php` | ✅ Fixed |
| EXPOSE-001-F | Webhook secrets exposed in Inertia | `ApplicationController.php` | ✅ Fixed |
| EXPOSE-002-F | Full models exposed to frontend | `Application.php`, `Server.php` | ✅ Fixed |

---

## Инструкции для агентов

### Как проверять гипотезу:

1. Открыть соответствующий файл чеклиста
2. Найти гипотезу со статусом `[ ]`
3. Изменить статус на `[🔍]`
4. Провести анализ кода
5. Обновить статус:
   - `[✅]` если проблем нет (добавить краткий комментарий)
   - `[⚠️]` или `[🔴]` если найдена проблема
6. При нахождении проблемы:
   - Добавить описание в секцию "Findings" файла
   - Создать issue или исправить код
   - После исправления обновить статус на `[🔧]`

### Приоритет проверки:

1. 🔴 Critical - проверять первыми
2. 🟡 High - после критических
3. 🟢 Medium - в последнюю очередь

---

## Связанные документы

- [.ai/patterns/security-patterns.md](../.ai/patterns/security-patterns.md) - паттерны безопасности
- [CLAUDE.md](../CLAUDE.md) - инструкции проекта
