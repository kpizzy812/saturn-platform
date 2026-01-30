# Monorepo Auto-Detection & Full-Stack Provisioning

**Issue:** https://github.com/kpizzy812/saturn-platform/issues/1
**Priority:** High (улучшение UX для современных проектов)
**Status:** Planning
**Updated:** 2026-01-30
**Last Review:** 2026-01-30 (критический анализ и исправления)

---

## Ключевые исправления (v2)

После критического анализа были исправлены следующие проблемы:

### Архитектура (01-architecture.md)
- ✅ Добавлена валидация `repoPath` для предотвращения path traversal
- ✅ Добавлен лимит размера репозитория (500MB)
- ✅ Добавлена обработка ошибок JSON/YAML парсинга
- ✅ DTOs теперь immutable (`withMergedConsumers()` вместо мутации)

### MonorepoDetector (02-monorepo-detector.md)
- ✅ Заменён `yaml_parse()` на `Symfony\Component\Yaml\Yaml::parse()`
- ✅ Добавлен парсинг `nx.json` и `workspace.json` для Nx проектов
- ✅ Добавлена поддержка Yarn 2+ workspaces format (`{ packages: [...] }`)
- ✅ Добавлены проверки на пустые конфиги и ошибки парсинга
- ✅ Лимит размера файлов (1MB) для предотвращения OOM

### AppDetector (03-app-detector.md)
- ✅ Исправлена логика проверки deps: `matchMode: 'all'` для vite-react/vite-vue
- ✅ Заменён `str_contains()` на парсинг зависимостей из package.json/requirements.txt
- ✅ Добавлена поддержка `excludeDeps` для исключения meta-frameworks
- ✅ Добавлен тип приложения (`backend`, `frontend`, `fullstack`)
- ✅ Улучшен парсинг Dockerfile (EXPOSE с /tcp, множественные порты)
- ✅ Добавлена поддержка docker-compose.yml

### DependencyAnalyzer (04-dependency-analyzer.md)
- ✅ Используется существующий `App\Services\EnvExampleParser`
- ✅ Добавлен парсинг pyproject.toml через `yosymfony/toml`
- ✅ Улучшен парсинг go.mod (require блоки)
- ✅ Парсинг Cargo.toml через TOML вместо regex
- ✅ Exact match для зависимостей вместо `str_contains()`

### DTOs (05-dtos.md)
- ✅ Добавлен `type` в `DetectedApp`
- ✅ `DetectedDatabase` теперь `readonly` с `withMergedConsumers()`
- ✅ Добавлен `RepositoryAnalysisException`
- ✅ Добавлен `ProvisioningException`

### InfrastructureProvisioner (06-infrastructure-provisioner.md)
- ✅ Добавлена DB транзакция для атомарности
- ✅ Добавлена обработка ошибок с `ProvisioningException`
- ✅ Исправлено создание Application (static site config, правильные поля)
- ✅ Заменён `generateUrl` на `generateFqdn`
- ✅ Поддержка разных git sources (GitHub, GitLab)

### API Endpoints (07-api-endpoints.md)
- ✅ **Критично:** Исправлен return type `filterAnalysis()` (была синтаксическая ошибка)
- ✅ Заменён `exec('git clone')` на `Process::timeout()` с таймаутом
- ✅ Добавлена авторизация через `Gate::authorize()`
- ✅ Валидация git repository URL через regex
- ✅ Поддержка GitHub/GitLab/Bitbucket sources
- ✅ Правильная обработка ошибок API

### Frontend UI (08-frontend-ui.md)
- ✅ Заменён несуществующий `useApi` на `axios`
- ✅ Добавлено отображение типа приложения (backend/frontend/fullstack)
- ✅ Улучшена обработка ошибок API (401/403)
- ✅ Добавлены иконки для типов приложений

### Testing (09-testing.md)
- ✅ Исправлен `createApp()` с полем `type`
- ✅ Добавлены тесты для edge cases (пустой JSON, невалидный YAML)
- ✅ Тесты для Nx с project.json format
- ✅ Тесты для yarn 2+ workspaces format
- ✅ Тесты для vite-react с `matchMode: 'all'`

