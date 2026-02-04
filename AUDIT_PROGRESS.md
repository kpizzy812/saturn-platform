# Saturn Platform Security & Bug Audit Progress

**Дата начала**: 2026-02-04
**Статус**: Фаза 1 завершена ✅
**Коммит**: b46cb23

---

## КРИТИЧЕСКИЕ ПРОБЛЕМЫ

### 1. ✅ Mass Assignment в User модели
- **Файл**: `app/Models/User.php:46`
- **Проблема**: `$guarded = []` позволяет массово назначать любые поля
- **Риск**: Эскалация привилегий через API
- **Статус**: ✅ ИСПРАВЛЕНО

### 2. ✅ Mass Assignment в моделях БД
- **Файлы**: Все Standalone***.php модели, EnvironmentVariable.php
- **Проблема**: `$guarded = []`
- **Статус**: ✅ ИСПРАВЛЕНО (8 моделей)

### 3. ✅ Command Injection в Docker командах
- **Файлы**: `app/Models/Standalone*.php` (все 8 DB моделей)
- **Проблема**: `docker volume rm -f $storage->name` без экранирования
- **Риск**: RCE через специальные символы в имени
- **Статус**: ✅ ИСПРАВЛЕНО (escapeshellarg + path validation)

### 4. ✅ Race Condition в логах деплоя
- **Файл**: `app/Models/ApplicationDeploymentQueue.php:204-220`
- **Проблема**: Lost Update без pessimistic lock
- **Статус**: ✅ ИСПРАВЛЕНО (lockForUpdate)

### 5. ✅ NPE в конструкторе ApplicationDeploymentJob
- **Файл**: `app/Jobs/ApplicationDeploymentJob.php:204-207`
- **Проблема**: Нет null check после find()
- **Статус**: ✅ ИСПРАВЛЕНО (null checks + logging)

### 6. ✅ Нет retry logic
- **Файл**: `app/Jobs/ApplicationDeploymentJob.php:61`
- **Проблема**: `$tries = 1`
- **Статус**: ✅ ИСПРАВЛЕНО ($tries = 3 + backoff)

### 7. ✅ Multi-tenancy утечка через ResourceLink
- **Файл**: `app/Models/Application.php:2138-2200`, `app/Models/ResourceLink.php`
- **Проблема**: Нет проверки team_id
- **Статус**: ✅ ИСПРАВЛЕНО (team validation + saving hook)

---

## ВЫСОКИЕ РИСКИ

### 8. ⏳ Memory leak в JSON логах
- **Файл**: `app/Models/ApplicationDeploymentQueue.php:208`
- **Проблема**: O(N²) при добавлении логов
- **Статус**: В ОЧЕРЕДИ (требует изменение архитектуры)

### 9. ✅ SSH timeout conflicts
- **Файл**: `config/constants.php:62-74`
- **Проблема**: mux_persist_time > mux_max_age
- **Статус**: ✅ ИСПРАВЛЕНО (swapped values)

### 10. ✅ Stale cache - Project model security
- **Файл**: `app/Models/Project.php`
- **Проблема**: $guarded = []
- **Статус**: ✅ ИСПРАВЛЕНО ($fillable)

### 11. ✅ Rate limiting на AI Chat
- **Файл**: `app/Services/AI/Chat/CommandExecutor.php`
- **Проблема**: Нет rate limiting на deploy/delete/restart/stop
- **Статус**: ✅ ИСПРАВЛЕНО (RateLimiter добавлен)

---

## СРЕДНИЕ РИСКИ

### 12. ⏳ ILIKE injection в CommandExecutor
- **Файлы**: `app/Services/AI/Chat/CommandExecutor.php:456,1616`
- **Статус**: В ОЧЕРЕДИ

### 13. ⏳ Upload octet-stream разрешен
- **Файл**: `app/Http/Controllers/UploadController.php:51`
- **Статус**: В ОЧЕРЕДИ

### 14. ⏳ Preview Deployments не реализованы
- **Файл**: `routes/api.php:336-373`
- **Статус**: ДОКУМЕНТИРОВАНО (8 TODO endpoints)

---

## АРХИТЕКТУРНЫЕ ПРОБЛЕМЫ (TECH DEBT)

### God Classes (>1000 строк)
- [ ] `Application.php` - 2275 строк
- [ ] `CommandExecutor.php` - 2272 строк
- [ ] `Service.php` - 1785 строк
- [ ] `ApplicationDeploymentJob.php` - 1211 строк

### Code Duplication
- [ ] `ApplicationComposeParser.php` + `ServiceComposeParser.php` = 3020 строк дублирования

### N+1 Queries
- [ ] `Service.php:85-100` - множество ->get() без eager loading
- [ ] `routes/web/misc.php` - nested foreach loops

---

## CHANGELOG

### 2026-02-04
- Начат аудит безопасности
- Выявлено 14+ критических и высоких проблем
- ✅ Исправлено 7 критических проблем:
  1. Mass Assignment в User.php и 9 других моделях
  2. Command Injection в 8 DB моделях (escapeshellarg + path validation)
  3. Race Condition в логах деплоя (pessimistic lock)
  4. NPE в ApplicationDeploymentJob (null checks)
  5. Отсутствие retry logic ($tries = 3 + backoff)
  6. SSH timeout conflicts (swapped mux values)
- Запушено в dev: b46cb23

### 2026-02-04 (Фаза 2)
- ✅ Multi-tenancy fix в ResourceLink (team validation)
- ✅ Rate limiting в AI Chat CommandExecutor
- ✅ Project model $fillable вместо $guarded
- ✅ ResourceLink model $fillable + team validation hook

