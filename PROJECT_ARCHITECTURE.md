# Saturn Platform - Архитектура Проекта

**Дата:** 2026-01-21
**Версия:** v4.x (форк Coolify)

---

## 📋 Общее Описание

**Saturn Platform** - это **open-source альтернатива Heroku/Netlify/Vercel** для самостоятельного хостинга (self-hosted). Платформа позволяет управлять серверами, приложениями и базами данных на собственном железе через SSH подключение.

### Ключевые Особенности
- ✅ Self-hosted (без vendor lock-in)
- ✅ Docker-first архитектура
- ✅ Git-based деплой (GitHub, GitLab, Bitbucket, Gitea)
- ✅ Поддержка множества баз данных
- ✅ Zero-downtime deployments
- ✅ Real-time мониторинг и логи
- ✅ Multi-server orchestration

---

## 🏗️ Архитектура Приложения

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                          │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  React 18 + TypeScript + Inertia.js                         │
│  • 137 страниц                                               │
│  • 40+ компонентов                                           │
│  • 12+ custom hooks                                          │
│  • Tailwind CSS + Headless UI                                │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────┐
│                      API LAYER (REST)                        │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Laravel Sanctum API (v1)                                    │
│  • 89+ endpoints                                             │
│  • Token-based auth                                          │
│  • Rate limiting                                             │
│  • Abilities: read, write, deploy, root, read:sensitive     │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER                          │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Laravel 12 (PHP 8.4)                                        │
│  • 60+ Eloquent Models                                       │
│  • 49+ Background Jobs (Laravel Queue)                       │
│  • 20+ Broadcast Events (WebSocket)                          │
│  • 187 Livewire Components (legacy, будет удален)            │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────┐
│                    INFRASTRUCTURE LAYER                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  • PostgreSQL 15 - Primary Database                          │
│  • Redis 7 - Cache & Queue                                   │
│  • Soketi - WebSocket Server                                 │
│  • Docker - Containerization                                 │
│  • Traefik/Caddy - Reverse Proxy                             │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────┐
│                      TARGET SERVERS                          │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  • VPS, Bare Metal, Raspberry Pi                             │
│  • SSH Connection                                             │
│  • Docker Engine                                              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Структура Директорий

### Backend (Laravel)

```
app/
├── Actions/               # Business logic actions (13 классов)
│   ├── Application/       # Деплой приложений
│   ├── Database/          # Управление БД
│   ├── Server/            # Управление серверами
│   └── Service/           # Управление сервисами
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/          # REST API контроллеры (14 контроллеров)
│   │   └── Webhook/      # Webhook обработчики
│   └── Middleware/       # Custom middleware
│
├── Models/               # Eloquent модели (60+ моделей)
│   ├── Application.php   # Приложения
│   ├── Server.php        # Сервера
│   ├── Service.php       # Сервисы (Docker Compose)
│   ├── StandalonePostgresql.php  # База PostgreSQL
│   ├── Project.php       # Проекты
│   └── Team.php          # Команды (multi-tenant)
│
├── Jobs/                 # Background jobs (49 джобов)
│   ├── ApplicationDeploymentJob.php  # Деплой
│   ├── DatabaseBackupJob.php         # Бэкапы
│   ├── ServerCheckJob.php            # Мониторинг
│   └── ...
│
├── Events/               # Broadcast события (20+ событий)
│   ├── ApplicationStatusChanged.php
│   ├── DatabaseStatusChanged.php
│   └── ...
│
├── Policies/             # Authorization policies
├── Notifications/        # Email/Discord/Slack уведомления
├── Services/             # Domain services
└── Traits/               # Reusable traits
```

### Frontend (React + TypeScript)

