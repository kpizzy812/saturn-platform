# Saturn Platform - Проблемы Обнаруженные При Изучении

**Дата:** 2026-01-21
**Статус:** Требует немедленного внимания

---

## 🚨 Критичные Проблемы (P0)

### 1. Хаос в Корневой Директории
**Проблема:** В корне проекта 30+ MD файлов без организации
```
- API_CONTROLLERS_SUMMARY.md
- SATURN_AUDIT.md
- SATURN_FRONTEND_AUDIT_REPORT.md
- SATURN_FINAL_READINESS_REPORT.md
- SATURN_MISSING_PAGES.md
- SATURN_NEXT_STEPS.md
- SATURN_READINESS_ASSESSMENT.md
- WEBSOCKET_CONFIGURATION_SUMMARY.md
- WEBSOCKET_IMPLEMENTATION.md
- RAILWAY_MIGRATION_PLAN.md
- RAILWAY_REACT_DEPLOYMENT.md
- MIGRATION_PLAN.md
- REDESIGN_PLAN.md
- ... и еще 15+ файлов
```
**Влияние:** Невозможно быстро найти нужную документацию
**Решение:** Создать структуру docs/ и переместить файлы по категориям

### 2. Отсутствуют Log Streaming APIs
**Проблема:**
- `GET /api/v1/deployments/{uuid}/logs` - возвращает пустой массив (placeholder)
- `GET /api/v1/services/{uuid}/logs` - endpoint не существует
- `GET /api/v1/databases/{uuid}/logs` - endpoint не существует

**Код (routes/api.php):**
```php
// Существует, но не реализован:
Route::get('deployments/{uuid}/logs', [DeploymentController::class, 'logs']);
```

**Влияние:**
- Невозможно дебажить деплои
- Пользователи не видят что происходит во время деплоя
- LogsViewer компонент на фронтенде не работает

**Затронутые файлы:**
- Frontend: `resources/js/components/features/LogsViewer.tsx`
- Frontend: `resources/js/pages/Deployments/BuildLogs.tsx`
- Frontend: `resources/js/pages/Services/Logs.tsx`
- Frontend: `resources/js/hooks/useLogStream.ts`
- Backend: `app/Http/Controllers/Api/DeploymentController.php`

**Решение:** Реализовать streaming endpoints + WebSocket `LogEntry` event

---

### 3. ~30 Страниц с Mock Data
**Проблема:** Треть фронтенда использует моки вместо реальных API

**Settings Pages (11 страниц):**
```typescript
// Settings/Account.tsx
const handleSave = () => {
  setTimeout(() => {
    alert('Settings saved!'); // ❌ Mock
  }, 500);
}
```

**Activity Pages (5 страниц):**
```typescript
// Activity/Index.tsx
const MOCK_ACTIVITIES = [ // ❌ Hardcoded mock data
  { id: 1, type: 'deployment', ... },
  { id: 2, type: 'server', ... },
]
```

**Logs Pages (4 страницы):**
```typescript
// Services/Logs.tsx
const generateMockLogs = () => { // ❌ Fake logs generator
  return Array.from({ length: 100 }, (_, i) => ({
    timestamp: new Date(),
    message: `Mock log entry ${i}`
  }))
}
```

**Влияние:** Настройки не сохраняются, логи фейковые, activity не обновляется

**Затронутые категории:**
- Settings: Account, Workspace, Team, Security, Tokens, Billing (11 файлов)
- Activity: Index, Timeline, Notifications (5 файлов)
- Databases: Tables, Extensions, Query (3 файла)
- Templates, Observability, Volumes, CronJobs (15+ файлов)

---

### 4. Test Coverage < 5%
**Проблема:** Практически нет тестов

**Текущее состояние:**
```
tests/Frontend/
├── components/ui/__tests__/components.test.tsx (1 файл)
├── pages/Deployments/Index.test.tsx
├── pages/Deployments/Show.test.tsx
├── pages/Deployments/BuildLogs.test.tsx
└── pages/Activity/Timeline.test.tsx

Всего: 5 тест-файлов
```

