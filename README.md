<div align="center">

# Saturn Platform

**Internal self-hosted PaaS for deploying and managing company products**

*Внутренняя self-hosted PaaS-платформа для деплоя и управления продуктами компании*

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![React 18](https://img.shields.io/badge/React-18-61DAFB?style=flat-square&logo=react&logoColor=black)](https://react.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?style=flat-square&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![PostgreSQL 15](https://img.shields.io/badge/PostgreSQL-15-4169E1?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![License](https://img.shields.io/badge/License-Apache_2.0-green?style=flat-square)](LICENSE)

</div>

---

**[English](#english)** | **[Русский](#русский)**

---

<a id="english"></a>

## What is Saturn Platform?

Saturn is a self-hosted Platform as a Service (PaaS) — an alternative to Heroku, Netlify, and Vercel that runs on your own infrastructure. It manages servers, applications, databases, and services via SSH, supporting Docker-based deployments with zero vendor lock-in.

### Key Capabilities

- **Application Deployment** — Git-based Docker deployments with build previews, rollbacks, and multi-server support
- **Database Management** — 8 database types: PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, Dragonfly, ClickHouse
- **Service Templates** — 318 pre-built Docker Compose templates (Supabase, Elasticsearch, Appwrite, n8n, etc.)
- **Real-time Monitoring** — WebSocket-driven live status, deployment logs, and container metrics
- **Team Collaboration** — Multi-tenant with RBAC, deployment approvals, audit logging, and webhooks
- **Notifications** — Slack, Discord, Telegram, Email, Pushover, custom webhooks
- **REST API** — 89+ endpoints with OpenAPI spec, Sanctum token auth
- **CLI & TUI** — Go-based CLI (`saturn`) + terminal UI panel (Ink/React)
- **SSL/TLS** — Automatic Let's Encrypt certificates via Traefik
- **Backups** — Scheduled database backups to S3/SFTP with pg_dump

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Traefik v3.6                             │
│               (SSL termination, routing, GZIP)                  │
├──────────┬──────────────┬──────────────┬───────────────────────-┤
│  Web UI  │  REST API    │  WebSocket   │  Terminal WS           │
│  :443    │  /api/v1/*   │  /app/{key}  │  /terminal/ws          │
├──────────┴──────────────┴──────────────┴───────────────────────-┤
│                     Laravel 12 (PHP 8.4)                        │
│  ┌──────────┐ ┌──────────┐ ┌───────────┐ ┌──────────────────┐  │
│  │ Inertia  │ │ Sanctum  │ │  Actions  │ │   69 Queue Jobs  │  │
│  │ React 18 │ │ API Auth │ │  Pattern  │ │   (deploy,       │  │
│  │ 137 pages│ │ RBAC     │ │  94 models│ │    backup, ...)  │  │
│  └──────────┘ └──────────┘ └───────────┘ └──────────────────┘  │
├────────────────┬──────────────────┬────────────────────────────-┤
│  PostgreSQL 15 │    Redis 7       │   Soketi (WebSocket)        │
│  (data store)  │ (cache/queue/    │   (real-time events,        │
│                │  sessions)       │    terminal streaming)      │
└────────────────┴──────────────────┴────────────────────────────-┘
```

### Data Model

```
Team → Project → Environment → Application / Service / Database
                                    ↓
                                 Server (SSH target, Docker daemon)
```

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4, Eloquent ORM |
| Frontend (new) | React 18, TypeScript 5.9, Inertia.js, Tailwind CSS 4 |
| Frontend (legacy) | Livewire 3, Alpine.js, Blade |
| Database | PostgreSQL 15 |
| Cache / Queue | Redis 7 |
| WebSocket | Soketi (self-hosted Pusher) |
| Proxy | Traefik v3.6 (auto SSL, routing) |
| Container Runtime | Docker + Docker Compose |
| CLI | Go 1.24, Cobra framework |
| TUI Panel | React 18 + Ink 5 + ssh2 |
| CI/CD | GitHub Actions → GHCR → VPS SSH deploy |
| Monitoring | Sentry, Laravel Telescope, Activity Log |
| Testing | Pest 3.8, Vitest 4, Testing Library |

## Repository Structure

```
saturn-platform/
├── app/                        # Laravel backend
│   ├── Actions/                #   Business logic (Action pattern)
│   ├── Http/Controllers/Api/   #   40 API controllers
│   ├── Jobs/                   #   69 background jobs
│   ├── Models/                 #   94 Eloquent models
│   ├── Events/                 #   WebSocket broadcast events
│   ├── Policies/               #   Team-based authorization
│   └── Services/               #   Core services (status aggregation, config generation)
├── resources/js/               # React frontend
│   ├── pages/                  #   137 Inertia.js pages
│   ├── components/             #   UI components (Headless UI, Lucide icons)
│   ├── hooks/                  #   40+ custom hooks
│   └── types/                  #   TypeScript definitions
├── cli/                        # Go CLI (saturn login/deploy/server/...)
├── panel/                      # TUI Panel (Ink terminal UI)
├── templates/compose/          # 318 service templates
├── deploy/
│   ├── scripts/                #   deploy.sh, saturn-ctl.sh, setup-proxy.sh
│   ├── environments/           #   .env.example per environment
│   └── proxy/                  #   Traefik configuration
├── tests/
│   ├── Unit/                   #   153 unit test files
│   └── Feature/                #   52 feature test files
├── docker-compose.yml          # Base services
├── docker-compose.dev.yml      # Local development overrides
├── docker-compose.env.yml      # VPS deployment (parameterized)
├── Dockerfile                  # Multi-stage production build
├── Makefile                    # Development shortcuts
└── .github/workflows/          # CI/CD pipeline
```

## Environments & Deployment

### Three-Environment Pipeline

| Branch | Environment | Domain | Deploy Trigger |
|--------|------------|--------|---------------|
| `dev` | Development | `dev.saturn.ac` | Auto on push |
| `staging` | UAT | `uat.saturn.ac` | Auto on push |
| `main` | Production | `saturn.ac` | Auto on push |

**Promotion flow:** `feature branch` → PR to `dev` → auto-deploy → PR to `staging` → auto-deploy → PR to `main` → production deploy

### Infrastructure

- **VPS:** Hetzner AX42, 64 GB RAM, Ubuntu 24.04
- **Proxy:** Traefik v3.6 shared across all environments (`saturn` Docker network)
- **Isolation:** Each environment gets its own database, Redis, and internal network
- **Containers per env:** `saturn-{env}`, `saturn-db-{env}`, `saturn-redis-{env}`, `saturn-realtime-{env}`
- **Data:** `/data/saturn/{dev,staging,production}/` — fully isolated per environment
- **Registry:** `ghcr.io/kpizzy812/saturn-platform`

### CI/CD Pipeline

```
Push to branch
    │
    ├── [prepare]   Determine env, image tag, domain
    ├── [test]      Pint + PHPStan + Pest (PHP 8.4)
    ├── [build]     Docker Buildx → push to GHCR
    └── [deploy]    Rsync + SSH → deploy.sh on VPS
                        │
                        ├── pg_dump backup
                        ├── Pull images
                        ├── Start infrastructure (DB, Redis, Soketi)
                        ├── Run migrations
                        ├── Start application
                        ├── Clear & rebuild caches
                        ├── Restore Traefik config
                        └── Health check (/api/health)
```

## Local Development

### Prerequisites

- Docker & Docker Compose
- Node.js 24+ (for frontend)
- PHP 8.4 (for IDE / linting only)

### Quick Start

```bash
# 1. Clone and start
git clone git@github.com:kpizzy812/coolify-Saturn.git
cd coolify-Saturn
cp .env.example .env

# 2. Start all services
make dev

# 3. Install dependencies and seed database
make install
make fresh          # migrate:fresh --seed

# 4. Start frontend dev server
npm install
npm run dev         # Vite HMR on :5173
```

### Available Make Commands

| Command | Description |
|---------|------------|
| `make dev` | Start all Docker services |
| `make dev-build` | Build images and start |
| `make dev-down` | Stop all services |
| `make dev-logs` | Follow container logs |
| `make shell` | Open Laravel container shell |
| `make db-shell` | Open PostgreSQL shell |
| `make redis-shell` | Open Redis CLI |
| `make install` | Run `composer install` |
| `make migrate` | Run database migrations |
| `make fresh` | Fresh migrate + seed |
| `make test` | Run PHP tests (inside Docker) |
| `make test-js` | Run frontend tests (Vitest) |
| `make build` | Build frontend for production |
| `make panel` | Launch TUI Panel |
| `make panel-test` | Run TUI Panel tests |

### Docker Services (Local)

| Service | Port | Description |
|---------|------|------------|
| saturn | 8000 | Laravel application |
| postgres | 5432 | PostgreSQL 15 |
| redis | 6379 | Redis 7 |
| soketi | 6001, 6002 | WebSocket + Terminal |
| vite | 5173 | Frontend HMR |
| mailpit | 8025 | Email sandbox UI |
| minio | 9000, 9001 | S3-compatible storage |

## Testing

### PHP Tests

```bash
# Unit tests (no database, can run locally)
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Unit/SomeTest.php

# Feature tests (MUST run inside Docker — requires database)
docker exec saturn php artisan test
docker exec saturn php artisan test --filter=SomeTest
```

### Frontend Tests

```bash
npm run test              # Vitest (watch mode)
npm run test -- --run     # Single run
npm run test:coverage     # With coverage report
```

### TUI Panel Tests

```bash
make panel-test           # 489 tests
```

### Code Quality

```bash
./vendor/bin/pint           # PHP formatter (PSR-12)
./vendor/bin/phpstan analyse  # Static analysis (level 5)
npm run lint                # ESLint for TypeScript/React
```

## API

REST API v1 with 89+ endpoints. Authentication via Laravel Sanctum tokens.

### Token Abilities

| Ability | Description |
|---------|------------|
| `read` | Read resources |
| `write` | Create / update resources |
| `deploy` | Trigger deployments |
| `root` | Full access |
| `read:sensitive` | Access environment variables |

### Key Endpoints

```
GET    /api/health                          # Public health check
POST   /api/v1/cli/auth/init               # Device auth flow (CLI)

GET    /api/v1/teams                        # Teams & members
GET    /api/v1/projects                     # Projects & environments
GET    /api/v1/servers                      # Servers + Sentinel metrics
GET    /api/v1/applications                 # Applications CRUD
GET    /api/v1/databases                    # Databases CRUD + backups
GET    /api/v1/services                     # Services CRUD + health
POST   /api/v1/deploy                       # Trigger deployment
GET    /api/v1/deployments                  # Deployment history + logs
GET    /api/v1/notifications                # Notification channels
POST   /api/v1/webhooks                     # Team webhooks
GET    /api/v1/deployment-approvals         # Approve/reject deploys
```

## CLI

Go-based CLI for managing Saturn from the terminal.

```bash
# Authentication (device auth flow — opens browser)
saturn login

# Manage resources
saturn server list
saturn application list
saturn application deploy <uuid>
saturn database list
saturn deployment list
saturn deployment logs <uuid>
```

Config stored at `~/.config/saturn/config.json`.

## TUI Panel

Terminal UI for real-time infrastructure management. Connects directly to VPS via SSH.

```bash
make panel              # Launch TUI
```

**Screens:** Dashboard `[1]`, Git `[2]`, Deploy `[3]`, Logs `[4]`, Containers `[5]`, Database `[6]`, Env `[7]`

**Navigation:** Number keys `1-7` switch screens, `q` quit, `?` help, `e` cycle environment, `Esc` back

## VPS Management

### Quick Commands (on server)

```bash
# Interactive control panel
./deploy/scripts/saturn-ctl.sh

# Direct commands
./deploy/scripts/saturn-ctl.sh status
./deploy/scripts/saturn-ctl.sh logs
./deploy/scripts/saturn-ctl.sh deploy
./deploy/scripts/saturn-ctl.sh restart
```

### Install Shell Aliases

```bash
./deploy/scripts/install-aliases.sh

# After installation:
saturn                    # Open control panel
saturn-logs               # View logs
saturn-deploy             # Deploy
saturn-shell              # Container shell
saturn-artisan            # Laravel Artisan
saturn-db                 # PostgreSQL shell
```

### Manual Deploy (without CI/CD)

```bash
ssh root@157.180.57.47
cd /root/coolify-Saturn
git pull origin dev
SATURN_ENV=dev ./deploy/scripts/deploy.sh
```

### Health Check

```bash
curl https://dev.saturn.ac/api/health
# {"status":"ok"}
```

## Configuration

Environment configuration per deployment:

```
deploy/environments/
├── dev/.env.example          # Development defaults
├── staging/.env.example      # Staging defaults
└── production/.env.example   # Production defaults (hardened)
```

### Critical Environment Variables

```env
# Application
APP_ENV=production|staging|development
APP_URL=https://saturn.ac
APP_KEY=                        # php artisan key:generate --show
SATURN_ENV=production|staging|dev

# Database
DB_CONNECTION=pgsql
DB_HOST=saturn-db               # Docker service name
DB_DATABASE=saturn
DB_PASSWORD=                    # openssl rand -base64 32

# Redis
REDIS_HOST=saturn-redis
REDIS_PASSWORD=                 # openssl rand -base64 32

# WebSocket (Soketi)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=

# Security
CORS_ALLOWED_ORIGINS=https://saturn.ac
IS_REGISTRATION_ENABLED=false   # Production: disabled
SANCTUM_STATEFUL_DOMAINS=saturn.ac

# Monitoring
SENTRY_DSN=                     # Optional
TELESCOPE_ENABLED=false         # Production: disabled
```

## Project Documentation

Detailed technical documentation lives in the `.ai/` directory:

| Document | Contents |
|----------|---------|
| `.ai/core/technology-stack.md` | Version numbers (single source of truth) |
| `.ai/core/application-architecture.md` | System design, CLI auth, container status |
| `.ai/core/deployment-architecture.md` | Docker Compose, deploy script, Traefik |
| `.ai/development/testing-patterns.md` | Testing conventions and patterns |
| `.ai/patterns/security-patterns.md` | Mass assignment, injection, multi-tenancy |
| `.ai/patterns/database-patterns.md` | Queries, caching, N+1 prevention |
| `.ai/patterns/frontend-patterns.md` | React components, hooks, Inertia |

## License

[Apache License 2.0](LICENSE)

---

<a id="русский"></a>

## Что такое Saturn Platform?

Saturn — это self-hosted Platform as a Service (PaaS), альтернатива Heroku, Netlify и Vercel, которая работает на вашей собственной инфраструктуре. Платформа управляет серверами, приложениями, базами данных и сервисами через SSH, поддерживая Docker-based деплой без привязки к вендору.

### Ключевые возможности

- **Деплой приложений** — Git-based Docker деплой с превью билдов, откатами и мульти-серверной поддержкой
- **Управление базами данных** — 8 типов БД: PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, Dragonfly, ClickHouse
- **Шаблоны сервисов** — 318 готовых Docker Compose шаблонов (Supabase, Elasticsearch, Appwrite, n8n и др.)
- **Мониторинг в реальном времени** — WebSocket-обновления статусов, логов деплоя и метрик контейнеров
- **Командная работа** — Мультитенантность с RBAC, аппрувы деплоев, аудит-логи и вебхуки
- **Уведомления** — Slack, Discord, Telegram, Email, Pushover, кастомные вебхуки
- **REST API** — 89+ эндпоинтов с OpenAPI-спецификацией, авторизация по Sanctum-токенам
- **CLI и TUI** — Go-based CLI (`saturn`) + терминальная UI-панель (Ink/React)
- **SSL/TLS** — Автоматические Let's Encrypt сертификаты через Traefik
- **Бэкапы** — Расписание бэкапов БД на S3/SFTP через pg_dump

## Архитектура

```
┌─────────────────────────────────────────────────────────────────┐
│                        Traefik v3.6                             │
│             (SSL-терминация, роутинг, GZIP)                     │
├──────────┬──────────────┬──────────────┬───────────────────────-┤
│  Web UI  │  REST API    │  WebSocket   │  Terminal WS           │
│  :443    │  /api/v1/*   │  /app/{key}  │  /terminal/ws          │
├──────────┴──────────────┴──────────────┴───────────────────────-┤
│                     Laravel 12 (PHP 8.4)                        │
│  ┌──────────┐ ┌──────────┐ ┌───────────┐ ┌──────────────────┐  │
│  │ Inertia  │ │ Sanctum  │ │  Actions  │ │  69 фоновых      │  │
│  │ React 18 │ │ API Auth │ │  Pattern  │ │  задач (деплой,  │  │
│  │ 137 стр. │ │ RBAC     │ │  94 модели│ │  бэкап, ...)     │  │
│  └──────────┘ └──────────┘ └───────────┘ └──────────────────┘  │
├────────────────┬──────────────────┬────────────────────────────-┤
│  PostgreSQL 15 │    Redis 7       │   Soketi (WebSocket)        │
│  (хранилище)   │  (кэш/очереди/  │   (реалтайм события,        │
│                │   сессии)        │    стриминг терминала)       │
└────────────────┴──────────────────┴────────────────────────────-┘
```

### Модель данных

```
Команда → Проект → Окружение → Приложение / Сервис / База данных
                                    ↓
                                 Сервер (SSH-цель, Docker daemon)
```

### Технологический стек

| Слой | Технология |
|------|-----------|
| Бэкенд | Laravel 12, PHP 8.4, Eloquent ORM |
| Фронтенд (новый) | React 18, TypeScript 5.9, Inertia.js, Tailwind CSS 4 |
| Фронтенд (legacy) | Livewire 3, Alpine.js, Blade |
| База данных | PostgreSQL 15 |
| Кэш / Очереди | Redis 7 |
| WebSocket | Soketi (self-hosted Pusher) |
| Прокси | Traefik v3.6 (авто SSL, роутинг) |
| Контейнеры | Docker + Docker Compose |
| CLI | Go 1.24, Cobra |
| TUI-панель | React 18 + Ink 5 + ssh2 |
| CI/CD | GitHub Actions → GHCR → VPS SSH деплой |
| Мониторинг | Sentry, Laravel Telescope, Activity Log |
| Тестирование | Pest 3.8, Vitest 4, Testing Library |

## Структура репозитория

```
saturn-platform/
├── app/                        # Laravel бэкенд
│   ├── Actions/                #   Бизнес-логика (Action паттерн)
│   ├── Http/Controllers/Api/   #   40 API контроллеров
│   ├── Jobs/                   #   69 фоновых задач
│   ├── Models/                 #   94 Eloquent модели
│   ├── Events/                 #   WebSocket-события
│   ├── Policies/               #   Авторизация на уровне команд
│   └── Services/               #   Сервисы (агрегация статусов, генерация конфигов)
├── resources/js/               # React фронтенд
│   ├── pages/                  #   137 Inertia.js страниц
│   ├── components/             #   UI-компоненты (Headless UI, Lucide)
│   ├── hooks/                  #   40+ кастомных хуков
│   └── types/                  #   TypeScript-определения
├── cli/                        # Go CLI (saturn login/deploy/server/...)
├── panel/                      # TUI-панель (Ink-терминальный UI)
├── templates/compose/          # 318 шаблонов сервисов
├── deploy/
│   ├── scripts/                #   deploy.sh, saturn-ctl.sh, setup-proxy.sh
│   ├── environments/           #   .env.example для каждого окружения
│   └── proxy/                  #   Конфигурация Traefik
├── tests/
│   ├── Unit/                   #   153 файла юнит-тестов
│   └── Feature/                #   52 файла функциональных тестов
├── docker-compose.yml          # Базовые сервисы
├── docker-compose.dev.yml      # Переопределения для локальной разработки
├── docker-compose.env.yml      # VPS-деплой (параметризованный)
├── Dockerfile                  # Многоэтапная продакшн-сборка
├── Makefile                    # Команды разработки
└── .github/workflows/          # CI/CD пайплайн
```

## Окружения и деплой

### Три окружения

| Ветка | Окружение | Домен | Триггер деплоя |
|-------|----------|-------|---------------|
| `dev` | Development | `dev.saturn.ac` | Автоматически при push |
| `staging` | UAT | `uat.saturn.ac` | Автоматически при push |
| `main` | Production | `saturn.ac` | Автоматически при push |

**Путь промоции:** `feature branch` → PR в `dev` → авто-деплой → PR в `staging` → авто-деплой → PR в `main` → деплой в прод

### Инфраструктура

- **VPS:** Hetzner AX42, 64 ГБ RAM, Ubuntu 24.04
- **Прокси:** Traefik v3.6, общий для всех окружений (Docker-сеть `saturn`)
- **Изоляция:** Каждое окружение имеет собственную БД, Redis и внутреннюю сеть
- **Контейнеры:** `saturn-{env}`, `saturn-db-{env}`, `saturn-redis-{env}`, `saturn-realtime-{env}`
- **Данные:** `/data/saturn/{dev,staging,production}/` — полная изоляция по окружениям
- **Registry:** `ghcr.io/kpizzy812/saturn-platform`

### CI/CD пайплайн

```
Push в ветку
    │
    ├── [prepare]   Определение env, image tag, домена
    ├── [test]      Pint + PHPStan + Pest (PHP 8.4)
    ├── [build]     Docker Buildx → push в GHCR
    └── [deploy]    Rsync + SSH → deploy.sh на VPS
                        │
                        ├── pg_dump бэкап БД
                        ├── Pull образов
                        ├── Запуск инфраструктуры (DB, Redis, Soketi)
                        ├── Миграции
                        ├── Запуск приложения
                        ├── Очистка и пересборка кэшей
                        ├── Восстановление конфигов Traefik
                        └── Health check (/api/health)
```

## Локальная разработка

### Предварительные требования

- Docker и Docker Compose
- Node.js 24+ (для фронтенда)
- PHP 8.4 (только для IDE / линтинга)

### Быстрый старт

```bash
# 1. Клонировать и запустить
git clone git@github.com:kpizzy812/coolify-Saturn.git
cd coolify-Saturn
cp .env.example .env

# 2. Запустить все сервисы
make dev

# 3. Установить зависимости и заполнить БД
make install
make fresh          # migrate:fresh --seed

# 4. Запустить фронтенд дев-сервер
npm install
npm run dev         # Vite HMR на :5173
```

### Команды Makefile

| Команда | Описание |
|---------|---------|
| `make dev` | Запуск всех Docker-сервисов |
| `make dev-build` | Сборка образов и запуск |
| `make dev-down` | Остановка сервисов |
| `make dev-logs` | Следить за логами |
| `make shell` | Shell в контейнере Laravel |
| `make db-shell` | Shell PostgreSQL |
| `make redis-shell` | Redis CLI |
| `make install` | `composer install` |
| `make migrate` | Миграции БД |
| `make fresh` | Чистая миграция + seed |
| `make test` | PHP-тесты (в Docker) |
| `make test-js` | Фронтенд-тесты (Vitest) |
| `make build` | Продакшн-сборка фронтенда |
| `make panel` | Запуск TUI-панели |
| `make panel-test` | Тесты TUI-панели |

### Docker-сервисы (локально)

| Сервис | Порт | Описание |
|--------|------|---------|
| saturn | 8000 | Laravel-приложение |
| postgres | 5432 | PostgreSQL 15 |
| redis | 6379 | Redis 7 |
| soketi | 6001, 6002 | WebSocket + терминал |
| vite | 5173 | Фронтенд HMR |
| mailpit | 8025 | Песочница для email |
| minio | 9000, 9001 | S3-совместимое хранилище |

## Тестирование

### PHP-тесты

```bash
# Юнит-тесты (без БД, можно запускать локально)
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Unit/SomeTest.php

# Функциональные тесты (ТОЛЬКО в Docker — требуют БД)
docker exec saturn php artisan test
docker exec saturn php artisan test --filter=SomeTest
```

### Фронтенд-тесты

```bash
npm run test              # Vitest (watch mode)
npm run test -- --run     # Одноразовый запуск
npm run test:coverage     # С покрытием
```

### Тесты TUI-панели

```bash
make panel-test           # 489 тестов
```

### Качество кода

```bash
./vendor/bin/pint           # PHP-форматтер (PSR-12)
./vendor/bin/phpstan analyse  # Статический анализ (уровень 5)
npm run lint                # ESLint для TypeScript/React
```

## API

REST API v1 с 89+ эндпоинтами. Авторизация через токены Laravel Sanctum.

### Возможности токенов

| Ability | Описание |
|---------|---------|
| `read` | Чтение ресурсов |
| `write` | Создание / обновление |
| `deploy` | Запуск деплоев |
| `root` | Полный доступ |
| `read:sensitive` | Доступ к переменным окружения |

### Основные эндпоинты

```
GET    /api/health                          # Публичный health check
POST   /api/v1/cli/auth/init               # Device auth flow (CLI)

GET    /api/v1/teams                        # Команды и участники
GET    /api/v1/projects                     # Проекты и окружения
GET    /api/v1/servers                      # Серверы + метрики Sentinel
GET    /api/v1/applications                 # CRUD приложений
GET    /api/v1/databases                    # CRUD баз данных + бэкапы
GET    /api/v1/services                     # CRUD сервисов + health check
POST   /api/v1/deploy                       # Запуск деплоя
GET    /api/v1/deployments                  # История деплоев + логи
GET    /api/v1/notifications                # Каналы уведомлений
POST   /api/v1/webhooks                     # Командные вебхуки
GET    /api/v1/deployment-approvals         # Аппрувы деплоев
```

## CLI

Go-based CLI для управления Saturn из терминала.

```bash
# Авторизация (device auth flow — открывает браузер)
saturn login

# Управление ресурсами
saturn server list
saturn application list
saturn application deploy <uuid>
saturn database list
saturn deployment list
saturn deployment logs <uuid>
```

Конфигурация хранится в `~/.config/saturn/config.json`.

## TUI-панель

Терминальный UI для управления инфраструктурой в реальном времени. Подключается напрямую к VPS по SSH.

```bash
make panel              # Запуск TUI
```

**Экраны:** Dashboard `[1]`, Git `[2]`, Deploy `[3]`, Logs `[4]`, Containers `[5]`, Database `[6]`, Env `[7]`

**Навигация:** Клавиши `1-7` — переключение экранов, `q` — выход, `?` — справка, `e` — смена окружения, `Esc` — назад

## Управление VPS

### Быстрые команды (на сервере)

```bash
# Интерактивная панель управления
./deploy/scripts/saturn-ctl.sh

# Прямые команды
./deploy/scripts/saturn-ctl.sh status
./deploy/scripts/saturn-ctl.sh logs
./deploy/scripts/saturn-ctl.sh deploy
./deploy/scripts/saturn-ctl.sh restart
```

### Установка алиасов

```bash
./deploy/scripts/install-aliases.sh

# После установки:
saturn                    # Панель управления
saturn-logs               # Логи
saturn-deploy             # Деплой
saturn-shell              # Shell контейнера
saturn-artisan            # Laravel Artisan
saturn-db                 # PostgreSQL shell
```

### Ручной деплой (без CI/CD)

```bash
ssh root@157.180.57.47
cd /root/coolify-Saturn
git pull origin dev
SATURN_ENV=dev ./deploy/scripts/deploy.sh
```

### Проверка здоровья

```bash
curl https://dev.saturn.ac/api/health
# {"status":"ok"}
```

## Конфигурация

Файлы конфигурации для каждого окружения:

```
deploy/environments/
├── dev/.env.example          # Настройки для разработки
├── staging/.env.example      # Настройки для UAT
└── production/.env.example   # Продакшн (усиленная безопасность)
```

### Критические переменные окружения

```env
# Приложение
APP_ENV=production|staging|development
APP_URL=https://saturn.ac
APP_KEY=                        # php artisan key:generate --show
SATURN_ENV=production|staging|dev

# База данных
DB_CONNECTION=pgsql
DB_HOST=saturn-db               # Имя Docker-сервиса
DB_DATABASE=saturn
DB_PASSWORD=                    # openssl rand -base64 32

# Redis
REDIS_HOST=saturn-redis
REDIS_PASSWORD=                 # openssl rand -base64 32

# WebSocket (Soketi)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=

# Безопасность
CORS_ALLOWED_ORIGINS=https://saturn.ac
IS_REGISTRATION_ENABLED=false   # Продакшн: отключено
SANCTUM_STATEFUL_DOMAINS=saturn.ac

# Мониторинг
SENTRY_DSN=                     # Опционально
TELESCOPE_ENABLED=false         # Продакшн: отключено
```

## Документация проекта

Детальная техническая документация находится в директории `.ai/`:

| Документ | Содержание |
|----------|-----------|
| `.ai/core/technology-stack.md` | Версии технологий (единый источник истины) |
| `.ai/core/application-architecture.md` | Архитектура, CLI auth, статусы контейнеров |
| `.ai/core/deployment-architecture.md` | Docker Compose, скрипт деплоя, Traefik |
| `.ai/development/testing-patterns.md` | Конвенции и паттерны тестирования |
| `.ai/patterns/security-patterns.md` | Mass assignment, инъекции, мультитенантность |
| `.ai/patterns/database-patterns.md` | Запросы, кэширование, предотвращение N+1 |
| `.ai/patterns/frontend-patterns.md` | React-компоненты, хуки, Inertia |

## Лицензия

[Apache License 2.0](LICENSE)
