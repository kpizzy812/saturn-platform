# Аудит фронтенда Saturn: найденные проблемы

---

## 🔴 КРИТИЧЕСКИЕ ПРОБЛЕМЫ

### Безопасность
- [x] **XSS уязвимость в QR коде** ✅
- [x] **URL injection через window.open()** ✅
- [x] **JSON.parse без try-catch** ✅

### Mock данные (исправлено)
- [x] **Mock webhooks в Integrations** ✅
- [x] **Mock webhooks в Services** ✅
- [x] **Mock notifications** ✅
- [x] **Mock user в Settings** ✅
- [x] **Mock данные в Settings/Team/Index.tsx** ✅
- [x] **Mock данные в Settings/Members/Show.tsx** ✅
- [x] **Mock данные в Settings/Security.tsx** ✅

### Mock данные (требуют исправления)

- [ ] **Settings/Workspace.tsx** - захардкоженные workspace, timezones, environments
- [x] **Settings/Team/Activity.tsx** - 9 записей активности ✅
- [ ] **Settings/Integrations.tsx** - GitHub, GitLab, Slack, Discord интеграции
- [ ] **Settings/AuditLog.tsx** - 8 записей аудита

### Mock данные в Notifications Settings (6 файлов)
- [ ] **Event options захардкожены** в Email, Telegram, Discord, Slack, Pushover, Webhook

### Mock данные в Database Panels (5 файлов)
- [ ] **ClickHousePanel.tsx** - queries, replication, logs
- [ ] **PostgreSQLPanel.tsx** - extensions, users, logs
- [ ] **MySQLPanel.tsx, MongoDBPanel.tsx, RedisPanel.tsx** - аналогичные данные

### Прочие критические
- [ ] **API tokens видимы в React DevTools** - Settings/Tokens.tsx
- [ ] **Broken links** - /terms, /privacy, /support не существуют
- [ ] **Неправильные API пути** - useDatabaseMetrics.ts, useDatabaseMetricsHistory.ts используют `/api/databases/` вместо `/api/v1/databases/`

---

## 🟠 ВЫСОКИЕ ПРОБЛЕМЫ

### Кнопки без API (исправлено)
- [x] **Services Settings - Save/Delete** ✅
- [x] **Notifications Preferences - Save** ✅
- [x] **Services Scaling - Apply Changes** ✅
- [x] **Database Restart** ✅

### Кнопки без API (требуют исправления)
- [ ] **Templates/Submit.tsx** - Submit использует `setTimeout` вместо API
- [ ] **Settings/Integrations.tsx** - Connect не сохраняет токены
- [ ] **Databases/Query.tsx** - SQL запросы возвращают hardcoded результаты
- [ ] **Errors/Maintenance.tsx** - Subscribe не сохраняет email

### React баги / Memory leaks
- [x] **setTimeout без cleanup в TwoFactor/Setup.tsx** ✅
- [ ] **terminal.js** - setTimeout без cleanup, рекурсивный focusWhenReady, window.onresize без cleanup
- [ ] **useTerminal.ts** - потенциальный infinite loop в scheduleReconnect
- [ ] **useRealtimeStatus.ts** - рекурсивный reconnect без гарантии остановки
- [ ] **CommandPalette.tsx / Terminal.tsx** - SSR unsafe (нет проверки `typeof window`)

