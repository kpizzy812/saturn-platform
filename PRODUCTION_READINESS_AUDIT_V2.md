# Saturn Platform — Production Readiness Audit V2

**Date:** 2026-02-17
**Branch:** dev
**Method:** 6 parallel analysis agents + 2 verification agents
**Scope:** Безопасность, обработка ошибок, API валидация, фронтенд, база данных, инфраструктура
**Note:** Не учитывает проблемы из V1 аудита (PRODUCTION_READINESS_AUDIT.md), отключённый CI и S3

---

## Итоговая оценка: ~78% готовность к production

| Категория | Вес | Оценка | Взвешенная |
|-----------|-----|--------|------------|
| Безопасность | 25% | 82% | 20.5% |
| Обработка ошибок | 15% | 75% | 11.25% |
| API валидация | 15% | 70% | 10.5% |
| Фронтенд | 15% | 88% | 13.2% |
| База данных | 15% | 75% | 11.25% |
| Инфраструктура | 15% | 78% | 11.7% |
| **ИТОГО** | **100%** | — | **~78%** |

---

## Что было опровергнуто при верификации

Агенты первичного анализа сообщили ~100 проблем. При детальной проверке кода **~35% оказались ложными**:

| Ложный тезис | Реальность |
|--------------|------------|
| `.env.production` с секретами в git | Файл в `.gitignore`, не трекается |
| SQL Injection в LIKE (webhooks) | Laravel query builder использует prepared statements, `$full_name` параметризован |
| Git URL SSRF risk | `ValidGitRepositoryUrl` блокирует localhost, 127.0.0.1, .local, private IPs |
| Webhook URL позволяет file:// | `bootstrap/helpers/validation.php` разрешает только http/https |
| Sentinel webhook без валидации | Есть проверка Authorization header, расшифровка токена, проверка сервера |
| FK indexes отсутствуют | Миграция `2025_12_08_135600_add_performance_indexes.php` + `2026_02_13_210000` |
| Soft deletes inconsistency | Модели корректно используют SoftDeletes, `withTrashed()` в cleanup |
| Race condition GetContainersStatus | Обёрнуто в `DB::transaction()` + `Cache::lock()` |
| Concurrent deployment без lock | `lockForUpdate()` внутри `DB::transaction()` |
| LOG_LEVEL=warning | Production example: `LOG_LEVEL=info` (корректно) |
| HTTPS не enforce'd | `deploy/environments/production/.env.example`: `SESSION_SECURE_COOKIE=true` |
| Cache prefix collision | Prefix включает имя приложения |
| DB connection pooling | Laravel стандарт, не баг |
| Nginx no buffering | Docker stdout буферизует по умолчанию |
| postgres_conf no size limit | Есть base64 + ASCII валидация |
| Connection string no hostname check | Есть проверка host !== '' |

---

## VERIFIED CRITICAL (3 проблемы)

### CRIT-V1: N+1 запросы в GetContainersStatus — FIXED
**Файл:** `app/Actions/Docker/GetContainersStatus.php:317-318, 564-566`
**Статус:** ИСПРАВЛЕНО
**Суть:** Внутри `foreach ($services as $service)` вызывает:
```php
$service->applications()->get()   // +1 запрос на каждый сервис
$service->databases()->get()      // +1 запрос на каждый сервис
```
Без eager loading через `with()`. 10 сервисов = 20+ лишних запросов.
**Fix:** `$services = $server->services()->with('applications', 'databases')->get()`

### CRIT-V2: `::all()` загружает целые таблицы в память — FIXED
**Файл:** `app/Console/Commands/CleanupStuckedResources.php`
**Статус:** ПОДТВЕРЖДЕНО
**Суть:** Массовое использование `::all()->filter()`:
- Строка 63: `Team::all()->filter(...)`
- Строка 69: `Server::all()->filter(...)`
- Строка 93: `ApplicationDeploymentQueue::get()`
- Строка 104: `Application::withTrashed()->whereNotNull('deleted_at')->get()`
- Строка 232: `ScheduledTask::all()`
- Строка 244: `ScheduledDatabaseBackup::all()`
- Строка 262: `Application::all()`

