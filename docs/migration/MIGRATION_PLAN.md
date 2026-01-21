# Saturn Migration Plan: Старый фронт → Новый React фронт

## Статус: ✅ Аудит завершён

---

## Краткие результаты аудита

| Компонент | Количество | Статус |
|-----------|------------|--------|
| **API Endpoints** | 110+ | ✅ Готовы к использованию |
| **Livewire компоненты** | 160+ | 📋 Надо заменить |
| **React страницы** | 140 | ⚠️ 470+ моков |
| **WebSocket события** | 16 | ✅ Готовы |
| **Jobs (фоновые задачи)** | 40+ | ✅ Работают |

---

## Фаза 1: Результаты аудита

### 1.1 Бэкенд API (110+ endpoints)

#### Applications API
| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/v1/applications` | Список приложений |
| POST | `/api/v1/applications/public` | Создать публичное приложение |
| GET | `/api/v1/applications/{uuid}` | Получить приложение |
| PATCH | `/api/v1/applications/{uuid}` | Обновить приложение |
| DELETE | `/api/v1/applications/{uuid}` | Удалить приложение |
| GET/POST | `/api/v1/applications/{uuid}/start` | Запустить |
| GET/POST | `/api/v1/applications/{uuid}/stop` | Остановить |
| GET/POST | `/api/v1/applications/{uuid}/restart` | Перезапустить |
| GET | `/api/v1/applications/{uuid}/logs` | Логи |
| GET | `/api/v1/applications/{uuid}/envs` | Переменные окружения |

#### Deployments API
| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET/POST | `/api/v1/deploy` | Запустить деплой |
| GET | `/api/v1/deployments` | Список активных деплоев |
| GET | `/api/v1/deployments/{uuid}` | Статус деплоя |
| POST | `/api/v1/deployments/{uuid}/cancel` | Отменить деплой |

#### Databases API
| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/v1/databases` | Список БД |
| POST | `/api/v1/databases/{type}` | Создать БД (postgresql/mysql/redis/mongodb/etc) |
| GET/POST | `/api/v1/databases/{uuid}/start` | Запустить |
| GET/POST | `/api/v1/databases/{uuid}/stop` | Остановить |
| GET | `/api/v1/databases/{uuid}/backups` | Бэкапы |

#### Servers API
| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/v1/servers` | Список серверов |
| GET | `/api/v1/servers/{uuid}` | Детали сервера |
| GET | `/api/v1/servers/{uuid}/resources` | Ресурсы (CPU/RAM) |
| GET | `/api/v1/servers/{uuid}/validate` | Проверить подключение |

#### Projects API
| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/v1/projects` | Список проектов |
| POST | `/api/v1/projects` | Создать проект |
| GET | `/api/v1/projects/{uuid}/environments` | Окружения |

---

### 1.2 WebSocket события (16 событий)

| Событие | Канал | Назначение |
|---------|-------|------------|
| `ApplicationStatusChanged` | `team.{teamId}` | Статус приложения |
| `DatabaseStatusChanged` | `user.{userId}` | Статус БД |
| `ServiceStatusChanged` | `team.{teamId}` | Статус сервиса |
| `ServerReachabilityChanged` | direct | Доступность сервера |
| `ServerValidated` | `team.{teamId}` | Сервер проверен |
| `ProxyStatusChangedUI` | `team.{teamId}` | Статус Traefik |
| `BackupCreated` | `user.{userId}` | Бэкап создан |
| `ScheduledTaskDone` | `user.{userId}` | Задача выполнена |

---

### 1.3 Livewire компоненты (160+)