**Что не покрыто тестами:**
- ❌ 0 тестов для API hooks (12 файлов, 31 функция)
- ❌ 0 тестов для form validation
- ❌ 0 тестов для WebSocket integration
- ❌ 0 тестов для критичных user paths
- ❌ Minimal coverage для UI компонентов

**Влияние:**
- Высокий риск регрессий при рефакторинге
- Невозможно уверенно деплоить
- Баги обнаруживаются пользователями, а не CI

---

### 5. Отсутствует Shared Variables Feature
**Проблема:** Нет API для управления переменными окружения на уровне Team/Project/Environment

**Что должно быть:**
```
Team Variables      → Переменные для всей команды
Project Variables   → Переменные для проекта
Environment Variables → Переменные для окружения (prod/staging)
```

**Что есть:**
- ✅ Application environment variables (per-application)
- ❌ Team variables
- ❌ Project variables
- ❌ Environment variables

**Livewire код существует:**
- `app/Livewire/SharedVariables/` (6 компонентов)
- Но API endpoints отсутствуют

**Влияние:**
- Невозможно переиспользовать env variables
- Приходится дублировать переменные в каждом приложении
- Нет centralized secrets management

---

### 6. Отсутствует Storage Management
**Проблема:** Нет API для управления S3/backup locations

**Что должно быть:**
```php
POST   /api/v1/storages              # Create S3 storage
GET    /api/v1/storages              # List storages
PATCH  /api/v1/storages/{uuid}       # Update storage
DELETE /api/v1/storages/{uuid}       # Delete storage
POST   /api/v1/storages/{uuid}/test  # Test connection
```

**Что есть:**
- ❌ API endpoints отсутствуют
- ✅ Livewire компоненты существуют (`app/Livewire/Storage/`)
- ✅ Модель существует (`app/Models/S3Storage.php`)

**Влияние:**
- Бэкапы баз данных не работают (некуда сохранять)
- Невозможно настроить remote storage
- Бэкапы хранятся только локально

**Затронутые файлы:**
- `app/Models/S3Storage.php` (модель есть)
- `app/Models/ScheduledDatabaseBackup.php` (использует S3Storage)
- Frontend: нет страниц для Storage management

---

## ⚠️ Важные Проблемы (P1)

### 7. Notification Channels - Моки
**Проблема:** UI для настройки Discord/Slack/Telegram есть, но API не работают

**Файлы:**
- `resources/js/pages/Settings/Notifications/Discord.tsx` - есть
- `resources/js/pages/Settings/Notifications/Slack.tsx` - есть
- `resources/js/pages/Settings/Notifications/Telegram.tsx` - есть

**Backend:**
- Jobs существуют: `SendMessageToDiscordJob`, `SendMessageToSlackJob`
- API endpoints для сохранения настроек - отсутствуют

**Влияние:** Кнопка "Test Notification" не работает

---

### 8. SSH Terminal - Частичная Реализация
**Проблема:** UI для терминала есть, но WebSocket endpoint не подключен

**Что есть:**
- ✅ Frontend: Terminal component с xterm.js
- ✅ Backend: `docker/coolify-realtime/terminal-server.js` (Soketi extension)
- ❌ Интеграция не завершена

**Файлы:**
- Frontend: `resources/js/terminal.js` (legacy JS код)
- Backend: `docker/coolify-realtime/terminal-server.js`
- Soketi config: не настроен terminal namespace

**Влияние:** Невозможно подключиться к серверу через терминал из UI

---

### 9. Templates System - Backend Отсутствует
**Проблема:** UI для templates галереи готов, но backend API нет

**Frontend (готово):**
- `resources/js/pages/Templates/Index.tsx` - галерея шаблонов
- `resources/js/pages/Templates/Show.tsx` - детали шаблона
- `resources/js/pages/Templates/Deploy.tsx` - деплой из шаблона
- `resources/js/pages/Templates/Submit.tsx` - отправка шаблона

**Backend (отсутствует):**
```php
// Нужно реализовать:
GET    /api/v1/templates              # List templates
GET    /api/v1/templates/{uuid}       # Show template
POST   /api/v1/templates/{uuid}/deploy # Deploy from template
POST   /api/v1/templates              # Submit template
```

**Влияние:** Templates feature не работает

---

### 10. Preview Deployments - Backend Отсутствует
**Проблема:** UI для PR deployments есть, backend API нет

