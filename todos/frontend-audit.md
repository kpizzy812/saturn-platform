# Аудит фронтенда Saturn: найденные проблемы

---

## 🔴 КРИТИЧЕСКИЕ ПРОБЛЕМЫ (требуют немедленного исправления)

### Безопасность

- [x] **XSS уязвимость в QR коде** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Auth/TwoFactor/Setup.tsx:139`
  - Проблема: `dangerouslySetInnerHTML={{ __html: qrCode }}` без санитизации
  - Решение: Добавлен DOMPurify.sanitize() с SVG профилем
  - Также исправлено: backup codes теперь приходят с бэкенда (убраны захардкоженные)

### Mock данные в продакшн коде

- [x] **Mock webhooks в Integrations** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Integrations/Webhooks.tsx:29-94`
  - Проблема: Захардкоженные `mockWebhooks` и `mockDeliveries` используются в useState
  - Решение:
    - Создана миграция `team_webhooks` и `webhook_deliveries` таблиц
    - Созданы модели `app/Models/TeamWebhook.php` и `app/Models/WebhookDelivery.php`
    - Создан API контроллер `app/Http/Controllers/Api/TeamWebhooksController.php`
    - Создан Job `app/Jobs/SendTeamWebhookJob.php`
    - Добавлен hook `resources/js/hooks/useWebhooks.ts`
    - Добавлены API routes в `routes/api.php`
    - Добавлены web routes для Inertia в `routes/web.php`
    - Удалены все mock данные из фронтенда

- [x] **Mock webhooks в Services** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Services/Webhooks.tsx:34-98`
  - Проблема: Аналогичные mock данные для webhooks
  - Решение: Использует общую систему Team Webhooks, удалены все mock данные

- [x] **Mock notifications** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Notifications/Index.tsx:14-72`
  - Проблема: `MOCK_NOTIFICATIONS` массив используется как fallback
  - Решение:
    - Удалены mock данные из фронтенда
    - Создана миграция `user_notifications` таблицы
    - Создана модель `app/Models/UserNotification.php`
    - Создан API контроллер `app/Http/Controllers/Api/NotificationsController.php`
    - Добавлены API routes в `routes/api.php`
    - Добавлены web routes для Inertia в `routes/web.php`
    - Обновлён hook `useNotifications.ts` для реальных API вызовов

- [x] **Mock user в Settings** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Settings/Account.tsx:21`
  - Проблема: Fallback к `{ id: 1, name: 'John Doe', email: 'john@example.com' }`
  - Решение:
    - Исправлены типы Inertia PageProps в `resources/js/types/inertia.d.ts` для соответствия middleware
    - Добавлены типы `AuthUser` и `SharedTeam` для shared props
    - Удалён mock fallback, используется типизированный `props.auth`
    - Добавлена корректная обработка null (экран "Authentication Required")
    - Инициализация 2FA статуса из `currentUser.two_factor_enabled`

---

## 🟠 ВЫСОКИЕ ПРОБЛЕМЫ (неработающие кнопки и функционал)

### Кнопки без API интеграции

- [x] **Services Settings - Save/Delete не работают** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Services/Settings.tsx:17-27`
  - Кнопки на строках: 70, 100, 170 (Save Changes), 229 (Delete Service)
  - Проблема: Только показывают toast, не отправляют данные на сервер
  - Решение:
    - Добавлен `router` из `@inertiajs/react` и состояния загрузки
    - `handleSaveGeneral` - сохраняет name/description через `PATCH /api/v1/services/{uuid}`
    - `handleSaveDockerCompose` - сохраняет docker_compose_raw (с base64 кодированием)
    - `handleDelete` - удаляет сервис через `DELETE /api/v1/services/{uuid}` с двойным подтверждением
    - Resource Limits отключена (API не поддерживает эти поля, добавлен TODO)

