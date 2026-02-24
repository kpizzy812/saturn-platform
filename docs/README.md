# Saturn Platform - Documentation

Организованная документация проекта Saturn Platform.

---

## 📁 Структура Документации

### 📊 [TECH_STACK.md](TECH_STACK.md)
Технологический стек проекта:
- Frontend: React 18, TypeScript, Inertia.js, Tailwind CSS
- Backend: Laravel 12, PHP 8.4
- Database: PostgreSQL 15, Redis 7
- Infrastructure: Docker, Soketi (WebSockets)

---

## 📂 Категории

### 🔌 [api/](api/)
API документация и конфигурация

**Файлы:**
- [API_CONTROLLERS_SUMMARY.md](api/API_CONTROLLERS_SUMMARY.md) - Полный список API контроллеров и endpoints
- [WEBSOCKET_CONFIGURATION_SUMMARY.md](api/WEBSOCKET_CONFIGURATION_SUMMARY.md) - Конфигурация WebSocket (Soketi)
- [WEBSOCKET_IMPLEMENTATION.md](api/WEBSOCKET_IMPLEMENTATION.md) - Детали реализации WebSocket events

**Что здесь:**
- 89+ REST API endpoints
- WebSocket channels и события
- Broadcast configuration
- Real-time features

---

### 📊 [audits/](audits/)
Аудиты проекта и отчеты о готовности

**Файлы:**
- [SATURN_AUDIT.md](audits/SATURN_AUDIT.md) - Полный аудит проекта (backend + frontend)
- [SATURN_FRONTEND_AUDIT_REPORT.md](audits/SATURN_FRONTEND_AUDIT_REPORT.md) - Подробный аудит фронтенда
- [SATURN_FINAL_READINESS_REPORT.md](audits/SATURN_FINAL_READINESS_REPORT.md) - Финальная оценка готовности
- [SATURN_MISSING_PAGES.md](audits/SATURN_MISSING_PAGES.md) - Отсутствующие страницы
- [SATURN_READINESS_ASSESSMENT.md](audits/SATURN_READINESS_ASSESSMENT.md) - Оценка готовности к продакшну

**Что здесь:**
- Метрики проекта (137 страниц, 60+ моделей, 49 jobs)
- Анализ покрытия тестами (<5%)
- Список проблем и блокеров
- Feature parity анализ (~75%)
- GO/NO-GO рекомендации

---

### 🚚 [migration/](migration/)
Планы миграции и деплоя

**Файлы:**
- [MIGRATION_PLAN.md](migration/MIGRATION_PLAN.md) - План миграции с Livewire на React
- [RAILWAY_MIGRATION_PLAN.md](migration/RAILWAY_MIGRATION_PLAN.md) - План деплоя на Railway
- [RAILWAY_REACT_DEPLOYMENT.md](migration/RAILWAY_REACT_DEPLOYMENT.md) - React deployment на Railway

**Что здесь:**
- Стратегия миграции frontend
- Deployment стратегии
- Railway-specific конфигурация
- CI/CD пайплайны

---

### 📋 [planning/](planning/)
Планирование и roadmap

**Файлы:**
- [REDESIGN_PLAN.md](planning/REDESIGN_PLAN.md) - План редизайна UI
- [SATURN_NEXT_STEPS.md](planning/SATURN_NEXT_STEPS.md) - Следующие шаги после аудита

**Что здесь:**
- UI/UX планы
- Feature roadmap
- Приоритизация задач
- Timeline estimates

---

### 🖥️ [TUI Panel](../panel/)
Терминальная панель управления инфраструктурой Saturn

**Расположение:** `panel/` (standalone package, отдельный от web frontend)

**Запуск:**
```bash
make panel          # Launch TUI
make panel-test     # Run 489 tests
make panel-build    # Build distributable
```

**Что здесь:**
- 42 исходных файла (React 18 + TypeScript + Ink 5)
- SSH подключение к VPS (ssh2, auto-reconnect)
- 7 экранов: Dashboard, Git, Deploy, Logs, Containers, Database, Env
- gh CLI интеграция для GitHub (PRs, Actions)
- Real-time log streaming, container management, deploy/rollback

---

### 👨‍💻 [development/](development/)
Документация для разработчиков

**Файлы:**
- [CLAUDE.md](development/CLAUDE.md) - Инструкции для работы с Claude AI

**Что здесь:**
- Development workflows
- AI coding guidelines
- Coding standards
- Best practices

---

## 🚀 Начало Работы

### Для Новых Разработчиков