```
resources/js/
├── pages/                # React страницы (137 страниц)
│   ├── Auth/            # Аутентификация (10 страниц)
│   ├── Projects/        # Проекты (6 страниц)
│   ├── Servers/         # Сервера (8 страниц)
│   ├── Services/        # Сервисы (15 страниц)
│   ├── Databases/       # Базы данных (14 страниц)
│   ├── Deployments/     # Деплои (6 страниц)
│   ├── Settings/        # Настройки (21 страница)
│   └── ...
│
├── components/          # React компоненты (40+ компонентов)
│   ├── ui/             # UI компоненты (Button, Input, Card, etc.)
│   ├── features/       # Feature компоненты (LogsViewer, Canvas, etc.)
│   └── layouts/        # Layouts (AppLayout, AuthLayout)
│
├── hooks/               # Custom React hooks (12 хуков)
│   ├── useApplications.ts    # Управление приложениями
│   ├── useDeployments.ts     # Управление деплоями
│   ├── useDatabases.ts       # Управление БД
│   ├── useServers.ts         # Управление серверами
│   ├── useLogStream.ts       # Real-time логи
│   └── useRealtimeStatus.ts  # WebSocket статусы
│
├── types/               # TypeScript типы
│   ├── models.ts       # Domain models types
│   └── index.ts        # Export all types
│
├── lib/                 # Утилиты
│   ├── echo.ts         # Laravel Echo config
│   └── utils.ts        # Helper functions
│
└── app.tsx             # Main entry point
```

### Routes (Маршруты)

```
routes/
├── web.php          # Web routes (1154 строки)
│                    # • Livewire routes (legacy)
│                    # • Inertia routes (/new/* prefix)
│                    # • Auth routes
│
├── api.php          # API routes (219 строк)
│                    # • /api/v1/* endpoints (89 маршрутов)
│                    # • Sanctum auth
│
├── channels.php     # Broadcasting channels
│                    # • team.{teamId}
│                    # • user.{userId}
│
└── webhooks.php     # Webhook routes
                     # • GitHub, GitLab, Bitbucket
```

---

## 🔌 API Endpoints (v1)

### Основные категории API:

#### 1. Teams (6 endpoints)
```
GET    /api/v1/teams                    # Список команд
GET    /api/v1/teams/current            # Текущая команда
GET    /api/v1/teams/{id}/members       # Участники команды
```

#### 2. Projects (12 endpoints)
```
GET    /api/v1/projects                 # Список проектов
POST   /api/v1/projects                 # Создать проект
GET    /api/v1/projects/{uuid}          # Детали проекта
PATCH  /api/v1/projects/{uuid}          # Обновить проект
DELETE /api/v1/projects/{uuid}          # Удалить проект
```

#### 3. Servers (13 endpoints)
```
GET    /api/v1/servers                  # Список серверов
POST   /api/v1/servers                  # Добавить сервер
GET    /api/v1/servers/{uuid}           # Детали сервера
GET    /api/v1/servers/{uuid}/resources # Ресурсы на сервере
```

#### 4. Applications (17 endpoints)
```
GET    /api/v1/applications             # Список приложений
POST   /api/v1/applications/public      # Создать (public repo)
POST   /api/v1/applications/dockerfile  # Создать (Dockerfile)
GET|POST /api/v1/applications/{uuid}/start   # Запустить
GET|POST /api/v1/applications/{uuid}/stop    # Остановить
GET|POST /api/v1/applications/{uuid}/restart # Перезапустить
```

#### 5. Databases (16 endpoints)
```
GET    /api/v1/databases                # Список БД
POST   /api/v1/databases/postgresql     # Создать PostgreSQL
POST   /api/v1/databases/mysql          # Создать MySQL
POST   /api/v1/databases/mongodb        # Создать MongoDB
POST   /api/v1/databases/redis          # Создать Redis
GET    /api/v1/databases/{uuid}/backups # Список бэкапов
```

#### 6. Deployments (7 endpoints)
```
GET    /api/v1/deployments              # Список деплоев
POST   /api/v1/deploy                   # Запустить деплой
GET    /api/v1/deployments/{uuid}       # Детали деплоя
⚠️ GET /api/v1/deployments/{uuid}/logs  # MISSING - Логи деплоя
```

---

## 🔄 Background Jobs (49 джобов)

### Категории джобов:

