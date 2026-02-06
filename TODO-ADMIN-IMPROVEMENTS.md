# Saturn Admin Panel — Improvements Plan

## CRITICAL

- [x] **1. Middleware fix** — Добавить `is.superadmin` middleware к admin routes (`routes/web/admin.php`)

## HIGH Priority

- [x] **2. Login History page** — `/admin/login-history`: таблица логинов (success/failed), фильтры по user/status/IP/date, suspicious activity badge. Route + React page.

- [x] **3. Webhook Delivery Logs** — `/admin/webhook-deliveries`: таблица доставок webhook, status codes, response times, фильтры по team/event/status. Route + React page.

- [x] **4. Dashboard improvements** — Deployment success rate (24h/7d), active vs offline servers, queue health (pending/failed), total databases/services counts.

## MEDIUM Priority

- [x] **5. Platform Roles UI** — Dropdown для смены `platform_role` (owner/admin/member) на User show page. Route endpoint + frontend.

- [x] **6. Settings Export/Import** — Export настроек в JSON, Import из JSON файла. 2 новых route endpoints + кнопки в Settings page.

- [x] **7. Scheduled Tasks overview** — `/admin/scheduled-tasks`: все cron-задачи по всем командам, последние executions, status. Route + React page + sidebar link.

## LOW Priority

- [x] **8. Docker Cleanup History** — `/admin/docker-cleanups`: история cleanup executions по серверам, freed space. Route + React page.

- [x] **9. SSL Certificates overview** — `/admin/ssl-certificates`: все SSL сертификаты, expiration dates, status. Route + React page + sidebar link.

- [x] **10. Resource Transfer History** — `/admin/transfers`: аудит лог перемещений ресурсов между командами. Route + React page.

## Files to modify/create

### Existing files to modify:
- `routes/web/admin.php` — middleware fix + new route includes
- `routes/web/admin/dashboard.php` — improved stats
- `routes/web/admin/users.php` — platform role endpoint
- `resources/js/pages/Admin/Index.tsx` — dashboard improvements
- `resources/js/pages/Admin/Users/Show.tsx` — role dropdown
- `resources/js/pages/Admin/Settings/Index.tsx` — export/import buttons
- `resources/js/layouts/AdminLayout.tsx` — new sidebar links

### New route files:
- `routes/web/admin/login-history.php`
- `routes/web/admin/webhook-deliveries.php`
- `routes/web/admin/scheduled-tasks.php`
- `routes/web/admin/docker-cleanups.php`
- `routes/web/admin/ssl-certificates.php`
- `routes/web/admin/transfers.php`

### New React pages:
- `resources/js/pages/Admin/LoginHistory/Index.tsx`
- `resources/js/pages/Admin/WebhookDeliveries/Index.tsx`
- `resources/js/pages/Admin/ScheduledTasks/Index.tsx`
- `resources/js/pages/Admin/DockerCleanups/Index.tsx`
- `resources/js/pages/Admin/SslCertificates/Index.tsx`
- `resources/js/pages/Admin/Transfers/Index.tsx`

### New test files:
- `tests/Unit/Admin/AdminMiddlewareTest.php`
- `tests/Unit/Admin/LoginHistoryPageTest.php`
- `tests/Unit/Admin/DashboardImprovementsTest.php`