1. **Прочитайте основные файлы:**
   - [../README.md](../README.md) - Основная документация
   - [../PROJECT_ARCHITECTURE.md](../PROJECT_ARCHITECTURE.md) - Архитектура проекта
   - [TECH_STACK.md](TECH_STACK.md) - Технологический стек

2. **Изучите текущее состояние:**
   - [audits/SATURN_AUDIT.md](audits/SATURN_AUDIT.md) - Полный аудит
   - [../IMMEDIATE_ISSUES.md](../IMMEDIATE_ISSUES.md) - Текущие проблемы

3. **Посмотрите задачи:**
   - [../REFACTORING_TODO.md](../REFACTORING_TODO.md) - TODO list

4. **Настройте окружение:**
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

   # Запустить dev server
   npm run dev
   ```

---

## 📊 Быстрые Ссылки

### Backend
- API Endpoints: [api/API_CONTROLLERS_SUMMARY.md](api/API_CONTROLLERS_SUMMARY.md)
- Models: `../app/Models/` (60+ моделей)
- Jobs: `../app/Jobs/` (49 background jobs)
- Events: `../app/Events/` (20 WebSocket событий)

### Frontend
- Pages: `../resources/js/pages/` (137 страниц)
- Components: `../resources/js/components/` (40+ компонентов)
- Hooks: `../resources/js/hooks/` (12 custom hooks)
- Types: `../resources/js/types/` (TypeScript типы)

### Infrastructure
- Docker Compose: `../docker-compose.dev.yml`
- Environment: `../.env.development.example`
- Routes: `../routes/` (web, api, channels)

---

## 🔍 Поиск Информации

### Хочу узнать про...

**API и Endpoints:**
- Список всех API → [api/API_CONTROLLERS_SUMMARY.md](api/API_CONTROLLERS_SUMMARY.md)
- WebSocket события → [api/WEBSOCKET_IMPLEMENTATION.md](api/WEBSOCKET_IMPLEMENTATION.md)

**Текущее состояние проекта:**
- Общий аудит → [audits/SATURN_AUDIT.md](audits/SATURN_AUDIT.md)
- Аудит фронтенда → [audits/SATURN_FRONTEND_AUDIT_REPORT.md](audits/SATURN_FRONTEND_AUDIT_REPORT.md)
- Готовность к продакшну → [audits/SATURN_FINAL_READINESS_REPORT.md](audits/SATURN_FINAL_READINESS_REPORT.md)

**Что нужно сделать:**
- Проблемы → [../IMMEDIATE_ISSUES.md](../IMMEDIATE_ISSUES.md)
- TODO список → [../REFACTORING_TODO.md](../REFACTORING_TODO.md)

**Архитектуру:**
- Обзор архитектуры → [../PROJECT_ARCHITECTURE.md](../PROJECT_ARCHITECTURE.md)
- Технологический стек → [TECH_STACK.md](TECH_STACK.md)

**Деплой и миграцию:**
- План миграции → [migration/MIGRATION_PLAN.md](migration/MIGRATION_PLAN.md)
- Railway деплой → [migration/RAILWAY_MIGRATION_PLAN.md](migration/RAILWAY_MIGRATION_PLAN.md)

---

## 📝 Обновление Документации

При добавлении новой документации:

1. **Выберите правильную категорию:**
   - `api/` - API endpoints, WebSocket
   - `audits/` - Аудиты, отчеты о состоянии
   - `migration/` - Планы миграции, deployment
   - `planning/` - Roadmaps, планирование
   - `development/` - Dev guidelines, workflows

2. **Создайте осмысленное имя файла:**
   - Используйте UPPER_SNAKE_CASE.md
   - Будьте описательными: `API_AUTHENTICATION_GUIDE.md`

3. **Добавьте ссылку в этот README**

4. **Обновите дату в футере**

---

## 🎯 Критические Документы

### Обязательно к прочтению:
1. [../PROJECT_ARCHITECTURE.md](../PROJECT_ARCHITECTURE.md) - **Что за что отвечает**
2. [../IMMEDIATE_ISSUES.md](../IMMEDIATE_ISSUES.md) - **Текущие проблемы**
3. [audits/SATURN_AUDIT.md](audits/SATURN_AUDIT.md) - **Полный аудит**

### Для начала работы:
4. [TECH_STACK.md](TECH_STACK.md) - Технологии
5. [../REFACTORING_TODO.md](../REFACTORING_TODO.md) - Задачи
6. [api/API_CONTROLLERS_SUMMARY.md](api/API_CONTROLLERS_SUMMARY.md) - API

---

**Последнее обновление:** 2026-01-21
**Всего документов:** 16 файлов в 5 категориях