#### Deployment Jobs
- `ApplicationDeploymentJob` - Основной деплой (git clone, build, push)
- `ApplicationPullRequestUpdateJob` - PR deployments
- `StopApplicationJob` - Остановка приложения
- `DeleteResourceJob` - Удаление с артефактами

#### Infrastructure Jobs
- `ServerCheckJob` - Проверка статуса сервера/контейнеров
- `ServerConnectionCheckJob` - Проверка SSH подключения
- `ServerStorageCheckJob` - Проверка дискового пространства
- `RestartProxyJob` - Перезапуск Traefik/Caddy
- `ValidateAndInstallServerJob` - Валидация и установка Docker

#### Database Jobs
- `DatabaseBackupJob` - Создание бэкапов
- `DatabaseRestoreJob` - Восстановление из бэкапа
- `ScheduledDatabaseBackupJob` - Автоматические бэкапы

#### Notification Jobs
- `SendMessageToSlackJob`
- `SendMessageToDiscordJob`
- `SendMessageToTelegramJob`
- `SendWebhookJob`

#### Cleanup Jobs
- `DockerCleanupJob` - Очистка неиспользуемых Docker ресурсов
- `CleanupHelperContainersJob` - Удаление helper контейнеров
- `CleanupOrphanedPreviewContainersJob` - Удаление orphaned previews

---

## 📡 WebSocket Events (20 событий)

### Team Channel Events (`team.{teamId}`)
```javascript
// Real-time статусы
ApplicationStatusChanged        // Приложение запущено/остановлено
ServiceStatusChanged            // Сервис изменил статус
DatabaseStatusChanged           // БД изменила статус
ServerReachabilityChanged       // Сервер online/offline

// Deployment события
DeploymentCreated              // Деплой начат
DeploymentFinished             // Деплой завершен

// Infrastructure события
ProxyStatusChangedUI           // Статус прокси для UI
ServerValidated                // Сервер валидирован
SentinelRestarted              // Sentinel перезапущен
BackupCreated                  // Бэкап создан
```

### User Channel Events (`user.{userId}`)
```javascript
DatabaseStatusChanged          // Персональные уведомления о БД
```

---

## 🗄️ База Данных (Eloquent Models)

### Core Models (60+ моделей)

#### Resources
- `Application` (74KB) - Приложения для деплоя
- `Service` (58KB) - Docker Compose сервисы
- `Server` (46KB) - Target сервера
- `Project` - Проекты (группировка ресурсов)
- `Environment` - Окружения (production, staging, etc.)

#### Databases (8 типов)
- `StandalonePostgresql`
- `StandaloneMysql`
- `StandaloneMariadb`
- `StandaloneMongodb`
- `StandaloneRedis`
- `StandaloneClickhouse`
- `StandaloneDragonfly`
- `StandaloneKeydb`

#### Infrastructure
- `Team` - Команды (multi-tenant)
- `User` - Пользователи
- `PrivateKey` - SSH ключи
- `ServerSettings` - Настройки сервера
- `GithubApp` - GitHub интеграция
- `GitlabApp` - GitLab интеграция

#### Deployments
- `ApplicationDeploymentQueue` - Очередь деплоев
- `ApplicationPreview` - Preview deployments (PR)
- `ScheduledDatabaseBackup` - Автоматические бэкапы

---

## 🐳 Docker Architecture

### Development Stack (docker-compose.dev.yml)

```yaml
services:
  saturn:              # Laravel приложение
  postgres:            # PostgreSQL 15 (база данных)
  redis:               # Redis 7 (кэш и очереди)
  soketi:              # WebSocket сервер
  vite:                # Frontend dev server (Hot reload)
  testing-host:        # Тестовый сервер для деплоев
  mailpit:             # Email тестирование
  minio:               # S3-compatible storage
```

### Production Stack (docker-compose.prod.yml)
- Без `vite` (билд фронтенда включен в образ)
- Без `testing-host` (реальные сервера)
- Без `mailpit` (реальный SMTP)

---

## 🔐 Authentication & Authorization

