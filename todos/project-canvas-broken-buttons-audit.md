# Аудит страницы Project Canvas - Неработающий функционал

## Обзор

Страница проекта с канвасом (`/projects/{uuid}`) содержит **50+ неработающих элементов**.

**Основной файл:** `resources/js/pages/Projects/Show.tsx` (2189 строк)

### Обновлённая статистика:
- 🔴 **10** критических заглушек (console.log)
- 🟠 **12** кнопок без onClick обработчиков
- 🟡 **15+** элементов в Database Tabs без функционала
- ⚫ **4** отсутствующих роута/страницы
- 🔵 **6** копок Copy без функционала
- 🟣 **4** input/toggle без сохранения на бэкенд

---

## 🔴 КРИТИЧЕСКИЕ ПРОБЛЕМЫ

### 1. Context Menu для приложений (Show.tsx:867-888)

| Действие | Строка | Текущий код | Нужный API |
|----------|--------|-------------|------------|
| Deploy | 867 | `console.log('Deploy', id)` | `POST /api/v1/applications/{uuid}/start` |
| Restart | 868 | `console.log('Restart', id)` | `POST /api/v1/applications/{uuid}/restart` |
| Stop | 869 | `console.log('Stop', id)` | `POST /api/v1/applications/{uuid}/stop` |
| Delete | 888 | `console.log('Delete', id)` | `DELETE /api/v1/applications/{uuid}` |

### 2. Context Menu для баз данных (ContextMenu.tsx:209-215)

| Действие | Строка | Текущий код |
|----------|--------|-------------|
| Create Backup | 209 | `console.log('Create backup')` |
| Restore Backup | 215 | `console.log('Restore backup')` |

### 3. CommandPalette - 6 заглушек (CommandPalette.tsx)

| Действие | Строка | Код |
|----------|--------|-----|
| Deploy | 56 | `action: () => console.log('Deploy')` |
| Restart | 65 | `action: () => console.log('Restart')` |
| View Logs | 74 | `action: () => console.log('View Logs')` |
| Add Service | 83 | `action: () => console.log('Add Service')` |
| Add Database | 91 | `action: () => console.log('Add Database')` |
| Add Template | 99 | `action: () => console.log('Add Template')` |

### 4. "Deploy Changes" = "Discard" (Show.tsx:478-491)

```tsx
// ОБЕ КНОПКИ ДЕЛАЮТ ОДИНАКОВОЕ!
<Button onClick={() => setHasStagedChanges(false)}>Discard</Button>
<Button onClick={() => setHasStagedChanges(false)}>Deploy Changes</Button>  // ❌ ДОЛЖЕН ДЕПЛОИТЬ!
```

---

## 🟠 КНОПКИ БЕЗ onClick

### 5. Cancel Deployment (Show.tsx:1035-1040)
```tsx
<DropdownItem>Cancel</DropdownItem>  // ❌ НЕТ onClick!
```
**API существует:** `POST /api/v1/deployments/{uuid}/cancel`

### 6. Create Dropdown (Show.tsx:619-677)
- GitHub Repo (строка 619)
- Docker Image (строка 632)
- Database (строка 645)
- Empty Service (строка 658)
- Template (строка 669)

### 7. Другие кнопки без onClick
| Элемент | Строка |
|---------|--------|
| "Set up locally" | 683 |
| Replicas − / + | 1491-1498 |
| Delete domain | 1386 |
| Add Custom Domain | 1390-1393 |
| Create Table | 1618-1621 |
| Create Backup | 1806 |
| Schedule backup | 1810 |
| Add env variable | 1121-1124 |

---

## 🔵 COPY КНОПКИ БЕЗ ФУНКЦИОНАЛА

| Элемент | Строка |
|---------|--------|
| Copy env variable | 1133-1135 |
| Copy URL | 1371 |
| Copy connection string | 1430 |
| Copy hostname | 1449 |
| Copy password | 1503, 1578 |

---

## 🟣 INPUT/TOGGLE БЕЗ СОХРАНЕНИЯ НА БЭКЕНД

### Toggle меняют только локальный state:
| Toggle | Строка | State |
|--------|--------|-------|
| Cron Schedule | 1509-1513 | `cronEnabled` |
| Health Check | 1545-1549 | `healthCheckEnabled` |

### Input без onChange/Save:
| Input | Строка | defaultValue |
|-------|--------|--------------|
| Cron expression | 1520-1525 | `"0 * * * *"` |
| Health endpoint | 1557-1562 | `"/health"` |
| Health timeout | 1567-1571 | `10` |
| Health interval | 1575-1579 | `30` |

---

## ⚫ ОТСУТСТВУЮЩИЕ РОУТЫ И СТРАНИЦЫ

### Несуществующие страницы:
| URL | Откуда ссылка | Статус |
|-----|---------------|--------|
| `/projects/{uuid}/settings` | Index.tsx:80, Show.tsx:409 | ❌ 404 |
| `/projects/{uuid}/edit` | Legacy redirect | ❌ 404 |

### Отсутствующие Inertia методы в ProjectController:
- `edit()` - форма редактирования
- `update()` - сохранение изменений
- `destroy()` - удаление через web
- `settings()` - страница настроек