### Migration (10-migration.md)
- ✅ **Критично:** Исправлен `monorepoSiblings()` (использовался неправильный relationship)
- ✅ Добавлены scopes: `inMonorepoGroup()`, `monorepoApps()`, `standaloneApps()`
- ✅ Добавлен `getMonorepoGroupCount()`
- ✅ Правильный cast для `monorepo_group_id`

---

## Цель

Автоматическое определение структуры проекта и создание полной инфраструктуры "из коробки":

```
Указал репозиторий → Saturn анализирует → Предлагает инфраструктуру → Deploy
```

**Что определяем:**
1. Тип монорепо (Turborepo, Nx, pnpm workspaces, Lerna)
2. Приложения и их типы (NestJS, Next.js, FastAPI, etc.)
3. Требуемые базы данных (PostgreSQL, MongoDB, Redis)
4. Внешние сервисы (S3, Elasticsearch, RabbitMQ)
5. Переменные окружения из `.env.example`

---

## Документация

| Файл | Описание |
|------|----------|
| [01-architecture.md](./01-architecture.md) | Архитектура решения, диаграммы |
| [02-monorepo-detector.md](./02-monorepo-detector.md) | Детектор типа монорепо |
| [03-app-detector.md](./03-app-detector.md) | Детектор приложений и фреймворков |
| [04-dependency-analyzer.md](./04-dependency-analyzer.md) | Анализ зависимостей (БД, сервисы, env) |
| [05-dtos.md](./05-dtos.md) | Data Transfer Objects |
| [06-infrastructure-provisioner.md](./06-infrastructure-provisioner.md) | Создание инфраструктуры |
| [07-api-endpoints.md](./07-api-endpoints.md) | API контроллер и роуты |
| [08-frontend-ui.md](./08-frontend-ui.md) | React компонент |
| [09-testing.md](./09-testing.md) | Unit и Feature тесты |
| [10-migration.md](./10-migration.md) | Миграции базы данных |

---

## Приоритеты реализации

| Phase | Описание | Приоритет | Сложность |
|-------|----------|-----------|-----------|
| 1 | RepositoryAnalyzer + Detectors | P0 | High |
| 2 | DependencyAnalyzer (DB detection) | P0 | Medium |
| 3 | DTOs | P0 | Low |
| 4 | InfrastructureProvisioner | P0 | High |
| 5 | API Endpoints | P1 | Medium |
| 6 | Frontend UI | P1 | Medium |
| 7 | Database Migration | P1 | Low |

---

## Новые файлы для создания

```
app/Services/RepositoryAnalyzer/
├── RepositoryAnalyzer.php           # Главный сервис
├── InfrastructureProvisioner.php    # Создание ресурсов
├── ProvisioningResult.php           # Результат провизионирования
├── Detectors/
│   ├── MonorepoDetector.php         # Детектор монорепо
│   ├── AppDetector.php              # Детектор приложений
│   └── DependencyAnalyzer.php       # Анализ зависимостей
├── DTOs/
│   ├── MonorepoInfo.php
│   ├── DetectedApp.php              # + type field
│   ├── DetectedDatabase.php         # readonly + withMergedConsumers()
│   ├── DetectedService.php
│   ├── DetectedEnvVariable.php
│   ├── DependencyAnalysisResult.php
│   └── AnalysisResult.php
└── Exceptions/
    ├── RepositoryAnalysisException.php
    └── ProvisioningException.php

app/Http/Controllers/Api/
└── GitAnalyzerController.php        # API контроллер

resources/js/components/features/
└── MonorepoAnalyzer.tsx             # React компонент

database/migrations/
└── xxxx_add_monorepo_group_to_applications.php

tests/Unit/Services/RepositoryAnalyzer/
├── MonorepoDetectorTest.php
├── AppDetectorTest.php
└── DependencyAnalyzerTest.php
```

---

## Связанные документы

- [../railway-like-experience.md](../railway-like-experience.md) — общий план Railway-like UX
- [../auto-provisioning-architecture.md](../auto-provisioning-architecture.md) — автоматическое создание VPS