**Что должно быть:**
```php
GET    /api/v1/applications/{uuid}/previews      # List PR deployments
POST   /api/v1/applications/{uuid}/previews      # Create preview
DELETE /api/v1/applications/{uuid}/previews/{id} # Delete preview
```

**Что есть:**
- ✅ Модель: `ApplicationPreview.php`
- ✅ Job: `ApplicationPullRequestUpdateJob.php`
- ❌ API endpoints отсутствуют

**Влияние:** Невозможно создавать preview deployments для PRs

---

## 🔧 Технические Проблемы (P2)

### 11. Bundle Size > 500KB
**Проблема:** Один chunk 501KB (warning при билде)

```
(!) Some chunks are larger than 500 kB after minification.
public/build/assets/index-H-oQyu9b.js (501.89 kB / 135.14 kB gzipped)
```

**Решение:**
- Code splitting для тяжелых страниц
- Lazy loading для Chart, SqlEditor, Canvas компонентов
- Dynamic imports для database panels

---

### 12. Типизация - 14 'any' Types
**Проблема:** В коде есть нетипизированные места

**Примеры:**
```typescript
// Event handlers
const handleSubmit = (e: any) => { ... } // ❌

// WebSocket events
Echo.listen('event', (event: any) => { ... }) // ❌

// Error handling
catch (err: any) { ... } // ❌
```

**Решение:** Создать типы в `types/` и заменить `any`

---

### 13. Alert() Calls - 14 Locations
**Проблема:** Используется browser alert() вместо Toast компонента

**Файлы:**
- Services/Settings.tsx:17 - `alert('Settings saved!')`
- Services/Show.tsx:46,57 - `alert('Failed to...')`
- Databases/Show.tsx:33 - `alert('Failed to restart')`
- И еще 10+ мест

**Решение:** Заменить на Toast component

---

### 14. Пустые onClick Handlers - 5 Locations
**Проблема:** Кнопки без обработчиков

**Примеры:**
```typescript
// Projects/Index.tsx:71
<Button onClick={() => {/* TODO */}}>More</Button>

// Servers/Show.tsx:68
<Button>Validate</Button> // No onClick at all

// Dashboard.tsx:84
<DropdownItem onClick={(e) => e.preventDefault()}>Edit</DropdownItem>
```

**Решение:** Добавить обработчики или удалить кнопки

---

### 15. Placeholder Text - 4 Locations
**Проблема:** Placeholder текст вместо реальных данных

```typescript
// Servers/Show.tsx:197
<div>Server logs will appear here...</div>

// Databases/Show.tsx:258
<div>Historical metrics and charts...</div>
```

**Решение:** Подключить реальные данные или убрать placeholder

---

## 📁 Проблемы Организации (P3)

### 16. Беспорядок в Корне Проекта
**Проблема:** 30+ MD файлов в корне без структуры

**Список файлов:**
```
CHANGELOG.md ✅ (нужен в корне)
CODE_OF_CONDUCT.md ✅ (нужен в корне)
CONTRIBUTING.md ✅ (нужен в корне)
LICENSE ✅ (нужен в корне)
README.md ✅ (нужен в корне)
SECURITY.md ✅ (нужен в корне)
RELEASE.md ✅ (нужен в корне)

API_CONTROLLERS_SUMMARY.md ❌ (переместить в docs/api/)
CLAUDE.md ❌ (переместить в docs/development/)
MIGRATION_PLAN.md ❌ (переместить в docs/migration/)
RAILWAY_MIGRATION_PLAN.md ❌ (переместить в docs/migration/)
RAILWAY_REACT_DEPLOYMENT.md ❌ (переместить в docs/migration/)
REDESIGN_PLAN.md ❌ (переместить в docs/planning/)
SATURN_AUDIT.md ❌ (переместить в docs/audits/)
SATURN_FINAL_READINESS_REPORT.md ❌ (переместить в docs/audits/)
SATURN_FRONTEND_AUDIT_REPORT.md ❌ (переместить в docs/audits/)
SATURN_MISSING_PAGES.md ❌ (переместить в docs/audits/)
SATURN_NEXT_STEPS.md ❌ (переместить в docs/planning/)
SATURN_READINESS_ASSESSMENT.md ❌ (переместить в docs/audits/)
TECH_STACK.md ❌ (переместить в docs/)
WEBSOCKET_CONFIGURATION_SUMMARY.md ❌ (переместить в docs/api/)
WEBSOCKET_IMPLEMENTATION.md ❌ (переместить в docs/api/)

PROJECT_ARCHITECTURE.md ✅ (наш файл - оставить)
REFACTORING_TODO.md ✅ (наш файл - оставить)
IMMEDIATE_ISSUES.md ✅ (этот файл - оставить)
```