### Отсутствующие роуты в routes/web.php:
```php
// НУЖНЫ:
Route::get('/projects/{uuid}/settings', ...)->name('projects.settings');
Route::patch('/projects/{uuid}', ...)->name('projects.update');
Route::delete('/projects/{uuid}', ...)->name('projects.destroy');
```

---

## 🟤 MOCK/DEMO ДАННЫЕ ВМЕСТО РЕАЛЬНЫХ

### LogsViewer.tsx - Fake логи
```tsx
// Строка 21-38: generateDemoLogs() - захардкоженные demo логи
// Строка 56-71: Fake streaming с Math.random()
```

### MetricsTab - Demo метрики (Show.tsx:1146)
```tsx
const cpuData = [35, 42, 38, 45, 52, 48, 55, 62, 58, 45, 40, 38];
const memoryData = [65, 68, 70, 72, 71, 74, 76, 75, 73, 72, 70, 69];
```

### Database Panels - Fake credentials
```tsx
// PostgreSQLPanel.tsx, MySQLPanel.tsx, RedisPanel.tsx
password: 'super_secret_password_123',  // HARDCODED
```

### Environments.tsx, Variables.tsx
- Используют полностью mock данные
- Не получают реальные данные с бэкенда

---

## 🟢 АНТИПАТТЕРНЫ В КОДЕ

### window объект для zoom (ProjectCanvas.tsx:100-103)
```tsx
(window as any).__projectCanvasZoomIn = handleZoomIn;
(window as any).__projectCanvasZoomOut = handleZoomOut;
```
**Проблема:** Глобальные переменные - антипаттерн. Использовать refs или callbacks.

### selectedEnv никогда не меняется (Show.tsx:121)
```tsx
const [selectedEnv] = useState<Environment | null>(...);
// Нет setSelectedEnv - dropdown в header декоративный
```

---

## ✅ РАБОТАЮЩИЙ ФУНКЦИОНАЛ

| Действие | Статус |
|----------|--------|
| View Logs (открытие модала) | ✅ |
| Open Settings (правая панель) | ✅ |
| Copy Service ID | ✅ |
| Open URL | ✅ |
| Canvas zoom/pan | ✅ |
| Node selection | ✅ |
| Undo/Redo | ✅ |

---

## API ENDPOINTS (Backend готов)

### Приложения:
- `POST /api/v1/applications/{uuid}/start`
- `POST /api/v1/applications/{uuid}/stop`
- `POST /api/v1/applications/{uuid}/restart`
- `DELETE /api/v1/applications/{uuid}`
- `POST /api/v1/deployments/{uuid}/cancel`

### Базы данных:
- `POST /api/v1/databases/{uuid}/start`
- `POST /api/v1/databases/{uuid}/stop`
- `POST /api/v1/databases/{uuid}/restart`
- `DELETE /api/v1/databases/{uuid}`

### Проекты:
- `PATCH /api/v1/projects/{uuid}` - обновление
- `DELETE /api/v1/projects/{uuid}` - удаление

---

## ПЛАН ИСПРАВЛЕНИЯ

### Фаза 1: Критические заглушки
1. Заменить console.log на API вызовы в Show.tsx (Deploy, Restart, Stop, Delete)
2. Исправить "Deploy Changes" кнопку
3. Реализовать CommandPalette actions

### Фаза 2: Кнопки без обработчиков
4. Cancel deployment
5. Create Dropdown items
6. Replicas ±
7. Domain management

### Фаза 3: Отсутствующие страницы
8. Создать Projects/Settings.tsx
9. Добавить методы в ProjectController
10. Добавить роуты в web.php

### Фаза 4: Input/Toggle сохранение
11. Подключить Cron/Health toggles к API
12. Добавить Save кнопки для inputs

### Фаза 5: Copy функции
13. Реализовать все Copy кнопки

### Фаза 6: Реальные данные
14. LogsViewer - WebSocket для реальных логов
15. MetricsTab - реальные метрики с сервера
16. Database Panels - реальные credentials

---

## ФАЙЛЫ ДЛЯ МОДИФИКАЦИИ

| Файл | Проблемы |
|------|----------|
| `resources/js/pages/Projects/Show.tsx` | 30+ проблем |
| `resources/js/components/features/ContextMenu.tsx` | 2 заглушки |
| `resources/js/components/features/CommandPalette.tsx` | 6 заглушек |
| `resources/js/components/features/LogsViewer.tsx` | Demo data |
| `resources/js/components/features/databases/*.tsx` | Fake credentials |
| `app/Http/Controllers/Inertia/ProjectController.php` | +4 метода |
| `routes/web.php` | +3 роута |
| **СОЗДАТЬ:** `resources/js/pages/Projects/Settings.tsx` | Новая страница |

---

## ВЕРИФИКАЦИЯ

После исправлений:
1. Правый клик на приложении → все действия работают
2. Правый клик на БД → все действия работают
3. `/projects/{uuid}/settings` → страница открывается
4. Cancel deployment → деплоймент отменяется
5. Create dropdown → все пункты ведут на правильные страницы
6. Copy кнопки → копируют в буфер
7. Toggle Cron/Health → сохраняется на бэкенд
8. LogsViewer → показывает реальные логи
9. `./vendor/bin/pint && npm run build` → без ошибок