### console.log в продакшн коде (22 файла)
- [ ] Deployments/Show.tsx, Projects/Show.tsx, Settings/AuditLog.tsx, Settings/Tokens.tsx, Settings/Account.tsx, Settings/Workspace.tsx, Settings/Team/Index.tsx, ScheduledTasks/History.tsx, Observability/Metrics.tsx, Observability/Logs.tsx, Environments/Secrets.tsx, Services/Rollbacks.tsx, Services/Networking.tsx, Services/Scaling.tsx, Applications/DeploymentDetails.tsx, Applications/Index.tsx, Applications/Rollback/*, Boarding/Index.tsx, Onboarding/ConnectRepo.tsx

### Формы без proper обработки
- [ ] **Без disabled state при submit** - Servers/Create, Databases/Create, Settings/Team/Invite, CronJobs/Create, Auth/ForgotPassword
- [ ] **Без onError callback** - те же файлы
- [ ] **Без HTML5 валидации** - Auth/Register (password), Servers/Create (ip/port), Settings/Notifications/Email (smtp_port)

### Accessibility
- [ ] **Input без aria-invalid/aria-describedby**
- [ ] **Отсутствие aria-label** - Servers/Create, Settings/Team/Invite, Settings/Account, CronJobs/Create

### Роутинг
- [x] **javascript: protocol в href** ✅ (404.tsx, 403.tsx)
- [ ] **window.location.href вместо router.visit** - 9 мест
- [ ] **Legacy path /project/** в Rollback/Show.tsx

### Пароли/секреты
- [ ] **Пароли без маскировки** - SharedVariables/Show.tsx, Databases/Connections.tsx, Databases/Users.tsx

---

## 🟡 СРЕДНИЕ ПРОБЛЕМЫ

### UX (исправлено)
- [x] **confirm() → ConfirmationModal** ✅ (46 файлов)
- [x] **TODO в Boarding и Projects/Show** ✅

### TypeScript качество (исправлено)
- [x] **`as any` в основном коде** ✅ (15 файлов)

### TypeScript (требует исправления)
- [ ] **`: any` в параметрах** - lib/api.ts, hooks/useLogStream.ts, hooks/useTerminal.ts, ProjectCanvas.tsx, Servers/Proxy/Settings.tsx, Activity/Timeline.tsx
- [ ] **`: any` в интерфейсах** - Observability/Index.tsx, Observability/Metrics.tsx
- [ ] **`Record<string, any>`** - hooks/useDatabases.ts, Observability/Logs.tsx, Admin/Logs/Index.tsx, Settings/AuditLog.tsx, Databases/Query.tsx

### Дублирование кода (~3500 строк)
- [x] **Status utilities** ✅ (создана statusUtils.ts)
- [ ] **Logs pages** - 5 файлов (~1000 строк) → создать LogsViewer
- [ ] **Variables pages** - 6 файлов (~1200 строк) → создать VariablesManager
- [ ] **Domains/SSL pages** - 6 файлов (~600 строк) → создать DomainsList + sslUtils
- [ ] **Build Logs pages** - 2 файла (~400 строк) → создать BuildStepsViewer
- [ ] **Backups pages** - 4 файла (~500 строк) → создать BackupsManager

---

## 🟢 НИЗКИЕ / ПРОВЕРЕНО

- [x] **localStorage для theme/sidebar/sound** ✅ безопасно
- [x] **Memory leaks в ProjectCanvas/Terminal** ✅ не актуально (уже cleanup)
- [x] **Silent fail в BuildLogs/Tokens** ✅ не актуально

---

## Статистика

| Категория | Всего | Исправлено | Осталось |
|-----------|-------|------------|----------|
| Критические (безопасность) | 6 | 3 | 3 |
| Критические (mock данные) | 18 | 8 | 10 |
| Высокие (кнопки без API) | 8 | 4 | 4 |
| Высокие (memory leaks) | 5 | 1 | 4 |
| Высокие (console.log) | 22 | 0 | 22 |
| Высокие (формы/a11y/routing) | ~25 | 1 | ~24 |
| Средние (TypeScript) | ~20 | 15 | ~5 |
| Средние (дублирование) | 6 | 1 | 5 |

**Прогресс: ~33 исправлено ✅ | ~77 осталось**

---

## План исправления

### Приоритет 1: Безопасность
1. [ ] Скрыть API токены от React DevTools
2. [ ] Добавить маскировку паролей по умолчанию
3. [ ] Создать страницы /terms, /privacy, /support или удалить ссылки
4. [ ] Исправить API пути в useDatabaseMetrics*

### Приоритет 2: Mock данные в Settings
5. [ ] Settings/Workspace - API для workspace данных
6. [x] Settings/Team/Activity - API для истории активности ✅
7. [ ] Settings/Integrations - API для интеграций
8. [ ] Settings/AuditLog - использовать spatie/laravel-activitylog

### Приоритет 3: Database Panels
9. [ ] Реализовать API для получения реальных данных из БД че��ез SSH

### Приоритет 4: Кнопки без API
10. [ ] Templates/Submit - API для шаблонов
11. [ ] Databases/Query - API для SQL через SSH
12. [ ] Errors/Maintenance - API для подписки

### Приоритет 5: Качество кода
13. [ ] Удалить console.log (22 файла)
14. [ ] Исправить memory leaks в terminal.js
15. [ ] Добавить SSR проверки
16. [ ] Рефакторинг дублирования (5 компонентов)