- [x] **Notifications Preferences - Save не работает** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Notifications/Preferences.tsx:70-76`
  - Кнопка на строке: 102 (Save Changes)
  - Проблема: `handleSave` имитирует загрузку через setTimeout, но не сохраняет на бэкенд
  - Решение:
    - Создана миграция `user_notification_preferences` таблицы
    - Создана модель `app/Models/UserNotificationPreference.php`
    - Добавлены API endpoints `GET/PUT /api/v1/notifications/preferences`
    - Frontend загружает preferences при рендере и сохраняет через `router.put()`

- [x] **Services Scaling - Apply Changes не работает** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Services/Scaling.tsx`
  - Проблема: Только `console.log()`, не вызывает API
  - Решение:
    - Создана миграция `add_scaling_fields_to_services_table` для полей resource limits
    - Обновлена модель `app/Models/Service.php` с методами `getLimits()` и `hasResourceLimits()`
    - Обновлён API контроллер `app/Http/Controllers/Api/ServicesController.php` для поддержки scaling
    - Обновлён `bootstrap/helpers/parsers.php` для применения limits при генерации docker-compose
    - Полностью переписан фронтенд компонент с реальными слайдерами для CPU/Memory limits
    - Добавлен unit тест `tests/Unit/ServiceResourceLimitsTest.php`
    - Обновлён тип Service в `resources/js/types/models.ts`
    - Примечание: Функции replicas/auto-scaling/regions/sleep mode удалены как нереализуемые в текущей архитектуре (требуют Docker Swarm/Kubernetes)

- [x] **Database Restart не работает** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Databases/Overview.tsx:73-81`
  - Проблема: `handleRestart` имитирует перезагрузку через setTimeout
  - Решение: Реализован реальный API вызов к `POST /databases/{uuid}/restart` через Inertia router
    - Добавлен `useToast` для уведомлений об успехе/ошибке
    - Используется существующий web route `databases.restart` который вызывает `RestartDatabase::run()`

### Memory leaks и утечки

- [x] **Window pollution в ProjectCanvas** ⚠️ НЕ АКТУАЛЬНО
  - Файл: `resources/js/components/features/canvas/ProjectCanvas.tsx:164-171, 533-548`
  - Файл: `resources/js/pages/Projects/Show.tsx:533-546`
  - Проблема: Глобальные переменные `window.__projectCanvas*` - потенциальные race conditions
  - Статус: Проверено - `window.__projectCanvas` не найден в коде, возможно уже рефакторинг

- [x] **useEffect без cleanup в Terminal** ⚠️ НЕ АКТУАЛЬНО
  - Файл: `resources/js/components/features/Terminal.tsx:103-206`
  - Проблема: Несколько useEffect создают слушатели без cleanup
  - Статус: Проверено - все useEffect уже имеют cleanup функции с removeEventListener и dispose()

- [x] **setTimeout без cleanup** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Auth/TwoFactor/Setup.tsx:50, 56`
  - Проблема: `setTimeout(() => setCopiedCode(false), 2000)` без cleanup
  - Решение: Добавлены useRef для таймеров + useEffect cleanup при размонтировании

### Обработка ошибок

- [x] **Silent fail в BuildLogs** ⚠️ НЕ АКТУАЛЬНО
  - Файл: `resources/js/pages/Deployments/BuildLogs.tsx:57`
  - Проблема: `.catch(() => setIsLoading(false))` - ошибка игнорируется
  - Статус: Проверено - в файле нет `.catch()` вообще, код был переписан

- [x] **Weak catch в Tokens** ⚠️ НЕ АКТУАЛЬНО
  - Файл: `resources/js/pages/Settings/Tokens.tsx:50`
  - Проблема: `response.json().catch(() => ({}))` - silent fail
  - Статус: Проверено - это валидный паттерн для защиты от ошибки парсинга JSON; реальная ошибка обрабатывается внешним try-catch с toast

---

## 🟡 СРЕДНИЕ ПРОБЛЕМЫ

### TODO комментарии (незавершённая функциональность)

- [x] **Boarding - создание сервера** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Boarding/Index.tsx:95`
  - Проблема: `// TODO: Create server via API` - отсутствовал SSH ключ в форме
  - Решение:
    - Добавлен `privateKeys` в props route `/boarding` (routes/web.php)
    - Добавлен UI для выбора существующего SSH ключа или ввода нового
    - Добавлена валидация IP, порта и SSH ключа (используется lib/validation)
    - Исправлен `handleServerSubmit` для отправки `private_key_id` или `private_key`
    - Заменены все `alert()` на `useConfirm` hook
    - Удалён неиспользуемый код `deployType`/`setDeployType`

- [x] **Projects Show - deployment UUID** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Projects/Show.tsx:1306`
  - Проблема: `// TODO: Need deployment UUID for real API call` + mock данные
  - Решение:
    - Заменены mock deployments на реальные данные через `useDeployments` hook
    - Реализован `handleCancel` с `POST /api/v1/deployments/{uuid}/cancel`
    - Реализован `handleRollback` с `POST /api/v1/applications/{uuid}/rollback/{deploymentUuid}`
    - Заменены `window.confirm` на `useConfirm` hook
    - Добавлены состояния загрузки для cancel/rollback операций
    - Удалена кнопка "Remove" (нет API для удаления deployment из истории)

### UX проблемы