**Основные категории:**
- **Project/** - 8 компонентов (Index, Show, Edit, Create)
- **Application/** - 8+ компонентов (General, Source, Advanced, Deployment)
- **Database/** - 15+ компонентов (для каждого типа БД)
- **Server/** - 20+ компонентов (Show, Create, Proxy, Sentinel)
- **Service/** - 5+ компонентов (Index, Configuration)
- **Settings/** - 5+ компонентов
- **Shared/** - 15+ компонентов (Logs, Tags, HealthChecks, ResourceOperations)

**Ключевые паттерны:**
- `syncData()` - синхронизация данных с моделью
- `echo-private:team.{$teamId}` - WebSocket слушатели
- `dispatch('success')` - toast уведомления

---

### 1.4 React фронт - Что работает vs Мокнуто

#### ✅ РАБОТАЕТ (реальные данные)
| Страница | Источник данных |
|----------|-----------------|
| Auth (Login, Register, 2FA) | Inertia forms → backend |
| Projects/Index | Inertia props |
| Databases/Index | Inertia props |
| Databases/Create | router.post |
| Servers/Index | Inertia props |
| Domains/* | router.post/delete |

#### ❌ МОКНУТО (470+ моков)
| Страница | Что мокнуто |
|----------|-------------|
| Dashboard | Список проектов (hardcoded array) |
| Deployments/* | 20+ деплоев, логи |
| Activity/* | 7 активностей |
| Notifications/* | 7 уведомлений |
| Services/Show | Детали сервиса |
| Services/Logs | Симуляция setInterval |
| Databases/Query | SQL интерфейс |
| Observability/* | Метрики, логи, алерты |
| Settings/Account | setTimeout симуляция |
| Settings/APITokens | 3 токена |
| Templates/* | 10+ шаблонов |
| CronJobs/* | 4+ задачи |
| Volumes/* | 4 volume |

---

## Фаза 2: Что нужно подключить

### 2.1 Критично (P0)

#### Деплой и статусы
```
React: Deployments/Index.tsx, Show.tsx
API: GET /api/v1/deployments
WebSocket: ApplicationStatusChanged
Livewire: Application/Deployment/Index.php
```

#### Логи в реальном времени
```
React: Services/Logs.tsx (сейчас setInterval mock)
API: GET /api/v1/applications/{uuid}/logs
WebSocket: Нужен новый канал для стриминга
Livewire: Project/Shared/Logs.php
```

#### Start/Stop/Restart контейнеров
```
React: Кнопки в Services/Show.tsx
API: POST /api/v1/applications/{uuid}/start|stop|restart
Livewire: Application/General.php
```

### 2.2 Важно (P1)

#### Activity & Notifications
```
React: Activity/Index.tsx, Notifications/Index.tsx
API: Нужно создать endpoints
WebSocket: Broadcast events
```

#### Environment Variables
```
React: Services/Variables.tsx
API: GET/POST/DELETE /api/v1/applications/{uuid}/envs
Livewire: Project/Shared/EnvironmentVariable/All.php
```

#### Database Details
```
React: Databases/Show.tsx, Query.tsx, Backups.tsx
API: Существуют для backups
Livewire: Database/*/General.php
```

### 2.3 Желательно (P2)

- Settings (Account, Tokens, Team)
- Templates gallery
- Observability (Metrics, Traces, Alerts)
- Cron Jobs
- Volumes

---

## Фаза 3: План миграции

### Этап 1: Подготовка
- [ ] Создать feature branch `migration/react-frontend`
- [ ] Настроить Inertia.js middleware
- [ ] Создать API routes для недостающих endpoints

### Этап 2: Подключение критичных фич
- [ ] Деплой: подключить к `/api/v1/deployments`
- [ ] Логи: реализовать WebSocket streaming
- [ ] Статусы: подключить к WebSocket событиям
- [ ] Start/Stop/Restart: подключить к API

### Этап 3: Подключение важных фич
- [ ] Activity feed
- [ ] Notifications
- [ ] Environment Variables CRUD
- [ ] Database operations

### Этап 4: Удаление старого фронта
- [ ] Удалить `app/Livewire/` (160+ файлов)
- [ ] Удалить `resources/views/livewire/` (Blade views)
- [ ] Обновить routes

### Этап 5: Тестирование
- [ ] E2E тесты (Playwright)
- [ ] Integration тесты
- [ ] Staging деплой
- [ ] Production деплой

---

## Маппинг: React → API → Livewire

| React страница | API Endpoint | Livewire компонент |
|----------------|--------------|-------------------|
| Dashboard | `/api/v1/projects` | Dashboard.php |
| Projects/Show | `/api/v1/projects/{uuid}` | Project/Show.php |
| Services/Show | `/api/v1/applications/{uuid}` | Application/General.php |
| Services/Logs | `/api/v1/applications/{uuid}/logs` | Project/Shared/Logs.php |
| Services/Variables | `/api/v1/applications/{uuid}/envs` | Shared/EnvironmentVariable/All.php |
| Deployments/Index | `/api/v1/deployments` | Application/Deployment/Index.php |
| Databases/Show | `/api/v1/databases/{uuid}` | Database/*/General.php |
| Databases/Backups | `/api/v1/databases/{uuid}/backups` | Database/Backup/Index.php |
| Servers/Show | `/api/v1/servers/{uuid}` | Server/Show.php |

---

## Технические заметки

### WebSocket интеграция
```javascript
// Нужно реализовать в React
Echo.private(`team.${teamId}`)
    .listen('ApplicationStatusChanged', (e) => {
        // Обновить статус в UI
    })
    .listen('ServiceStatusChanged', (e) => {
        // Обновить статус сервиса
    });
```

### API аутентификация
- Bearer token через Sanctum
- Abilities: `read`, `write`, `deploy`, `sensitive`

### Inertia.js паттерн
```javascript
// Уже работает для Auth
import { router, useForm } from '@inertiajs/react';

const { data, post, processing } = useForm({ name: '' });
post('/api/v1/projects');
```

---

*Последнее обновление: После завершения аудита*