**Предлагаемая структура:**
```
docs/
├── api/
│   ├── API_CONTROLLERS_SUMMARY.md
│   ├── WEBSOCKET_CONFIGURATION_SUMMARY.md
│   └── WEBSOCKET_IMPLEMENTATION.md
├── audits/
│   ├── SATURN_AUDIT.md
│   ├── SATURN_FRONTEND_AUDIT_REPORT.md
│   ├── SATURN_FINAL_READINESS_REPORT.md
│   ├── SATURN_MISSING_PAGES.md
│   └── SATURN_READINESS_ASSESSMENT.md
├── migration/
│   ├── MIGRATION_PLAN.md
│   ├── RAILWAY_MIGRATION_PLAN.md
│   └── RAILWAY_REACT_DEPLOYMENT.md
├── planning/
│   ├── REDESIGN_PLAN.md
│   └── SATURN_NEXT_STEPS.md
├── development/
│   └── CLAUDE.md
└── TECH_STACK.md

Корень (оставить):
├── PROJECT_ARCHITECTURE.md (наш)
├── REFACTORING_TODO.md (наш)
├── IMMEDIATE_ISSUES.md (наш)
├── README.md (основной)
├── CHANGELOG.md
├── CONTRIBUTING.md
├── CODE_OF_CONDUCT.md
├── SECURITY.md
├── RELEASE.md
└── LICENSE
```

---

### 17. Git Not Initialized
**Проблема:** Проект не является git репозиторием

```bash
$ git status
fatal: not a git repository
```

**Влияние:**
- Нет version control
- Нет истории изменений
- Невозможно создавать branches для features
- Невозможно использовать git hooks

**Решение:** Инициализировать git репозиторий

---

### 18. .env Файл Отсутствует
**Проблема:** Нет .env файла для локальной разработки

**Есть примеры:**
- `.env.development.example`
- `.env.production`
- `.env.railway.example`
- `.env.windows-docker-desktop.example`

**Нужно:** Создать .env из примера

---

## 🔍 Наблюдения

### Позитивные моменты:
1. ✅ Хорошо структурированный Laravel код
2. ✅ Comprehensive API endpoints (89 endpoints)
3. ✅ TypeScript на фронтенде
4. ✅ Docker-based development
5. ✅ Comprehensive models and relationships
6. ✅ Background jobs хорошо организованы
7. ✅ WebSocket infrastructure готов (Soketi)

### Требует внимания:
1. ❌ Много недоделанных features (моки)
2. ❌ Практически нет тестов
3. ❌ Документация разбросана
4. ❌ Некоторые критичные API отсутствуют
5. ❌ Frontend-Backend связь не полная

---

## 📊 Приоритеты Исправления

### Немедленно (сегодня):
1. ✅ Организовать docs/ структуру
2. ✅ Инициализировать git
3. ✅ Создать .env файл
4. ✅ Проверить работу docker-compose.dev.yml

### Эта неделя (P0):
5. ⬜ Реализовать Log Streaming APIs
6. ⬜ Убрать моки из Settings страниц
7. ⬜ Написать тесты для API hooks (target: 30%)

### Следующая неделя (P1):
8. ⬜ Реализовать Shared Variables
9. ⬜ Реализовать Storage Management
10. ⬜ Реализовать Notification Channels
11. ⬜ Увеличить test coverage до 60%

### Через 2 недели (P2):
12. ⬜ Templates System
13. ⬜ Preview Deployments
14. ⬜ SSH Terminal
15. ⬜ Code optimization

---

**Последнее обновление:** 2026-01-21
**Следующий шаг:** Организация docs/ структуры
