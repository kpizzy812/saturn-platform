# План миграции Saturn Platform → Railway-style UI

## Исследование Railway (Декабрь 2025)

### Технологический стек Railway

| Компонент | Технология |
|-----------|------------|
| **Backend** | Rust (CLI), Go (Railpack), PostgreSQL, Redis |
| **Frontend** | React, TypeScript, Tailwind CSS |
| **API** | GraphQL (`https://backboard.railway.com/graphql/v2`) |
| **Транспиляция** | SWC (не Babel) |
| **Шрифт** | Inter |
| **Компоненты** | 400+ кастомных |
| **Canvas** | Вероятно React Flow (xyflow) |

### Дизайн-система Railway

#### Цвета (HSL)

```css
/* Dark Theme (Default) */
--background: hsl(250, 24%, 9%);        /* #13111C - основной фон */
--background-secondary: hsl(250, 21%, 11%);  /* вторичный фон */
--foreground: hsl(0, 0%, 100%);         /* белый текст */

/* Light Theme */
--background: hsl(0, 0%, 100%);
--background-secondary: hsl(0, 0%, 98%);
--foreground: hsl(250, 24%, 9%);

/* Accent Colors */
--green-500: #10B981;  /* primary action */
--red-500: #EF4444;    /* danger/error */
--yellow-500: #F59E0B; /* warning */
--blue-500: #3B82F6;   /* info/links */
```

#### Типографика

```css
font-family: 'Inter', 'Inter Fallback', sans-serif;
/* Размеры: 12px, 14px, 16px, 18px, 24px, 32px */
```

---

## Что у нас есть (Saturn Platform)

| Компонент | Статус | Нужно |
|-----------|--------|-------|
| Laravel Backend | ✅ Готов | Сохранить |
| PostgreSQL + Redis | ✅ Готов | Сохранить |
| Модели данных | ✅ Готовы | Сохранить |
| API | ⚠️ Частично | Добавить GraphQL или расширить REST |
| Livewire Frontend | ❌ Удалить | Заменить на React |
| Blade Templates | ❌ Удалить | Заменить на React |

---

## План работы

### Фаза 1: Инфраструктура (1-2 промпта)

#### 1.1 Установка React + Inertia.js
```bash
# Backend
composer require inertiajs/inertia-laravel
php artisan inertia:middleware

# Frontend
npm install @inertiajs/react react react-dom
npm install -D @types/react @types/react-dom @vitejs/plugin-react typescript
```

#### 1.2 Создание структуры проекта
```
resources/js/
├── app.tsx                 # Точка входа
├── types/                  # TypeScript типы
│   ├── models.ts          # Server, Application, Project...
│   └── index.ts
├── components/            # UI компоненты
│   ├── ui/               # Базовые (Button, Input, Modal)
│   ├── layout/           # Layout компоненты
│   └── features/         # Фичевые компоненты
├── pages/                # Inertia страницы
│   ├── Dashboard/
│   ├── Projects/
│   ├── Servers/
│   └── Settings/
├── hooks/                # React hooks
├── lib/                  # Утилиты
└── styles/              # CSS/Tailwind
```

#### 1.3 Настройка Tailwind с Railway цветами
```js
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        background: 'hsl(250, 24%, 9%)',
        'background-secondary': 'hsl(250, 21%, 11%)',
        foreground: 'hsl(0, 0%, 100%)',
        primary: '#10B981',
        danger: '#EF4444',
        warning: '#F59E0B',
        info: '#3B82F6',
      },
      fontFamily: {
        sans: ['Inter', 'Inter Fallback', 'sans-serif'],
      },
    },
  },
}
```

---

### Фаза 2: Дизайн-система (1-2 промпта)

#### 2.1 Базовые UI компоненты (20 шт)

| Компонент | Приоритет | Сложность |
|-----------|-----------|-----------|
| Button | 🔴 Высокий | Низкая |
| Input | 🔴 Высокий | Низкая |
| Select | 🔴 Высокий | Средняя |
| Checkbox | 🔴 Высокий | Низкая |
| Modal | 🔴 Высокий | Средняя |
| Dropdown | 🔴 Высокий | Средняя |
| Card | 🔴 Высокий | Низкая |
| Badge | 🟡 Средний | Низкая |
| Toast | 🟡 Средний | Средняя |
| Tooltip | 🟡 Средний | Низкая |
| Tabs | 🟡 Средний | Средняя |
| Table | 🟡 Средний | Средняя |
| Avatar | 🟢 Низкий | Низкая |
| Skeleton | 🟢 Низкий | Низкая |
| Progress | 🟢 Низкий | Низкая |
| Spinner | 🔴 Высокий | Низкая |
| Alert | 🟡 Средний | Низкая |
| Breadcrumb | 🟢 Низкий | Низкая |
| CommandPalette | 🔴 Высокий | Высокая |
| Sidebar | 🔴 Высокий | Средняя |

