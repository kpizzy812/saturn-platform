# Admin Panel Improvements & Missing Features

**Дата анализа:** 2026-01-30
**Статус:** In Progress
**Приоритет:** Critical

---

## 🔴 КРИТИЧЕСКИЕ НЕДОРАБОТКИ (Сломанный функционал)

### 1. User Management - Несуществующие эндпоинты ❌
**Файл:** `resources/js/pages/Admin/Users/Index.tsx:46-67`
**Статус:** Broken

**Проблема:**
- UI имеет кнопки "Impersonate User" и "Suspend User"
- Роуты не существуют в бэкенде:
  - `/admin/users/{id}/impersonate` - отсутствует
  - `/admin/users/{id}/toggle-suspension` - отсутствует

**Решение:**
- [ ] Добавить миграцию для поля `status` в таблице `users` (active, suspended, banned)
- [ ] Создать роут `POST /admin/users/{id}/impersonate` с middleware
- [ ] Создать роут `POST /admin/users/{id}/toggle-suspension`
- [ ] Добавить логирование в AuditLog для impersonation
- [ ] Добавить проверку прав (только superadmin)

---

### 2. Custom Roles - Полностью фронтенд-мок ❌
**Файл:** `resources/js/pages/Settings/Team/Roles.tsx:115-147`
**Статус:** Mock (только UI)

**Проблема:**
- Создание кастомных ролей работает только в React state
- Нет сохранения в БД
- Помечено как "Pro Feature", но вообще не имплементировано

**Решение:**
**Вариант 1: Удалить функционал (рекомендуется)**
- [ ] Убрать кнопку "Create Custom Role"
- [ ] Оставить только просмотр встроенных ролей
- [ ] Добавить note: "Custom roles - coming soon in Pro plan"

**Вариант 2: Полная реализация**
- [ ] Создать миграцию для таблицы `custom_roles`
- [ ] Создать модель `CustomRole` с permissions JSON
- [ ] Создать CRUD API endpoints
- [ ] Интегрировать с TeamPolicy
- [ ] Добавить валидацию permissions

**Выбран:** Вариант 1 (пока убрать, потом реализовать как Pro)

---

### 3. Metrics Dashboard - Отсутствующая страница ❌
**Файл:** `routes/superadmin.php:22`
**Статус:** 500 Error

**Проблема:**
- Роут `/superadmin/metrics` существует
- Файл `Admin/Metrics/Index.tsx` НЕ СУЩЕСТВУЕТ
- При переходе - ошибка 500

**Решение:**
**Вариант 1: Удалить роут**
- [ ] Удалить строку из routes/superadmin.php

**Вариант 2: Создать базовую страницу**
- [ ] Создать Admin/Metrics/Index.tsx
- [ ] Добавить метрики: total resources, deployment success rate, avg deployment time
- [ ] Графики за последние 30 дней

**Выбран:** Вариант 2 (создать базовую страницу)

---

### 4. User Status Management - Хардкод ❌
**Файл:** `routes/web/admin.php:133`
**Статус:** Incomplete

**Проблема:**
- Статус всегда хардкодится как 'active'
- В БД нет поля `status` в таблице `users`
- UI показывает badge статусов, но они фейковые

**Решение:**
- [ ] Добавить миграцию: `ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'`
- [ ] Добавить enum для статусов: active, suspended, banned, pending
- [ ] Обновить UserPolicy для проверки статуса
- [ ] Middleware для блокировки suspended пользователей
- [ ] Обновить роут `/admin/users` для возврата реального статуса

---

### 5. Security Settings - 100% Mock ❌
**Файл:** `resources/js/pages/Settings/Security.tsx`
**Статус:** Mock (только UI)

**Проблема:**
Полностью не реализованные разделы:
- Active Sessions (нет таблицы сессий, нет API)
- Login History (нет логирования входов)
- API IP Allowlist (есть в InstanceSettings, но нет CRUD API)
- Security Notifications (нет механизма отправки)

