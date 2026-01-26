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

- [x] **Settings/Team/Activity.tsx** - 9 записей активности ✅
- [x] **Settings/AuditLog.tsx** - использует реальный API /api/v1/teams/current/activities ✅

---

## 📋 ДЕТАЛЬНАЯ СПЕЦИФИКАЦИЯ МОКОВ

### 1. Settings/Workspace.tsx
**Файл:** `resources/js/pages/Settings/Workspace.tsx`

**Захардкоженные данные:**
```typescript
// Строки 15-20: Mock workspace data
const mockWorkspace: WorkspaceData = {
    name: 'My Workspace',
    slug: 'my-workspace',
    defaultEnvironment: 'production',
    timezone: 'UTC',
};

// Строки 22-34: Статический список таймзон
const timezones = ['UTC', 'America/New_York', ...];

// Строки 36-40: Статические environments
const environments = [
    { value: 'production', label: 'Production' },
    { value: 'staging', label: 'Staging' },
    { value: 'development', label: 'Development' },
];
```

**Что нужно:**
1. Передавать `workspace` данные из backend через Inertia props (Team модель)
2. Получить timezones через API: `DateTimeZone::listIdentifiers()` в PHP
3. Получить environments из базы: `/api/v1/teams/current/environments`

---

### 2. Settings/Integrations.tsx
**Файл:** `resources/js/pages/Settings/Integrations.tsx`

**Захардкоженные данные:**
```typescript
// Строки 19-56: Полностью моковые интеграции
const mockIntegrations: Integration[] = [
    { id: 'github', name: 'GitHub', connected: true, config: { account: 'your-username', ... } },
    { id: 'gitlab', name: 'GitLab', connected: false },
    { id: 'slack', name: 'Slack', connected: true, config: { account: '#deployments', ... } },
    { id: 'discord', name: 'Discord', connected: false },
];

// Строки 82-108: Connect использует setTimeout вместо API
const handleConnect = (e: React.FormEvent) => {
    setTimeout(() => { ... }, 1000); // ← ЗАГЛУШКА!
};

// Строки 110-125: Disconnect только меняет локальный state
const handleDisconnect = () => { ... }; // ← НЕТ API!
```

**Что нужно:**
1. **Backend модель** `TeamIntegration` для хранения подключений
2. **API endpoints:**
   - `GET /api/v1/teams/current/integrations` - список интеграций
   - `POST /api/v1/teams/current/integrations/{type}/connect` - подключение
   - `DELETE /api/v1/teams/current/integrations/{type}` - отключение
3. **OAuth flow** для GitHub/GitLab (или Personal Access Token)
4. **Webhook URL storage** для Slack/Discord

---

### 3. Database Panels (5 файлов)
**Файлы:** `resources/js/components/features/databases/*.tsx`

#### PostgreSQLPanel.tsx
```typescript
// Строки 176-183: Захардкоженные расширения
const [extensions] = useState([
    { name: 'pg_stat_statements', version: '1.10', enabled: true, ... },
    { name: 'pgcrypto', version: '1.3', enabled: true, ... },
    // ...
]);

// Строки 234-238: Захардкоженные пользователи
const [users] = useState([
    { name: 'postgres', role: 'Superuser', connections: 5 },
    { name: 'app_user', role: 'Standard', connections: 12 },
]);

// Строки 363-368: Захардкоженные логи
const [logs] = useState([...]);
```

#### ClickHousePanel.tsx
```typescript
// Строки 187-209: Захардкоженный лог запросов
const [queries] = useState([
    { query: 'SELECT count() FROM events...', duration: '0.234s', ... },
]);

// Строки 246-252: Захардкоженная репликация
const [replication] = useState({
    enabled: true,
    replicas: [{ host: 'ch-replica1.example.com', status: 'Healthy', ... }],
});

// Строки 166-179: Захардкоженный Merge Status
// Active Merges: 3, Parts Count: 142, Merge Rate: 12/min

// Строки 332-337: Захардкоженные логи
const [logs] = useState([...]);
```

#### MySQLPanel.tsx, MongoDBPanel.tsx, RedisPanel.tsx
Аналогичные моки: users, databases, logs, settings

**Что нужно:**
1. **Backend API для выполнения SQL через SSH:**
   - PostgreSQL: `SELECT * FROM pg_extension`, `SELECT * FROM pg_user`, logs из pg_log
   - ClickHouse: `SELECT * FROM system.query_log`, `SELECT * FROM system.replicas`
   - MySQL: `SHOW DATABASES`, `SELECT * FROM mysql.user`
   - MongoDB: `db.runCommand({listDatabases: 1})`, `db.getUsers()`
   - Redis: `INFO`, `CLIENT LIST`

2. **API endpoints:**
   - `GET /databases/{uuid}/extensions` - PostgreSQL extensions
   - `GET /databases/{uuid}/users` - database users
   - `GET /databases/{uuid}/logs` - последние логи
   - `GET /databases/{uuid}/queries` - ClickHouse query log
   - `GET /databases/{uuid}/replication` - ClickHouse replication status

3. **SSH execution** через существующий механизм remote commands

---