#### 2.2 Layout компоненты (5 шт)

| Компонент | Описание |
|-----------|----------|
| AppLayout | Основной layout с sidebar |
| AuthLayout | Layout для логина/регистрации |
| SettingsLayout | Layout для страниц настроек |
| ProjectLayout | Layout для project canvas |
| EmptyState | Заглушка для пустых списков |

---

### Фаза 3: Core Pages (2-3 промпта)

#### 3.1 Аутентификация
- [ ] Login page
- [ ] Register page
- [ ] Forgot password
- [ ] Two-factor auth

#### 3.2 Dashboard
- [ ] Project list (grid view)
- [ ] Quick actions
- [ ] Usage stats widget
- [ ] Recent deployments

#### 3.3 Projects
- [ ] Project list
- [ ] Create project modal
- [ ] Project settings

#### 3.4 Servers
- [ ] Server list
- [ ] Server details
- [ ] Add server wizard
- [ ] Server monitoring

---

### Фаза 4: Project Canvas (2-3 промпта) ⚠️ СЛОЖНО

#### 4.1 Canvas инфраструктура
```bash
npm install @xyflow/react
```

#### 4.2 Node типы для canvas
- [ ] ServiceNode (Application)
- [ ] DatabaseNode (PostgreSQL, MySQL, etc.)
- [ ] RedisNode
- [ ] VolumeNode
- [ ] ConnectionEdge (линии между нодами)

#### 4.3 Canvas функционал
- [ ] Drag & drop нод
- [ ] Zoom & pan
- [ ] Auto-layout
- [ ] Context menu (right-click)
- [ ] Node status indicators
- [ ] Connection lines

---

### Фаза 5: Application Management (1-2 промпта)

#### 5.1 Application Pages
- [ ] Application overview
- [ ] Deployment history
- [ ] Environment variables
- [ ] Domains/networking
- [ ] Logs viewer
- [ ] Metrics dashboard

#### 5.2 Deployment Flow
- [ ] Deploy button
- [ ] Build logs (real-time)
- [ ] Rollback functionality
- [ ] Deployment status

---

### Фаза 6: Database & Services (1 промпт)

- [ ] Database list
- [ ] Database details
- [ ] Connection info
- [ ] Backups management
- [ ] Redis/MongoDB/MySQL specific UI

---

### Фаза 7: Settings & Team (1 промпт)

#### 7.1 Settings pages
- [ ] Profile settings
- [ ] Team management
- [ ] Billing (если нужно)
- [ ] API tokens
- [ ] Webhooks

#### 7.2 Team features
- [ ] Invite members
- [ ] Role management
- [ ] Audit log

---

### Фаза 8: Polish & Real-time (1 промпт)

- [ ] WebSocket для real-time updates
- [ ] Animations (Framer Motion)
- [ ] Loading states
- [ ] Error handling
- [ ] Mobile responsive
- [ ] Command Palette (⌘K)

---

## Сводка по промптам

| Фаза | Описание | Промптов |
|------|----------|----------|
| 1 | Инфраструктура (React + Inertia) | 1-2 |
| 2 | Дизайн-система (компоненты) | 1-2 |
| 3 | Core Pages (Dashboard, Projects, Servers) | 2-3 |
| 4 | Project Canvas (React Flow) | 2-3 |
| 5 | Application Management | 1-2 |
| 6 | Database & Services | 1 |
| 7 | Settings & Team | 1 |
| 8 | Polish & Real-time | 1 |
| **ИТОГО** | | **10-15 промптов** |

---

## Риски и решения

| Риск | Решение |
|------|---------|
| Canvas сложный | Использовать React Flow (проверенная библиотека) |
| Много компонентов | Использовать shadcn/ui как базу |
| Real-time логи | Laravel Echo + Pusher/Soketi |
| Большой объём работы | Параллельные агенты |

---

## Начать с...

**Рекомендуемый порядок для первого промпта:**

1. ✅ Установить React + Inertia.js + TypeScript
2. ✅ Настроить Tailwind с Railway цветами
3. ✅ Создать базовые компоненты (Button, Input, Card)
4. ✅ Создать AppLayout с sidebar
5. ✅ Создать Dashboard page

После этого будет работающий прототип с Railway-style UI.

---

## Команда для старта

```bash
# Когда будешь готов, скажи: "Начинаем Фазу 1"
```