**Решение:**
**Вариант 1: Удалить страницу**
- [ ] Убрать `/settings/security` из меню
- [ ] Redirect на `/settings/account`

**Вариант 2: Реализовать базовый функционал**
- [ ] Active Sessions:
  - [ ] Создать таблицу `user_sessions`
  - [ ] Middleware для записи сессий
  - [ ] API для получения списка сессий
  - [ ] API для revoke session
- [ ] Login History:
  - [ ] Создать таблицу `login_attempts`
  - [ ] Listener на Login event
  - [ ] API для получения истории
- [ ] IP Allowlist:
  - [ ] CRUD API для user-level IP restrictions
  - [ ] Middleware для проверки IP
- [ ] Security Notifications (отложить)

**Выбран:** Вариант 1 пока (убрать страницу, добавить в будущем)

---

### 6. Settings Pages - Только UI, нет бэкенда ❌

| Страница | Статус | Действие |
|----------|--------|----------|
| **Workspace.tsx** | Mock | Убрать или реализовать API |
| **Integrations.tsx** | Mock | Убрать или реализовать CRUD |
| **Team/Activity.tsx** | Mock | Реализовать фильтры + экспорт |
| **Team/Invite.tsx** | Partial | Доработать bulk invites |
| **Notifications/** | Mock | Реализовать 6 каналов |

**Решение:**
- [ ] Провести аудит каждой страницы
- [ ] Создать отдельный TODO для каждой
- [ ] Приоритизировать по важности

---

## 🟡 ВЫСОКИЙ ПРИОРИТЕТ (Важный функционал)

### 7. User Management Improvements

**User Search & Filters**
- [ ] Добавить search по email, имени
- [ ] Фильтр по статусу (active, suspended, pending)
- [ ] Фильтр по роли (superadmin, regular)
- [ ] Фильтр по дате регистрации

**Bulk Operations**
- [ ] Checkbox для выбора пользователей
- [ ] Bulk suspend/activate
- [ ] Bulk delete (с подтверждением)
- [ ] Bulk export to CSV

**User Activity Tracking**
- [ ] Добавить поле `last_login_at` в users
- [ ] Middleware для обновления при логине
- [ ] Показывать в Admin/Users/Index
- [ ] Показывать last activity в Admin/Users/Show

**Force Password Reset**
- [ ] UI кнопка в Admin/Users/Show
- [ ] Обновить флаг `force_password_reset`
- [ ] Redirect при следующем логине

---

### 8. Server Monitoring

**Automated Health Checks**
- [ ] Создать Job: ServerHealthCheckJob
- [ ] Запускать каждые 5 минут
- [ ] Проверять: SSH connectivity, Docker status, disk usage
- [ ] Обновлять ServerSettings (is_reachable, is_usable)
- [ ] Отправлять alerts при падении

**Metrics History**
- [ ] Создать таблицу `server_metrics_history`
- [ ] Записывать метрики каждые 5 минут
- [ ] API для получения ист��рии за период
- [ ] Графики в Admin/Servers/Show

**Server Groups/Tags**
- [ ] Миграция для поля `tags` (JSON)
- [ ] UI для добавления/удаления тегов
- [ ] Фильтр по тегам в списке серверов

---

### 9. Deployment Approval Workflow

**Статус:** Модель есть, UI частичный

**Реализовать:**
- [ ] Страница Admin/Approvals/Index - список pending approvals
- [ ] Кнопки Approve/Reject с комментарием
- [ ] Уведомления пользователю о решении
- [ ] Интеграция с ApplicationDeploymentQueue
- [ ] Показывать "Pending Approval" badge в деплоях

---

### 10. Notifications Backend

**6 каналов - только UI, нужен бэкенд:**

**Discord**
- [ ] Таблица `notification_channels` (type, config, team_id)
- [ ] CRUD API для Discord webhook
- [ ] Test notification endpoint
- [ ] Integration с Events (DeploymentFinished, etc)

**Slack** (аналогично)
- [ ] CRUD API
- [ ] Test notification
- [ ] Event integration

**Telegram, Email, Webhook, Pushover** - аналогично

**Notification Rules**
- [ ] Таблица `notification_rules` (event_type, channel_id, enabled)
- [ ] UI для настройки правил
- [ ] Event dispatcher для отправки

---

### 11. Audit Log Improvements

**Текущий статус:** Базовая запись есть

**Доработать:**
- [ ] Фильтры в UI (по user, action, resource, date)
- [ ] Export to CSV/JSON
- [ ] Pagination improvements
- [ ] Поиск по description
- [ ] Группировка по resource

---

## 🟢 СРЕДНИЙ ПРИОРИТЕТ (Улучшения UX)

### 12. Application Templates
- [ ] Таблица `application_templates` (name, config JSON)
- [ ] CRUD API
- [ ] "Create from template" кнопка
- [ ] Популярные стеки: Node.js, Laravel, Django, Rails

### 13. Database Cloning
- [ ] API endpoint POST /databases/{uuid}/clone
- [ ] Job: CloneDatabaseJob
- [ ] UI кнопка в Admin/Databases/Show
- [ ] Выбор target environment

### 14. Backup Automation
- [ ] Backup verification после создания
- [ ] Automated restore testing (weekly)
- [ ] S3 integrity checks
- [ ] Backup cost estimation dashboard

### 15. Team Quotas UI
- [ ] Показывать current usage vs limits
- [ ] Progress bars для каждого quota
- [ ] Alerts при приближении к лимиту
- [ ] Admin UI для изменения limits

---

## 🔵 НИЗКИЙ ПРИОРИТЕТ (Nice to have)

### 16. Billing System
- [ ] Usage tracking (CPU hours, bandwidth, storage)
- [ ] Subscription plans (Free, Pro, Enterprise)
- [ ] Payment integration (Stripe)
- [ ] Invoice generation
- [ ] Usage alerts

### 17. Advanced Deployment Strategies
- [ ] Blue-Green deployments
- [ ] Canary deployments (% traffic)
- [ ] Rolling updates
- [ ] A/B testing support

### 18. Compliance Reports
- [ ] GDPR compliance dashboard
- [ ] Data export for users
- [ ] Audit trail export
- [ ] SOC2 reports

### 19. Branding Customization
- [ ] Instance logo upload
- [ ] Color scheme editor
- [ ] Custom email templates
- [ ] White-label option (Pro)

---

## 📊 ПЛАН РЕАЛИЗАЦИИ

### Неделя 1: Критические исправления
- [x] Анализ админки
- [ ] User impersonate/suspend endpoints
- [ ] Metrics dashboard создание или удаление роута
- [ ] User status management
- [ ] Security Settings - удалить или сделать заглушку

### Неделя 2: User Management
- [ ] Search & filters
- [ ] Bulk operations
- [ ] Activity tracking
- [ ] Force password reset UI

### Неделя 3: Server Monitoring
- [ ] Automated health checks
- [ ] Metrics history
- [ ] Server groups/tags

### Неделя 4: Notifications
- [ ] Backend API для всех каналов
- [ ] Notification rules
- [ ] Test notifications

### Неделя 5+: Остальное
- [ ] Deployment approvals
- [ ] Audit log improvements
- [ ] Application templates
- [ ] И т.д.

---

## 🎯 МЕТРИКИ УСПЕХА

- [ ] 0 broken UI elements (сейчас 6+)
- [ ] Все страницы Settings работают или удалены
- [ ] User management полнофункциональный
- [ ] Server monitoring автоматический
- [ ] Notifications работают для всех каналов

---

## 📝 ЗАМЕТКИ

- Большинство Settings страниц - mock без бэкенда
- Custom Roles - "Pro Feature" только в UI, нет реализации
- Deployment Approvals - модель есть, UI частичный
- Нужна документация: "Какие features работают, какие в разработке"

---

**Последнее обновление:** 2026-01-30
**Автор анализа:** Claude Code
**Статус:** Ready for implementation