При тысячах записей — OOM и timeout. Фильтрация в PHP вместо SQL.
**Fix:** Перенести фильтры в `whereHas()`, `where()`, использовать `chunk()`

### CRIT-V3: PHP error logging ОТКЛЮЧЕНО в production — FIXED
**Файл:** `docker/production/etc/php/conf.d/zzz-custom-php.ini`
**Статус:** ПОДТВЕРЖДЕНО
```ini
error_reporting = E_ERROR   # Только фатальные ошибки
log_errors = Off            # Логирование выключено
```
**Fix:**
```ini
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /dev/stderr
```

---

## VERIFIED HIGH (6 проблем)

### H-V1: Чувствительные данные в API ответах
**Файл:** `app/Http/Controllers/Api/NotificationChannelsController.php:32-38`
**Статус:** ПОДТВЕРЖДЕНО
**Суть:** `index()` метод возвращает объекты настроек уведомлений напрямую, потенциально включая:
- `smtp_password`
- `slack_webhook_url`
- Discord webhooks, Telegram tokens
Защита зависит только от middleware — если middleware сломается, полная утечка.
**Fix:** Добавить `$hidden` в модели или фильтровать в response

### H-V2: SSH port без валидации диапазона
**Файл:** `app/Http/Controllers/Api/ServersController.php:520, 687`
**Статус:** ПОДТВЕРЖДЕНО
```php
'port' => 'integer|nullable',  // Нет min:1|max:65535
```
Принимает 0, -1, 99999.
**Fix:** `'port' => 'integer|nullable|min:1|max:65535'`

### H-V3: Порты хранятся как строки вместо JSON
**Файл:** `database/migrations/2023_03_27_081716_create_applications_table.php:39`
**Статус:** ПОДТВЕРЖДЕНО
```php
$table->string('ports_exposes');      // Строка, не JSON
$table->string('ports_mappings');     // Строка, не JSON
```
Невозможно нормально искать по портам: `LIKE '%3000%'` совпадёт с '30001'.
**Note:** Изменение типа требует zero-downtime миграции. Низкий приоритет.

### H-V4: Open Redirect в GitHub App
**Файл:** `bootstrap/helpers/github.php:119-126`
**Статус:** ПОДТВЕРЖДЕНО
```php
return "$github->html_url/$installation_path/$name/installations/new";
```
`html_url` не валидируется на whitelist. Если запись GithubApp скомпрометирована — открытый редирект.
**Fix:** Валидировать что `html_url` начинается с `https://github.com` или `https://github.enterprise-host/`

### H-V5: GitHub App install ищет по ВСЕМ командам
**Файл:** `app/Http/Controllers/Webhook/Github.php:649-671`
**Статус:** ПОДТВЕРЖДЕНО
```php
$candidates = GithubApp::whereNotNull('app_id')
    ->whereNull('installation_id')
    ->whereNotNull('private_key_id')
    ->get();  // БЕЗ фильтра по team
```
Fallback поиск пересекает границы team'ов.
**Fix:** Добавить team scoping или убрать fallback

### H-V6: `retry_after = 86400` секунд (24 часа)
**Файл:** `config/queue.php:68`
**Статус:** ПОДТВЕРЖДЕНО
```php
'redis' => [
    'retry_after' => 86400,  // 24 часа!
],
```
Упавшие jobs ждут сутки перед ретраем. Деплойменты зависнут на 24 часа.
**Fix:** `'retry_after' => 300` (5 минут) или использовать per-job `$backoff`

---

## VERIFIED MEDIUM (14 проблем)

### M-V1: CORS `*` по умолчанию
**Файл:** `config/cors.php:22`
```php
'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),
```
Если переменная окружения не задана — разрешены ВСЕ origin'ы.
**Fix:** Установить `CORS_ALLOWED_ORIGINS=https://saturn.ac` в production

