# Рефакторинг крупных файлов кодовой базы

Анализ от 2026-01-25. Файлы отсортированы по количеству строк кода.

---

## 🔴 КРИТИЧЕСКИЕ (4000+ строк)

### 1. routes/web.php — ✅ ГОТОВО (было 4,385 → стало 1,209 строк, -72%)
Монолитный файл маршрутов разделён на доменные модули.

- [x] Выделены маршруты в отдельные файлы:
  - [x] `routes/web/servers.php` — 518 строк (36 маршрутов)
  - [x] `routes/web/projects.php` — 196 строк (16 маршрутов)
  - [x] `routes/web/services.php` — 263 строки (19 маршрутов)
  - [x] `routes/web/databases.php` — 423 строки (22 маршрута)
  - [x] `routes/web/applications.php` — 639 строк (21 маршрут)
  - [x] `routes/web/admin.php` — 381 строка (11 маршрутов)
  - [x] `routes/web/settings.php` — 870 строк (77 маршрутов)
- [x] Подключены через `require __DIR__.'/web/filename.php'`
- [x] Все 557 маршрутов работают после рефакторинга

### 2. ApplicationDeploymentJob.php — ✅ ГОТОВО (было 4,191 → стало 1,168 строк, -72%)
Главный Job деплоя разделён на 17 traits по функциональности.

- [x] Выделены traits в `app/Traits/Deployment/`:
  - [x] `HandlesBuildSecrets.php` — управление Docker build secrets (172 строки)
  - [x] `HandlesBuildtimeEnvGeneration.php` — генерация build-time переменных (234 строки)
  - [x] `HandlesComposeFileGeneration.php` — генерация docker-compose (268 строк)
  - [x] `HandlesContainerOperations.php` — операции с контейнерами (126 строк)
  - [x] `HandlesDeploymentCommands.php` — pre/post deployment команды (90 строк)
  - [x] `HandlesDeploymentConfiguration.php` — BuildKit detection, config writing (158 строк)
  - [x] `HandlesDeploymentStatus.php` — управление статусом деплоя (142 строки)
  - [x] `HandlesDockerComposeBuildpack.php` — Docker Compose buildpack (286 строк)
  - [x] `HandlesDockerfileModification.php` — модификация Dockerfile (421 строка)
  - [x] `HandlesGitOperations.php` — git clone/checkout (174 строки)
  - [x] `HandlesHealthCheck.php` — health check и rolling update (177 строк)
  - [x] `HandlesImageBuilding.php` — сборка Docker образов (315 строк)
  - [x] `HandlesImageRegistry.php` — push/pull образов (150 строк)
  - [x] `HandlesNixpacksBuildpack.php` — Nixpacks buildpack (285 строк)
  - [x] `HandlesRuntimeEnvGeneration.php` — runtime переменные (327 строк)
  - [x] `HandlesSaturnEnvVariables.php` — Saturn платформенные переменные (171 строка)
  - [x] `HandlesStaticBuildpack.php` — Static buildpack (86 строк)
- [x] Основной класс содержит только оркестрацию и свойства
- [x] Код прошёл Pint и PHPStan валидацию

---

## 🟠 ВЫСОКИЕ (3000-4000 строк)

### 3. ApplicationsController.php — ✅ ГОТОВО (было 3,648 → стало 841 строк, -77%)
API контроллер приложений разделён на специализированные контроллеры и Action классы.

- [x] Выделены контроллеры:
  - [x] `ApplicationEnvsController.php` — ENV методы (745 строк)
  - [x] `ApplicationActionsController.php` — start/stop/restart (287 строк)
  - [x] `ApplicationDeploymentsController.php` — deployments/rollback (167 строк)
  - [x] `ApplicationCreateController.php` — создание приложений (300 строк)
- [x] Выделены Action классы в `app/Actions/Application/`:
  - [x] `CreatePublicApplication.php` — публичный репозиторий (138 строк)
  - [x] `CreatePrivateGhAppApplication.php` — GitHub App (189 строк)
  - [x] `CreatePrivateDeployKeyApplication.php` — Deploy Key (170 строк)
  - [x] `CreateDockerfileApplication.php` — Dockerfile (117 строк)
  - [x] `CreateDockerImageApplication.php` — Docker Image (122 строк)
  - [x] `CreateDockerComposeApplication.php` — Docker Compose (137 строк)
  - [x] `Concerns/CreatesApplication.php` — общая логика (266 строк)
- [x] Маршруты обновлены в `routes/api.php`
- [x] Код прошёл Pint валидацию

### 4. bootstrap/helpers/shared.php — 3,401 строк
Глобальные хелперы, смешанная ответственность.

