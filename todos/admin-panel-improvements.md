# Admin Panel Improvements & Missing Features

**Дата анализа:** 2026-01-30
**Последнее обновление:** 2026-01-30
**Статус:** In Progress
**Приоритет:** High (критические проблемы решены)

---

## ✅ РЕШЁННЫЕ КРИТИЧЕСКИЕ ПРОБЛЕМЫ

### 1. User Management - Impersonate/Suspend ✅ DONE
**Статус:** Полностью реализовано

**Что сделано:**
- [x] Миграция для `status`, `suspended_at`, `suspended_by`, `suspension_reason` создана
- [x] Роут `POST /admin/users/{id}/impersonate` реализован в `routes/web/admin.php:212`
- [x] Роут `POST /admin/users/{id}/toggle-suspension` реализован в `routes/web/admin.php:254`
- [x] AuditLog логирование добавлено
- [x] Проверка прав superadmin добавлена
- [x] Методы модели User: `isSuspended()`, `suspend()`, `activate()` реализованы

---

### 2. Custom Roles ✅ DONE (Вариант 1)
**Статус:** Закомментировано, добавлен notice

**Что сделано:**
- [x] Кнопка "Create Custom Role" закомментирована
- [x] Добавлен notice "Coming Soon in Pro Plan"
- [x] Просмотр встроенных ролей работает

---

### 3. Metrics Dashboard ✅ DONE
**Статус:** Страница создана

**Что сделано:**
- [x] Файл `Admin/Metrics/Index.tsx` создан (12994 bytes)
- [x] Роут `/superadmin/metrics` работает
- [x] Метрики: total resources, deployment stats, графики

---

### 4. User Status Management ✅ DONE
**Статус:** Полностью реализовано

**Что сделано:**
- [x] Миграция `add_status_and_suspension_to_users_table` создана
- [x] Поля: `status`, `suspended_at`, `suspended_by`, `suspension_reason`
- [x] Роут `/admin/users` возвращает реальный статус
- [x] Логика определения pending (email not verified)

---

### 5. Security Settings ✅ DONE
**Статус:** Полностью реализовано

**Что сделано:**
- [x] Active Sessions - реализовано с revoke
- [x] Login History - реализовано
- [x] IP Allowlist - CRUD API реализован
- [x] Security Notifications - настройки реализованы
- [x] Все роуты в `routes/web/settings.php:166-522`

---

### 6. Settings Pages ✅ PARTIALLY DONE
**Статус:** Основные страницы работают

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
---

## 🟡 ВЫСОКИЙ ПРИОРИТЕТ (Следующие задачи)

### 7. User Management Improvements

**User Search & Filters** ✅ DONE
- [x] Search по email, имени - реализовано в `admin.php:128-133`
- [x] Фильтр по статусу (active, suspended, pending) - реализовано
- [x] Сортировка по полям - реализовано
- [ ] Фильтр по роли (superadmin, regular) - TODO

**Bulk Operations** - TODO
- [ ] Checkbox для выбора пользователей
- [ ] Bulk suspend/activate
- [ ] Bulk delete (с подтверждением)
- [ ] Bulk export to CSV

**User Activity Tracking** - PARTIAL
- [x] Поле `last_login_at` в users - есть в модели
- [ ] Middleware для обновления при логине
- [x] Показывать в Admin/Users/Index - показывается
- [ ] Показывать last activity в Admin/Users/Show

**Force Password Reset** - TODO
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