### Authentication Stack
- **Laravel Sanctum** - API token auth
- **Laravel Fortify** - Web auth (login, register, 2FA)
- **Laravel Socialite** - OAuth (GitHub, Google, Discord, etc.)

### Authorization
- **Laravel Policies** - Policy-based authorization
- **Team-based access** - Multi-tenant isolation
- **API Abilities** - Token permissions:
  - `read` - Чтение данных
  - `write` - Создание/обновление
  - `deploy` - Деплой приложений
  - `root` - Полный доступ
  - `read:sensitive` - Чтение секретов (env variables)

---

## 🚀 Deployment Flow

### 1. Git-based Deployment

```
┌──────────────┐
│  User Click  │
│   "Deploy"   │
└──────┬───────┘
       │
       ▼
┌─────────────────────────┐
│  ApplicationDeployment  │
│  Job dispatched         │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  SSH to Target Server   │
│  • Clone/Pull Git repo  │
│  • Build Docker image   │
│  • Push to registry     │
│  • Update container     │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  Broadcast Events       │
│  • DeploymentCreated    │
│  • LogEntry (stream)    │
│  • DeploymentFinished   │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  Frontend Updates       │
│  • Real-time status     │
│  • Live logs stream     │
│  • Success/Error state  │
└─────────────────────────┘
```

### 2. Dockerfile Deployment
- Build локально или на сервере
- Push в registry
- Pull и запуск на target сервере

### 3. Docker Compose Deployment
- Парсинг docker-compose.yml
- Генерация конфигурации
- Запуск multi-container стека

---

## 🔧 Что За Что Отвечает

### Backend Компоненты

#### **Actions/** - Business Logic
Содержит бизнес-логику в виде action классов:
- `Application/DeployApplication.php` - Деплой приложения
- `Database/StartDatabase.php` - Запуск БД
- `Server/ValidateServer.php` - Валидация сервера

#### **Jobs/** - Background Processing
Асинхронные задачи (Laravel Queue):
- Длительные операции (деплой, бэкап)
- Регулярные проверки (мониторинг)
- Отправка уведомлений

#### **Models/** - Domain Models
Eloquent модели для работы с БД:
- Связи (relationships)
- Scopes для фильтрации
- Accessors/Mutators
- Casts для типизации

#### **Events/** - Broadcasting
WebSocket события для real-time обновлений:
- Отправка через Soketi
- Подписка через Laravel Echo
- Автоматическое обновление UI

#### **Policies/** - Authorization
Policy классы для проверки прав доступа:
- `ApplicationPolicy.php` - Кто может управлять приложением
- `ServerPolicy.php` - Кто может управлять сервером
- Team-based isolation

#### **Notifications/** - Alerts
Уведомления через разные каналы:
- Email (Mailpit/SMTP)
- Discord webhooks
- Slack webhooks
- Telegram bot
- Custom webhooks

---

### Frontend Компоненты

#### **pages/** - React Pages
Страницы приложения (Inertia.js):
- Получают данные через props от Laravel
- Используют `router` для навигации
- Используют `useForm` для форм

#### **components/ui/** - UI Components
Базовые UI элементы:
- Button, Input, Select, Checkbox
- Modal, Dropdown, Tabs
- Toast, Spinner, Progress
- Styled с Tailwind CSS

#### **components/features/** - Feature Components
Сложные компоненты с бизнес-логикой:
- `LogsViewer` - Просмотр логов с фильтрацией
- `ProjectCanvas` - Canvas для визуализации проекта
- `DatabaseCard` - Карточка БД с действиями
- `CommandPalette` - Глобальный поиск (Cmd+K)

#### **hooks/** - Custom Hooks
React хуки для работы с API:
- `useApplications()` - CRUD приложений
- `useDeployments()` - Управление деплоями
- `useLogStream()` - Real-time логи
- `useRealtimeStatus()` - WebSocket статусы

#### **types/** - TypeScript Types
Типы для TypeScript:
- Domain models (Application, Server, etc.)
- API responses
- Component props
- Event payloads

---

## 🎯 Критичные Проблемы

### 🚨 P0 - Блокеры