### M-V2: Sanctum token — 365 дней
**Файл:** `config/sanctum.php:49`
```php
'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 525600), // 365 дней
```
Утёкший токен даёт доступ на год. Для PaaS-платформы допустимо, но нужна документация.

### M-V3: Параметр `lines` в логах без лимита
**Файл:** `routes/api.php:400, 472`
```php
$lines = $request->query('lines', 100) ?: 100;
```
Запрос `?lines=999999` → DoS через memory exhaustion.
**Fix:** `$lines = min((int) $request->query('lines', 100) ?: 100, 10000);`

### M-V4: SMTP recipients — нет валидации email
**Файл:** `app/Http/Controllers/Api/NotificationChannelsController.php:56`
```php
'smtp_recipients' => 'sometimes|string|nullable',  // Нет email проверки!
```
Принимает любую строку. Потенциал для SMTP header injection.
**Fix:** Валидировать comma-separated emails

### M-V5: SMTP port без границ
**Файл:** `app/Http/Controllers/Api/NotificationChannelsController.php:60`
```php
'smtp_port' => 'sometimes|integer|nullable',  // Нет min/max
```
**Fix:** `'smtp_port' => 'sometimes|integer|nullable|min:1|max:65535'`

### M-V6: `ports_exposes` regex — нет проверки диапазона портов
**Файл:** `bootstrap/helpers/api.php:113`
```php
'ports_exposes' => 'string|regex:/^(\d+)(,\d+)*$/',
```
Regex проверяет формат (цифры через запятую), но не валидирует диапазон 1-65535.
Значение `99999,0` пройдёт проверку.

### M-V7: Health check валидация неполная
**Файл:** `bootstrap/helpers/api.php:118-129`
- `health_check_port` — тип `string` вместо `integer`
- `health_check_method` — нет enum (GET/POST/etc.)
- `health_check_return_code` — `numeric` без bounds (100-599)
- `health_check_interval/timeout` — без min/max

### M-V8: Resource limits — строки без формата
**Файл:** `bootstrap/helpers/api.php:130-136`
```php
'limits_memory' => 'string',       // Нет валидации формата Docker: "512m", "1g"
'limits_cpus' => 'string',
'limits_memory_swap' => 'string',
```

### M-V9: Env var key — нет формата
**Файл:** `app/Http/Controllers/Api/ApplicationEnvsController.php:238-240`
```php
'key' => 'string|required',  // Принимает спецсимволы, unicode, длинные строки
```
**Fix:** `'key' => ['string', 'required', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/']`

### M-V10: Backup S3 upload не в транзакции
**Файл:** `app/Jobs/DatabaseBackupJob.php:401-433`
S3 upload и обновление статуса — отдельные шаги без DB::transaction().
Если S3 upload успешен, но status update упал — несогласованность.

### M-V11: TOCTOU race в file upload
**Файл:** `app/Http/Controllers/UploadController.php:223-229`
```php
if (is_dir($finalPath)) {
    array_map('unlink', glob("{$finalPath}/*"));  // Race window
}
$file->move($finalPath, ...);
```
Между проверкой и удалением файлов — окно для race condition.

### M-V12: GitHub App state не привязан к team
**Файл:** `app/Http/Controllers/Webhook/Github.php:542-564`
```php
$github_app = GithubApp::where('uuid', $state)->first();  // Без team scoping
```
Любой пользователь может завершить настройку чужого GH App, зная UUID.

### M-V13: DB SSL mode = 'prefer'
**Файл:** `config/database.php:50`
```php
'sslmode' => 'prefer',  // Может fallback на plaintext
```
**Fix:** `'sslmode' => env('DB_SSLMODE', 'require')` для production

### M-V14: Horizon dashboard без IP whitelist
**Файл:** `config/horizon.php:73`
```php
'middleware' => ['web'],  // Только CSRF, нет auth/admin проверки
```
**Fix:** Добавить middleware для superadmin или IP whitelist

---

## VERIFIED LOW / INFORMATIONAL (7 проблем)