- [x] **confirm() вместо модальных окон (~80 мест)** ✅ ИСПРАВЛЕНО
  - Файлы: SharedVariables/Show.tsx:51, Applications/Index.tsx:224 и др.
  - Проблема: Нативный `confirm()` не соответствует дизайну системы
  - Решение: Создан компонент `ConfirmationModal` + хук `useConfirm` + провайдер `ConfirmationProvider`
  - Заменено во всех 46 файлах:
    - [x] Dashboard, Projects (Index, Environments, Variables)
    - [x] Applications (Index, Deployments, DeploymentDetails, Previews/Show, Settings/Domains)
    - [x] Services (Index, Show, Deployments, Domains, Variables, Settings)
    - [x] Databases (Overview, Show, Backups, Settings/Index) + панели (MySQL, PostgreSQL, Redis)
    - [x] Servers (Index, Settings, Cleanup, LogDrains, PrivateKeys, Proxy/*, Sentinel/Alerts)
    - [x] Admin (Settings, Users/Index, Users/Show)
    - [x] Auth (AcceptInvite, OAuth/Connect)
    - [x] Misc (CronJobs, Domains, Environments, Observability, Destinations, ScheduledTasks)
    - [x] Sources (GitHub, GitLab, Bitbucket), Storage, Tags
    - [x] Components (DatabaseCard, PreviewCard)

### localStorage использование

- [ ] **Theme в localStorage**
  - Файл: `resources/js/components/layout/Header.tsx:20, 31, 34`
  - Проблема: Хранение в localStorage (OK для theme, но паттерн опасен)
  - Решение: Убедиться что sensitive данные не попадут в localStorage

- [ ] **Notifications sound в localStorage**
  - Файл: `resources/js/pages/Notifications/Index.tsx:94, 105`
  - Проблема: Sound preferences в localStorage
  - Решение: Оставить как есть (не sensitive), но документировать политику

---

## 🟢 НИЗКИЕ ПРОБЛЕМЫ

### TypeScript качество

- [ ] **Чрезмерное использование `as any` (60+ мест)**
  - Файлы: BuildLogs.tsx:406, Projects/Show.tsx:533, Databases/Overview.tsx:52-62 и др.
  - Проблема: Обход TypeScript компилятора, потеря типобезопасности
  - Решение: Постепенно заменять на правильные interface definitions
  - Приоритетные файлы:
    - [ ] `resources/js/pages/Databases/Overview.tsx`
    - [ ] `resources/js/pages/Deployments/BuildLogs.tsx`
    - [ ] `resources/js/pages/Projects/Show.tsx`

### Placeholder URLs

- [x] **Примеры URL с XXX** ✅ ИСПРАВЛЕНО
  - Файл: `resources/js/pages/Integrations/Webhooks.tsx:43`
  - Файл: `resources/js/pages/Services/Webhooks.tsx:45`
  - Проблема: URL типа `https://hooks.slack.com/services/T00/B00/XXXX`
  - Решение: Удалены вместе с mock данными при реализации реального API

---

## Статистика

| Категория | Количество | Исправлено | Критичность |
|-----------|-----------|------------|-------------|
| XSS уязвимость | 1 | ✅ 1 | 🔴 Критическая |
| Mock данные | 5 | ✅ 4 | 🔴 Критическая |
| Неработающие кнопки | 4 | ✅ 4 | 🟠 Высокая |
| Memory leaks | 3 | ✅ 3 (1 fix + 2 n/a) | 🟠 Высокая |
| Обработка ошибок | 2 | ✅ 2 (n/a) | 🟠 Высокая |
| TODO незавершённые | 2 | ✅ 2 | 🟡 Средняя |
| confirm() → Modal | ~80 | ✅ 46 файлов | 🟡 Средняя |
| localStorage | 2 | 0 | 🟡 Средняя |
| TypeScript any | 60+ | 0 | 🟢 Низкая |

**Прогресс: 17 из ~25 критических/высоких/средних проблем исправлено (4 были неактуальны)**

---

## Созданные файлы (Notifications система)

- `database/migrations/2026_01_23_104535_create_user_notifications_table.php`
- `app/Models/UserNotification.php`
- `app/Http/Controllers/Api/NotificationsController.php`
- Обновлены: `routes/api.php`, `routes/web.php`, `resources/js/hooks/useNotifications.ts`

## Созданные файлы (Webhooks система)

- `database/migrations/2026_01_23_135645_create_team_webhooks_table.php`
- `app/Models/TeamWebhook.php`
- `app/Models/WebhookDelivery.php`
- `app/Http/Controllers/Api/TeamWebhooksController.php`
- `app/Jobs/SendTeamWebhookJob.php`
- `resources/js/hooks/useWebhooks.ts`
- `tests/Unit/TeamWebhookTest.php`
- `tests/Unit/WebhookDeliveryTest.php`
- Обновлены: `routes/api.php`, `routes/web.php`, `app/Models/Team.php`
- Обновлены: `resources/js/pages/Integrations/Webhooks.tsx`, `resources/js/pages/Services/Webhooks.tsx`

## Обновлённые файлы (Settings Account + Inertia Types)

- `resources/js/types/inertia.d.ts` - исправлены типы PageProps для соответствия HandleInertiaRequests middleware
- `resources/js/pages/Settings/Account.tsx` - удалён mock fallback, добавлена типизация и обработка null

## Созданные/Обновлённые файлы (Services Scaling)

- `database/migrations/2026_01_23_113426_add_scaling_fields_to_services_table.php` - миграция для полей resource limits
- `app/Models/Service.php` - добавлены методы `getLimits()` и `hasResourceLimits()`, OpenAPI документация
- `app/Http/Controllers/Api/ServicesController.php` - поддержка scaling полей в update_by_uuid
- `bootstrap/helpers/parsers.php` - применение resource limits при генерации docker-compose
- `resources/js/pages/Services/Scaling.tsx` - полностью переписанный компонент с реальными слайдерами
- `resources/js/types/models.ts` - добавлены поля resource limits в тип Service
- `tests/Unit/ServiceResourceLimitsTest.php` - unit тесты для Service resource limits

---

## План исправления

### Этап 1: Критические (до продакшна)
1. ~~Исправить XSS в TwoFactor/Setup.tsx~~ ✅
2. ~~Удалить mock данные из Notifications/Index.tsx~~ ✅
3. ~~Удалить mock данные из Webhooks~~ ✅ (Integrations/Webhooks.tsx и Services/Webhooks.tsx)
4. ~~Удалить mock данные из Settings/Account.tsx~~ ✅

### Этап 2: Высокие (неделя 1)
4. ~~Реализовать API для Services/Settings.tsx~~ ✅
5. ~~Реализовать API для Notifications/Preferences.tsx~~ ✅
6. ~~Реализовать API для Services/Scaling.tsx~~ ✅
7. ~~Реализовать API для Databases/Overview.tsx restart~~ ✅
8. ~~Исправить memory leaks в Terminal и ProjectCanvas~~ ✅ (setTimeout fix + остальные были неактуальны)

### Этап 3: Средние (неделя 2)
9. ~~Создать ConfirmationModal компонент~~ ✅
10. ~~Заменить все confirm() вызовы (46 файлов)~~ ✅
11. Завершить TODO функциональность

### Этап 4: Низкие (ongoing)
12. Постепенно исправлять `as any` типизацию

---

## Созданные файлы (ConfirmationModal система)

- `resources/js/components/ui/ConfirmationModal.tsx` - компонент модального окна подтверждения
  - `ConfirmationModal` - базовый компонент
  - `useConfirmation` - хук для управления состоянием модалки
  - `ConfirmationProvider` - провайдер для глобального использования
  - `useConfirm` - хук для Promise-based подтверждения (замена `confirm()`)
- Обновлены: `resources/js/components/ui/index.ts`, `resources/js/app.tsx`
- Полный список обновлённых файлов (46 файлов):
  - Pages: Dashboard, Projects/*, Applications/*, Services/*, Databases/*, Servers/*, Admin/*, Auth/*, CronJobs/*, Domains/*, Environments/*, Observability/*, Destinations/*, ScheduledTasks/*, Sources/*, Storage/*, Tags/*
  - Components: DatabaseCard, PreviewCard, MySQLPanel, PostgreSQLPanel, RedisPanel

## Обновлённые файлы (Boarding - создание сервера)

- `routes/web.php` - добавлен `privateKeys` в props route `/boarding`
- `resources/js/pages/Boarding/Index.tsx`:
  - Добавлен интерфейс `PrivateKey[]` в Props
  - Добавлено состояние для SSH ключа (`keyMode`, `selectedKeyId`, `privateKeyContent`)
  - Добавлена валидация IP, порта и SSH ключа с функциями из `lib/validation`
  - Обновлён `ServerStep` компонент с UI выбора SSH ключа (existing/new)
  - Исправлен `handleServerSubmit` для отправки `private_key_id` или `private_key`
  - Заменены все `alert()` на `useConfirm` hook
  - Удалён неиспользуемый код `deployType`/`setDeployType`