1. **Отсутствуют Log Streaming APIs**
   - `GET /api/v1/deployments/{uuid}/logs` - возвращает пустой массив
   - `GET /api/v1/services/{uuid}/logs` - не существует
   - `GET /api/v1/databases/{uuid}/logs` - не существует
   - **Влияние:** Невозможно дебажить деплои

2. **~30 страниц с моками**
   - Settings страницы используют `setTimeout` вместо API
   - Activity/Logs используют `MOCK_DATA` массивы
   - **Влияние:** Настройки не работают

3. **Test Coverage < 5%**
   - Всего 5 тест-файлов
   - 0 тестов для API hooks
   - **Влияние:** Высокий риск регрессий

4. **Отсутствуют Shared Variables**
   - Team/Project/Environment variables
   - Критичная фича Saturn Platform
   - **Влияние:** Невозможно управлять env variables

5. **Отсутствует Storage Management**
   - S3/backup locations
   - Критично для бэкапов БД
   - **Влияние:** Бэкапы не работают

### ⚠️ P1 - Важно

6. **Notification Channels** - Моки вместо реальных API
7. **SSH Terminal** - Частичная реализация
8. **Templates System** - Backend отсутствует
9. **Preview Deployments** - UI есть, backend нет

---

## 📊 Метрики Проекта

### Backend
- **API Endpoints:** 89
- **Laravel Controllers:** 14
- **Background Jobs:** 49
- **Broadcast Events:** 20
- **Eloquent Models:** 60+
- **Livewire Components:** 187 (legacy, будет удален)

### Frontend
- **React Pages:** 137
- **React Components:** 40+
- **Custom Hooks:** 12
- **Test Files:** 5
- **Test Coverage:** < 5%

### Build Metrics
- **Build Time:** ~31 секунд
- **Bundle Size:** 4.2 MB (uncompressed)
- **Largest Chunk:** 501 KB (135 KB gzipped)
- **Build Warnings:** 1 (chunk size)

---

## 🔗 Полезные Ссылки

### Документация
- [README.md](README.md) - Основная документация
- [SATURN_AUDIT.md](SATURN_AUDIT.md) - Полный аудит проекта
- [SATURN_FRONTEND_AUDIT_REPORT.md](SATURN_FRONTEND_AUDIT_REPORT.md) - Аудит фронтенда
- [TECH_STACK.md](TECH_STACK.md) - Технологический стек
- [REFACTORING_TODO.md](REFACTORING_TODO.md) - Задачи рефакторинга

### Deployment
- [docker-compose.dev.yml](docker-compose.dev.yml) - Development окружение
- [docker-compose.prod.yml](docker-compose.prod.yml) - Production окружение
- [.env.production](.env.production) - Production переменные

### API
- [routes/api.php](routes/api.php) - API маршруты
- [openapi.json](openapi.json) - OpenAPI спецификация

---

## 🎓 Для Новых Разработчиков

### С чего начать?

1. **Изучить документацию:**
   - README.md - общее описание
   - SATURN_AUDIT.md - текущее состояние
   - PROJECT_ARCHITECTURE.md (этот файл)

2. **Настроить локальное окружение:**
   ```bash
   # Скопировать .env
   cp .env.development.example .env

   # Запустить Docker Compose
   docker-compose -f docker-compose.dev.yml up -d

   # Установить зависимости
   composer install
   npm install

   # Мигрировать БД
   php artisan migrate

   # Запустить frontend dev server
   npm run dev
   ```

3. **Изучить ключевые файлы:**
   - `app/Models/Application.php` - Модель приложения
   - `app/Jobs/ApplicationDeploymentJob.php` - Деплой
   - `resources/js/pages/Projects/Show.tsx` - Canvas проекта
   - `resources/js/hooks/useApplications.ts` - API hook

4. **Прочитать задачи:**
   - [REFACTORING_TODO.md](REFACTORING_TODO.md) - Что нужно сделать
   - [backlog/tasks/](backlog/tasks/) - Текущие задачи

---

**Последнее обновление:** 2026-01-21
