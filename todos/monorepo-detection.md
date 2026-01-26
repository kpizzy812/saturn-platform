# Monorepo Auto-Detection & Multi-App Deployment

**Issue:** https://github.com/kpizzy812/saturn-platform/issues/1
**Priority:** High (улучшение UX для современных проектов)

## Обзор

Автоматическое определение монорепо и создание нескольких приложений из одного Git-репозитория.

---

## Phase 1: Детекция монорепо

### Задачи

- [ ] **1.1** Создать сервис `MonorepoDetector`
  - Файл: `app/Services/MonorepoDetector.php`
  - Определение типа монорепо по маркерам:
    - `turbo.json` → Turborepo
    - `pnpm-workspace.yaml` → pnpm workspaces
    - `lerna.json` → Lerna
    - `nx.json` → Nx
    - `rush.json` → Rush
  - Парсинг конфигов для получения списка apps/packages

- [ ] **1.2** Метод `detectApps()` - поиск приложений
  ```php
  // Возвращает массив найденных приложений
  [
    ['name' => 'api', 'path' => 'apps/api', 'type' => 'nestjs'],
    ['name' => 'web', 'path' => 'apps/web', 'type' => 'nextjs'],
  ]
  ```

- [ ] **1.3** Определение типа приложения по package.json
  - NestJS: `@nestjs/core` в dependencies
  - Next.js: `next` в dependencies
  - Vite/React: `vite` + `react`
  - Express: `express`
  - и т.д.

### Файлы для изменения
- `app/Services/MonorepoDetector.php` (новый)
- `app/Jobs/ApplicationDeploymentJob.php` (вызов детекции)

---

## Phase 2: UI выбора приложений

### Задачи

- [ ] **2.1** API endpoint для детекции
  - `POST /api/v1/git/detect-monorepo`
  - Принимает: `{ url, branch, private_key_id }`
  - Возвращает: `{ is_monorepo, type, apps: [...] }`

- [ ] **2.2** Компонент выбора приложений
  - Файл: `resources/js/components/features/MonorepoAppSelector.tsx`
  - Показывает список найденных apps с чекбоксами
  - Предпросмотр типа каждого app (иконка NestJS/Next.js/etc)

- [ ] **2.3** Интеграция в Create Application flow
  - После ввода Git URL → вызов detect-monorepo
  - Если монорепо → показать MonorepoAppSelector
  - Пользователь выбирает какие apps деплоить

- [ ] **2.4** Bulk create applications
  - Создание нескольких Application записей
  - Автоматическая настройка Base Directory
  - Связь между apps (monorepo_group_id)

### Файлы для изменения
- `app/Http/Controllers/Api/GitController.php` (новый endpoint)
- `resources/js/components/features/MonorepoAppSelector.tsx` (новый)
- `resources/js/pages/Applications/Create.tsx` (интеграция)
- `database/migrations/xxx_add_monorepo_fields.php` (новая миграция)

---

## Phase 3: Связанные деплои

### Задачи

- [ ] **3.1** Поле `monorepo_group_id` в applications
  - Миграция для добавления поля
  - Связь приложений из одного монорепо

- [ ] **3.2** Опция "Deploy all on push"
  - При push в репо → деплой всех связанных apps
  - Настройка в UI (checkbox)

- [ ] **3.3** Shared Environment Variables
  - Переменные на уровне monorepo group
  - Автоматическое наследование во все apps

- [ ] **3.4** Canvas улучшения
  - Визуальная группировка apps из одного монорепо
  - Общая рамка/контейнер на canvas

### Файлы для изменения
- `database/migrations/xxx_add_monorepo_group.php`
- `app/Models/Application.php` (связи)
- `app/Jobs/ApplicationDeploymentJob.php` (групповой деплой)
- `resources/js/components/features/canvas/ProjectCanvas.tsx`

---

## Phase 4: Умный Build

### Задачи

- [ ] **4.1** Определение изменённых apps
  - При push → git diff для определения какие apps изменились
  - Деплоить только изменённые

- [ ] **4.2** Автогенерация nixpacks.toml
  - На основе типа приложения
  - Правильные onlyIncludeFiles для каждого типа

- [ ] **4.3** Shared dependencies caching
  - Кэширование node_modules на уровне монорепо
  - Переиспользование между apps

---

## Оценка сложности

| Phase | Сложность | Приоритет |
|-------|-----------|-----------|
| 1     | Medium    | P0        |
| 2     | High      | P0        |
| 3     | Medium    | P1        |
| 4     | High      | P2        |

---

## Тестирование

- [ ] Unit тесты для MonorepoDetector
- [ ] Feature тесты для API endpoint
- [ ] E2E тест: создание apps из turborepo
- [ ] E2E тест: групповой деплой

---

## Примеры монорепо для тестирования

1. **minizapier** - Turborepo + pnpm (apps/api + apps/web)
2. Создать тестовый репо с Lerna
3. Создать тестовый репо с Nx
