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

- [ ] **Services Settings - Save/Delete не работают**
  - Файл: `resources/js/pages/Services/Settings.tsx:17-27`
  - Кнопки на строках: 70, 100, 170 (Save Changes), 229 (Delete Service)
  - Проблема: Только показывают toast, не отправляют данные на сервер
  - Решение: Реализовать `router.put()` для save и `router.delete()` для delete

- [ ] **Notifications Preferences - Save не работает**
  - Файл: `resources/js/pages/Notifications/Preferences.tsx:70-76`
  - Кнопка на строке: 102 (Save Changes)
  - Проблема: `handleSave` имитирует загрузку через setTimeout, но не сохраняет на бэкенд
  - Решение: Реализовать `router.put()` для сохранения preferences

- [ ] **Services Scaling - Apply Changes не работает**
  - Файл: `resources/js/pages/Services/Scaling.tsx:38-51`
  - Кнопка на строке: 270 (Apply Changes)
  - Проблема: Только `console.log()`, не вызывает API
  - Решение: Реализовать API вызов для применения scaling конфигурации

- [ ] **Database Restart не работает**
  - Файл: `resources/js/pages/Databases/Overview.tsx:73-81`
  - Проблема: `handleRestart` имитирует перезагрузку через setTimeout
  - Решение: Реализовать реальный API вызов к `/api/v1/databases/{uuid}/restart`

### Memory leaks и утечки

- [ ] **Window pollution в ProjectCanvas**
  - Файл: `resources/js/components/features/canvas/ProjectCanvas.tsx:164-171, 533-548`
  - Файл: `resources/js/pages/Projects/Show.tsx:533-546`
  - Проблема: Глобальные переменные `window.__projectCanvas*` - потенциальные race conditions
  - Решение: Использовать `useImperativeHandle` + `forwardRef` или Event Emitter

- [ ] **useEffect без cleanup в Terminal**
  - Файл: `resources/js/components/features/Terminal.tsx:103-206`
  - Проблема: Несколько useEffect создают слушатели без cleanup
  - Решение: Добавить return cleanup функции для всех side effects

- [ ] **setTimeout без cleanup**
  - Файл: `resources/js/pages/Auth/TwoFactor/Setup.tsx:42, 48`
  - Проблема: `setTimeout(() => setCopiedCode(false), 2000)` без cleanup
  - Решение: Обернуть в useEffect с clearTimeout в return

### Обработка ошибок

- [ ] **Silent fail в BuildLogs**
  - Файл: `resources/js/pages/Deployments/BuildLogs.tsx:57`
  - Проблема: `.catch(() => setIsLoading(false))` - ошибка игнорируется
  - Решение: Добавить toast с сообщением об ошибке

- [ ] **Weak catch в Tokens**
  - Файл: `resources/js/pages/Settings/Tokens.tsx:50`
  - Проблема: `response.json().catch(() => ({}))` - silent fail
  - Решение: Логировать и показывать пользователю ошибку

---

## 🟡 СРЕДНИЕ ПРОБЛЕМЫ

### TODO комментарии (незавершённая функциональность)

- [ ] **Boarding - создание сервера**
  - Файл: `resources/js/pages/Boarding/Index.tsx:95`
  - Проблема: `// TODO: Create server via API` - нужно проверить endpoint
  - Решение: Верифицировать работоспособность API интеграции

- [ ] **Projects Show - deployment UUID**
  - Файл: `resources/js/pages/Projects/Show.tsx:1306`
  - Проблема: `// TODO: Need deployment UUID for real API call`
  - Решение: Реализовать получение deployment UUID для API

### UX проблемы

- [ ] **confirm() вместо модальных окон (~30 мест)**
  - Файлы: SharedVariables/Show.tsx:51, Applications/Index.tsx:224 и др.
  - Проблема: Нативный `confirm()` не соответствует дизайну системы
  - Решение: Создать и использовать переиспользуемый `ConfirmationModal` компонент
  - Затронутые файлы (выборочно):
    - [ ] `resources/js/pages/SharedVariables/Show.tsx`
    - [ ] `resources/js/pages/Applications/Index.tsx`
    - [ ] `resources/js/pages/Services/Settings.tsx`
    - [ ] И другие (~27 файлов)

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
| Неработающие кнопки | 4 | 0 | 🟠 Высокая |
| Memory leaks | 3 | 0 | 🟠 Высокая |
| Обработка ошибок | 2 | 0 | 🟠 Высокая |
| TODO незавершённые | 2 | 0 | 🟡 Средняя |
| confirm() → Modal | ~30 | 0 | 🟡 Средняя |
| localStorage | 2 | 0 | 🟡 Средняя |
| TypeScript any | 60+ | 0 | 🟢 Низкая |

**Прогресс: 5 из ~25 критических/высоких проблем исправлено**

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

---

## План исправления

### Этап 1: Критические (до продакшна)
1. ~~Исправить XSS в TwoFactor/Setup.tsx~~ ✅
2. ~~Удалить mock данные из Notifications/Index.tsx~~ ✅
3. ~~Удалить mock данные из Webhooks~~ ✅ (Integrations/Webhooks.tsx и Services/Webhooks.tsx)
4. ~~Удалить mock данные из Settings/Account.tsx~~ ✅

### Этап 2: Высокие (неделя 1)
4. Реализовать API для Services/Settings.tsx
5. Реализовать API для Notifications/Preferences.tsx
6. Реализовать API для Services/Scaling.tsx
7. Реализовать API для Databases/Overview.tsx restart
8. Исправить memory leaks в Terminal и ProjectCanvas

### Этап 3: Средние (неделя 2)
9. Создать ConfirmationModal компонент
10. Заменить все confirm() вызовы
11. Завершить TODO функциональность

### Этап 4: Низкие (ongoing)
12. Постепенно исправлять `as any` типизацию