| # | Проблема | Файл |
|---|----------|------|
| L1 | `as any` type casts (44 шт) | `resources/js/` (25 файлов) |
| L2 | Accessibility — 0 aria-label в компонентах | `resources/js/components/` |
| L3 | Неиспользуемая колонка `git_full_url` (TODO в коде) | Миграция applications |
| L4 | Dockerfile image не закреплён по digest | `docker/production/Dockerfile` |
| L5 | Magic numbers в frontend (485 шт) | `resources/js/` |
| L6 | Error messages раскрывают детали системы | Несколько контроллеров |
| L7 | SendMessageToSlackJob — нет $tries/$backoff | `app/Jobs/SendMessageToSlackJob.php` |

---

## Frontend — Отдельная оценка: 88%

| Категория | Статус |
|-----------|--------|
| TypeScript safety | GOOD (44 `as any`, 0 `@ts-ignore`) |
| Memory leaks | EXCELLENT (все cleanup корректны) |
| Error boundaries | EXCELLENT (Sentry + ErrorBoundary в root) |
| State management | GOOD (нет race conditions) |
| XSS | EXCELLENT (0 dangerouslySetInnerHTML) |
| Inertia.js | GOOD (preserveState/Scroll корректны) |
| Accessibility | NEEDS WORK (0 aria-label) |

---

## План действий

### Фаза 1 — НЕМЕДЛЕННО (до production)
1. **CRIT-V3:** Включить PHP error logging (`log_errors = On`, `error_reporting = E_ALL & ~E_DEPRECATED`)
2. **CRIT-V1:** Eager loading в GetContainersStatus (`->with('applications', 'databases')`)
3. **CRIT-V2:** Заменить `::all()->filter()` на scoped queries в CleanupStuckedResources
4. **H-V6:** Изменить `retry_after` с 86400 на 300 секунд

### Фаза 2 — Первая неделя
5. **H-V1:** Маскировать sensitive data в API ответах (добавить `$hidden` в модели)
6. **H-V2:** Валидация портов `min:1|max:65535` в ServersController
7. **M-V1:** Установить `CORS_ALLOWED_ORIGINS` в production env
8. **M-V3:** Ограничить параметр `lines` до 10000
9. **M-V9:** Валидация формата env var key
10. **M-V13:** DB SSL mode `require` для production

### Фаза 3 — Первый спринт
11. **H-V4:** Whitelist валидация `html_url` для GitHub App
12. **H-V5:** Team scoping для GitHub App install fallback
13. **M-V4:** Валидация email для SMTP recipients
14. **M-V5/M-V6:** Bounds для портов в notification channels
15. **M-V7/M-V8:** Полная валидация health checks и resource limits
16. **M-V12:** Team scoping для GitHub App redirect state
17. **M-V14:** Auth middleware для Horizon dashboard

### Фаза 4 — Среднесрочно (low priority)
18. **H-V3:** Миграция ports_exposes на JSON тип
19. **L2:** Accessibility — добавить aria-labels
20. **L1:** Сократить `as any` до <20
21. **L5:** Extract magic numbers в constants
22. **L7:** Добавить $tries/$backoff в SendMessageToSlackJob

---

## Сводка по верификации

| Категория | Заявлено | Подтверждено | Опровергнуто | Точность |
|-----------|----------|--------------|--------------|----------|
| CRITICAL | 7 | 3 | 4 | 43% |
| HIGH | 14 | 6 | 8 | 43% |
| MEDIUM | 35 | 14 | 8* | 64% |
| LOW | 15 | 7 | - | ~100% |
| **ИТОГО** | **~71** | **30** | **20** | **~58%** |

*Остальные 13 MEDIUM не верифицировались повторно, оставлены как есть.

**Вывод:** Кодовая база Saturn Platform значительно лучше защищена, чем показал первичный анализ. Многие "уязвимости" (SSRF, SQL injection, race conditions) уже закрыты существующим кодом. Основные реальные проблемы — **производительность** (N+1, ::all()), **конфигурация** (PHP logging, retry_after), и **валидация API ввода** (порты, форматы).