### ✅ Notifications Settings - НЕ МОКИ!
**Файлы:** `resources/js/pages/Settings/Notifications/*.tsx`

> ⚠️ Эти файлы используют РЕАЛЬНЫЙ API через Inertia!

```typescript
// Email.tsx, Telegram.tsx, Discord.tsx и др.
const { data, setData, post, processing, errors, isDirty } = useForm(settings);

// Данные приходят из backend как props:
export default function EmailNotifications({ settings, lastTestAt, lastTestStatus }: Props) { ... }

// Save отправляет данные на backend:
post('/settings/notifications/email', { ... });
```

`eventOptions` - это UI mapping для checkbox'ов, а не моковые данные.
Настройки хранятся в модели `Team` (notification_settings)

### Прочие критические
- [ ] **API tokens видимы в React DevTools** - Settings/Tokens.tsx
- [ ] **Broken links** - /terms, /privacy, /support не существуют
- [x] **~~Неправильные API пути~~** - useDatabaseMetrics.ts - НЕАКТУАЛЬНО: пути корректны, используют web routes с сессионной аутентификацией ✅

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

### Заглушки с console.log вместо реального функционала (требуют реализации API)
> ⚠️ Это не просто "логи в консоль" - это кнопки/функции которые ничего не делают!

- [x] **Settings/AuditLog.tsx** - `console.log('Exporting audit logs...')` → реализован реальный API экспорт в CSV/JSON ✅
- [ ] **Observability/Metrics.tsx:158,163** - Export/Refresh metrics → нужен реальный экспорт метрик
- [x] **Observability/Logs.tsx** - полностью переписан: реальный API для логов ресурсов, download/copy функционал ✅
- [x] **ScheduledTasks/History.tsx** - Export history → реализован клиентский экспорт в CSV/JSON ✅
- [ ] **Services/Rollbacks.tsx:130** - Rollback confirmation → нужен реальный API rollback
- [x] **Services/Networking.tsx** - Save network config → добавлен toast UI feedback ✅
- [x] **Environments/Secrets.tsx** - Secret viewed logging → удалён debug log ✅
- [x] **Deployments/Show.tsx:154** - debug log удалён ✅

### console.error в catch блоках (нужен proper error handling)
- [x] **Projects/Show.tsx** (6 мест) - заменить на toast notifications ✅
- [ ] **Applications/Rollback/*.tsx** (4 места) - заменить на toast notifications
- [x] **Settings/Account.tsx** (4 места) - заменить на toast notifications ✅
- [ ] **Settings/Workspace.tsx** (2 места) - заменить на toast notifications
- [ ] **Settings/Team/Index.tsx** (2 места) - заменить на toast notifications
- [ ] **Settings/Tokens.tsx, Applications/Index.tsx, Boarding/Index.tsx, Services/Scaling.tsx, Onboarding/ConnectRepo.tsx** - заменить на toast notifications

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
- [ ] **`Record<string, any>`** - hooks/useDatabases.ts, Observability/Logs.tsx, Admin/Logs/Index.tsx, Databases/Query.tsx

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
| Критические (mock данные) | 7 | 2 | 5 |
| Высокие (кнопки без API) | 8 | 4 | 4 |
| Высокие (memory leaks) | 5 | 1 | 4 |
| Высокие (console.log) | 8 | 5 | 3 |
| Высокие (формы/a11y/routing) | ~25 | 1 | ~24 |
| Средние (TypeScript) | ~20 | 15 | ~5 |
| Средние (дублирование) | 6 | 1 | 5 |

**Прогресс: ~32 исправлено ✅ | ~49 осталось**

---

## План исправления

### Приоритет 1: Безопасность
1. [ ] Скрыть API токены от React DevTools
2. [ ] Добавить маскировку паролей по умолчанию
3. [ ] Создать страницы /terms, /privacy, /support или удалить ссылки

### Приоритет 2: Mock данные в Settings (см. детальную спецификацию выше)
4. [x] **Settings/Workspace.tsx** - получать workspace данные из Team модели через Inertia props ✅
5. [x] Settings/Team/Activity - API для истории активности ✅
6. [ ] **Settings/Integrations.tsx** - создать модель TeamIntegration + OAuth/API token flow
7. [x] Settings/AuditLog - реальный API ✅

### Приоритет 3: Database Panels (см. детальную спецификацию выше)
8. [ ] **PostgreSQLPanel** - API для extensions, users, logs через SSH
9. [ ] **ClickHousePanel** - API для queries, replication, merge status через SSH
10. [ ] **MySQLPanel, MongoDBPanel, RedisPanel** - аналогичные API

### Приоритет 4: Кнопки без API
11. [ ] Templates/Submit - API для шаблонов
12. [ ] Databases/Query - API для SQL через SSH
13. [ ] Errors/Maintenance - API для подписки

### Приоритет 5: Качество кода
14. [ ] Observability/Metrics.tsx - реальный экспорт метрик
15. [ ] Services/Rollbacks.tsx - реальный API rollback
16. [ ] Исправить memory leaks в terminal.js
17. [ ] Добавить SSR проверки
18. [ ] Рефакторинг дублирования (5 компонентов)