- [ ] Разделить по функциональности:
  - [ ] `helpers/ssh.php` — SSH операции
  - [ ] `helpers/docker.php` — Docker команды (уже есть частично)
  - [ ] `helpers/validation.php` — валидация данных
  - [ ] `helpers/formatting.php` — форматирование строк
  - [ ] `helpers/encryption.php` — шифрование
- [ ] Перенести сложную логику в Service классы
- [ ] Добавить PHPDoc комментарии

### 5. Projects/Show.tsx — 3,240 строк
Главная страница проекта, много логики в одном компоненте.

- [ ] Выделить подкомпоненты:
  - [ ] `components/Projects/ProjectHeader.tsx`
  - [ ] `components/Projects/ProjectCanvas.tsx` (если не выделен)
  - [ ] `components/Projects/ProjectSidebar.tsx`
  - [ ] `components/Projects/ResourceList.tsx`
  - [ ] `components/Projects/DeploymentPanel.tsx`
- [ ] Выделить хуки:
  - [ ] `hooks/useProjectState.ts`
  - [ ] `hooks/useProjectActions.ts`
- [ ] Использовать React.memo для оптимизации
- [ ] Проверить производительность после рефакторинга

---

## 🟡 СРЕДНИЕ (2000-3000 строк)

### 6. DatabasesController.php — 2,878 строк

- [ ] Применить Action паттерн (аналогично ApplicationsController)
- [ ] Выделить общую логику для разных типов БД в абстракции
- [ ] Убрать switch/case по типам БД — использовать полиморфизм

### 7. bootstrap/helpers/parsers.php — 2,514 строк

- [ ] Выделить парсеры в отдельные классы:
  - [ ] `Parsers/DockerComposeParser.php`
  - [ ] `Parsers/EnvironmentParser.php`
  - [ ] `Parsers/NixpacksParser.php`
- [ ] Добавить интерфейс `ParserInterface`
- [ ] Покрыть unit-тестами

### 8. Application.php (Model) — 2,118 строк

- [ ] Выделить scopes в трейты:
  - [ ] `Traits/ApplicationScopes.php`
  - [ ] `Traits/ApplicationRelations.php`
- [ ] Выделить бизнес-логику в Service классы
- [ ] Убрать методы, которые должны быть в Actions

---

## 🟢 НИЗКИЕ (1500-2000 строк)

### 9. ServicesController.php — ✅ ГОТОВО (было 1,888 → стало 736 строк)

- [x] Выделены контроллеры:
  - `ServiceHealthcheckController.php` (207 строк)
  - `ServiceEnvsController.php` (534 строк)
  - `ServiceActionsController.php` (250 строк)
- [x] Созданы Action классы:
  - `CreateOneClickServiceAction.php` (137 строк)
  - `CreateCustomServiceAction.php` (85 строк)
  - `UpdateServiceAction.php` (99 строк)

### 10. Service.php (Model) — 1,774 строк

- [ ] Выделить relations и scopes в трейты
- [ ] Вынести compose-логику в отдельный сервис

### 11. bootstrap/helpers/docker.php — 1,515 строк

- [ ] Выделить в `Services/DockerService.php`
- [ ] Использовать DTO для конфигураций

### 12. Server.php (Model) — 1,514 строк

- [ ] Выделить SSH-логику в `Services/ServerConnectionService.php`
- [ ] Выделить проверки в отдельный сервис

---

## 📋 Чеклист перед рефакторингом

Для каждого файла:
- [ ] Написать тесты покрывающие текущее поведение
- [ ] Проверить что CI проходит до изменений
- [ ] Делать маленькие PR (один файл = один PR)
- [ ] Code review обязателен
- [ ] Проверить производительность после изменений

---

## 📊 Прогресс

| Файл | Статус | Дата |
|------|--------|------|
| routes/web.php | ✅ Готово | 2026-01-25 |
| ApplicationDeploymentJob.php | ✅ Готово | 2026-01-26 |
| ApplicationsController.php | ✅ Готово | 2026-01-26 |
| shared.php | ⏳ Не начато | - |
| Projects/Show.tsx | ⏳ Не начато | - |
| DatabasesController.php | ⏳ Не начато | - |
| parsers.php | ⏳ Не начато | - |
| Application.php | ⏳ Не начато | - |
| ServicesController.php | ✅ Готово | 2026-01-25 |
| Service.php | ⏳ Не начато | - |
| docker.php | ⏳ Не начато | - |
| Server.php | ⏳ Не начато | - |

**Легенда:** ⏳ Не начато | 🔄 В процессе | ✅ Готово | ❌ Отложено
