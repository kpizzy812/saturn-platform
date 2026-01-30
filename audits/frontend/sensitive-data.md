# Frontend Sensitive Data Exposure Audit

**Приоритет:** 🔴 Critical
**Статус:** [🔍] В процессе - найдены критические проблемы

---

## Обзор

Проверка на утечку чувствительных данных на фронтенде.

### Ключевые области для проверки:

- Browser storage (localStorage, sessionStorage)
- Console logging
- Network requests/responses
- DOM/page content

---

## Гипотезы для проверки

### Browser Storage

- [✅] **SENS-001**: Проверить localStorage - нет tokens/passwords
  - ✅ Хранят только UI preferences: тема, позиции canvas, autoscroll, query history
  - ⚠️ Query history (Databases/Query.tsx) может содержать sensitive SQL
- [✅] **SENS-002**: Проверить sessionStorage - нет sensitive data
  - ✅ Не используется для sensitive данных
- [ ] **SENS-003**: Проверить IndexedDB usage
- [ ] **SENS-004**: Проверить cookies - secure/httpOnly flags

### Console & Debugging

- [✅] **SENS-005**: Поиск console.log с sensitive data
  - ✅ Логируются только debug messages о инициализации (Echo, Sentry)
  - Нет sensitive data в console.log
- [ ] **SENS-006**: Проверить что debug mode отключен в production
- [ ] **SENS-007**: Проверить source maps в production

### Inertia.js Props

- [✅] **SENS-008**: Проверить shared props - нет secrets
  - ✅ `HandleInertiaRequests.php` передаёт только: id, name, email, avatar, permissions
  - ✅ `two_factor_secret` НЕ передаётся, только `two_factor_enabled: boolean`
- [🔴] **SENS-009**: Проверить page props - нет лишних данных
  - **КРИТИЧЕСКОЕ**: `ApplicationController.php:519` передаёт `value` без проверки!
  - Все env var values отправляются в Inertia props
  - Видны в "View Page Source" любому с доступом к странице
- [✅] **SENS-010**: Проверить что passwords не передаются обратно
  - ✅ В shared props нет паролей

### Environment Variables Display

- [⚠️] **SENS-011**: Проверить masked display env variables
  - ⚠️ На UI используется `type="password"` с toggle reveal
  - НО значения УЖЕ присутствуют в HTML source!
  - Это только visual masking, не security
- [ ] **SENS-012**: Проверить copy to clipboard - warning
- [🔴] **SENS-013**: Проверить export functionality
  - **КРИТИЧЕСКОЕ**: Export в plain text без warning!
  - `Variables.tsx:94-109` - все values экспортируются в файл
  - Нет предупреждения о sensitive data

### Credentials Display

- [ ] **SENS-014**: Database passwords - masked by default
- [ ] **SENS-015**: SSH keys - partial display
- [ ] **SENS-016**: API tokens - masked/hidden
- [ ] **SENS-017**: S3 credentials - protected display

### Logs Display

- [ ] **SENS-018**: Deployment logs - env vars masking
- [ ] **SENS-019**: Server logs - credential filtering
- [ ] **SENS-020**: Application logs - sensitive data filtering

### URL Exposure

- [ ] **SENS-021**: Проверить что sensitive data не в URL params
- [ ] **SENS-022**: Проверить browser history - нет secrets
- [ ] **SENS-023**: Проверить referrer headers

### Third-Party Scripts

- [ ] **SENS-024**: Проверить analytics - нет sensitive data tracking
- [ ] **SENS-025**: Проверить Sentry - error data filtering
- [ ] **SENS-026**: Проверить external scripts - limited access

### Caching

- [ ] **SENS-027**: Проверить HTTP cache headers для sensitive pages
- [ ] **SENS-028**: Проверить service worker caching

### Copy/Paste Security

- [ ] **SENS-029**: Проверить clipboard operations с secrets
- [ ] **SENS-030**: Проверить drag-drop sensitive data

---

## Findings

### Критические

#### SENS-009: Env var values передаются в Inertia props без фильтрации

**Severity: CRITICAL**
**Файл:** `app/Http/Controllers/Inertia/ApplicationController.php:512-528`

Все env variable values передаются на фронтенд:
```php
$variables = $application->environment_variables()
    ->get()
    ->map(function ($variable) {
        return [
            'value' => $variable->value,  // <-- PLAIN TEXT SECRET!
            // ...
        ];
    });
```

**Impact:**
- Все секреты видны в HTML page source (View Source)
- `is_shown_once` флаг НЕ проверяется при передаче
- Browser extensions, shoulder surfing, cache могут leak secrets

**Сравнение:** В `routes/web.php:1517` проверяется `is_shown_once`:
```php
$value = $envVar->is_shown_once ? '********' : ($envVar->value ?? '');
```
Но в Inertia controller - нет!

#### SENS-013: Export env vars в plain text без warning

**Severity: HIGH**
**Файл:** `resources/js/pages/Applications/Settings/Variables.tsx:94-109`

```javascript
const handleExport = () => {
    const content = variables
        .map(v => `${v.key}=${v.value}`)  // Plain text export
        .join('\n');
    // ... download as .txt file
};
```

Нет предупреждения о sensitive data перед экспортом.

### Важные

#### SENS-001: Query history может содержать sensitive SQL

**Severity: MEDIUM**
**Файл:** `resources/js/pages/Databases/Query.tsx:54-80`

SQL query history сохраняется в localStorage. Если пользователь выполняет запросы с sensitive данными (passwords в WHERE clause), они сохраняются локально.

### Низкий приоритет

#### SENS-011: Visual masking не равно security

На UI env vars отображаются с `type="password"`, но это только визуальное скрытие. Данные уже присутствуют в HTML source.

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| SENS-009 | Не передавать value если is_shown_once=true | ⏳ **СРОЧНО** | - |
| SENS-009 | Использовать отдельный API endpoint для reveal | ⏳ Pending | - |
| SENS-013 | Добавить warning modal перед export | ⏳ Pending | - |

---

## Заметки аудитора

**Дата проверки:** 2026-01-30

Рекомендуемое исправление для SENS-009:

```php
// ApplicationController.php
$variables = $application->environment_variables()
    ->get()
    ->map(function ($variable) {
        return [
            'id' => $variable->id,
            'key' => $variable->key,
            // Не передавать value если is_shown_once
            'value' => $variable->is_shown_once ? null : $variable->value,
            'is_shown_once' => $variable->is_shown_once,
            // ...
        ];
    });
```

И добавить отдельный API endpoint для получения скрытых значений:
```
GET /api/env-vars/{id}/reveal
```
С проверкой permissions и аудит логом.
