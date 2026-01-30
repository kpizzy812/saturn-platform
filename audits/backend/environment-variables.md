# Backend Environment Variables Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [🔍] В процессе - найдены критические проблемы

---

## Обзор

Проверка обработки environment variables - содержат чувствительные данные.

### Ключевые файлы для проверки:

- `app/Http/Controllers/Api/ApplicationEnvsController.php`
- `app/Http/Controllers/Api/ServiceEnvsController.php`
- `app/Models/EnvironmentVariable.php`
- `app/Models/SharedEnvironmentVariable.php`
- `app/Traits/EnvironmentVariableProtection.php`
- `app/Traits/EnvironmentVariableAnalyzer.php`
- `app/Policies/EnvironmentVariablePolicy.php`
- `app/Policies/SharedEnvironmentVariablePolicy.php`

---

## Гипотезы для проверки

### Storage Security

- [✅] **ENV-001**: Проверить encryption at rest для env variables
  - ✅ `'value' => 'encrypted'` в `$casts` - Laravel encryption используется
- [ ] **ENV-002**: Проверить encryption key management
- [✅] **ENV-003**: Проверить что values не хранятся в plain text
  - ✅ Используется `encrypt()`/`decrypt()` через Laravel cast
- [ ] **ENV-004**: Проверить backup encryption env vars

### Access Control

- [⚠️] **ENV-005**: Проверить `read:sensitive` ability requirement
  - ⚠️ Проверяется только для ЧТЕНИЯ через `removeSensitiveData()`
  - Файл: `ApplicationEnvsController.php:21`
  - Для записи нет такой проверки - любой с API token может создавать/изменять
- [🔴] **ENV-006**: Проверить EnvironmentVariablePolicy
  - **КРИТИЧЕСКОЕ**: ВСЕ методы возвращают `true`!
  - Файл: `app/Policies/EnvironmentVariablePolicy.php`
  - Любой авторизованный пользователь может view/update/delete ЛЮБОЙ env var
- [🔴] **ENV-007**: Проверить SharedEnvironmentVariablePolicy
  - **КРИТИЧЕСКОЕ**: Методы update/delete/restore/forceDelete возвращают `true`
  - Закомментирована проверка team_id!
  - Файл: `app/Policies/SharedEnvironmentVariablePolicy.php:38-77`
  - Только `view()` проверяет team_id (строка 23)
- [✅] **ENV-008**: Проверить team-based access к env vars
  - ✅ В контроллере: `Application::ownedByCurrentTeamAPI($teamId)`
  - Но Policy не проверяет! Защита только на уровне контроллера
- [⚠️] **ENV-009**: Проверить что нельзя получить env vars чужих приложений
  - ⚠️ В контроллере проверяется, но если обратиться напрямую к `EnvironmentVariable` - нет защиты

### API Exposure

- [✅] **ENV-010**: Проверить что env values не возвращаются по умолчанию
  - ✅ `makeHidden(['value', 'real_value'])` если нет `read:sensitive`
- [ ] **ENV-011**: Проверить pagination - нет bulk exposure
- [ ] **ENV-012**: Проверить filtering - нет timing attacks
- [ ] **ENV-013**: Проверить export функциональность

### Logging & Audit

- [ ] **ENV-014**: Проверить что env values не логируются
- [ ] **ENV-015**: Проверить audit trail для изменений env vars
- [ ] **ENV-016**: Проверить deployment logs - env vars masked

### Injection Protection

- [🔴] **ENV-017**: Проверить env variable name validation
  - **КРИТИЧЕСКОЕ**: Недостаточная валидация!
  - Файл: `EnvironmentVariable.php:243-248`
  - Только `trim()` и `replace(' ', '_')`
  - НЕТ проверки на:
    - Newlines (`\n`) - можно внедрить дополнительные env vars
    - Shell метасимволы (`$()`, backticks)
    - Специальные символы (`=`, `;`, `&`)
- [✅] **ENV-018**: Проверить env variable value sanitization
  - ✅ `escapeEnvVariables()` экранирует `\\`, `\r`, `\t`, `"`, `'`
  - ✅ Передача через base64 encoding
- [⚠️] **ENV-019**: Проверить что нельзя перезаписать system env vars
  - ⚠️ Нет защиты от перезаписи PATH, LD_PRELOAD и др.
- [🔴] **ENV-020**: Проверить injection через env var names (bash injection)
  - **КРИТИЧЕСКОЕ**: Связано с ENV-017
  - Файл: `HandlesRuntimeEnvGeneration.php:119,198`
  - `$envs->push($env->key.'='.$env->real_value)` - key без валидации!

### Deployment Injection

- [✅] **ENV-021**: Проверить как env vars передаются в docker
  - ✅ Через .env file с base64 encoding (безопасно)
  - Файл: `HandlesRuntimeEnvGeneration.php:231-238`
- [ ] **ENV-022**: Проверить docker-compose env interpolation safety
- [ ] **ENV-023**: Проверить build args vs runtime env vars

### Shared Environment Variables

- [ ] **ENV-024**: Проверить scope shared variables (project/team)
- [ ] **ENV-025**: Проверить inheritance rules
- [ ] **ENV-026**: Проверить override protection

---

## Findings

### Критические

#### ENV-006: EnvironmentVariablePolicy полностью отключена

**Severity: CRITICAL**
**Файл:** `app/Policies/EnvironmentVariablePolicy.php`

Все методы Policy возвращают `true`:
```php
public function view(User $user, EnvironmentVariable $environmentVariable): bool
{
    return true;  // <-- Нет проверки!
}
// update, delete, forceDelete - аналогично
```

**Impact:** Любой авторизованный пользователь может читать/изменять/удалять env vars ЛЮБОГО приложения в системе (если знает UUID или ID).

#### ENV-007: SharedEnvironmentVariablePolicy частично отключена

**Severity: CRITICAL**
**Файл:** `app/Policies/SharedEnvironmentVariablePolicy.php:38-77`

Проверка team_id закомментирована для update/delete:
```php
public function update(User $user, SharedEnvironmentVariable $sharedEnvironmentVariable): bool
{
    // return $user->isAdmin() && $user->teams->contains('id', $sharedEnvironmentVariable->team_id);
    return true;  // <-- Обход авторизации!
}
```

#### ENV-017/ENV-020: Injection через env variable name

**Severity: HIGH**
**Файлы:**
- `EnvironmentVariable.php:243-248`
- `HandlesRuntimeEnvGeneration.php:119,198`

Недостаточная валидация имени переменной. Можно создать key типа:
- `MY_VAR\nMALICIOUS=evil` - внедрение дополнительных переменных
- `PATH` или `LD_PRELOAD` - перезапись системных переменных

### Важные

#### ENV-005: read:sensitive только для чтения

**Severity: MEDIUM**

Проверка `can_read_sensitive` существует только для чтения. Пользователь без этой ability всё равно может создавать/изменять env vars.

#### ENV-019: Нет защиты системных переменных

**Severity: MEDIUM**

Можно создать env var с именем `PATH`, `LD_PRELOAD`, `LD_LIBRARY_PATH` и потенциально повлиять на выполнение в контейнере.

### Низкий приоритет

#### ENV-008: Защита только на уровне контроллера

API контроллеры используют `ownedByCurrentTeamAPI()`, что защищает от cross-team access через API. Но прямое использование модели не защищено.

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| ENV-006 | Включить проверку team в EnvironmentVariablePolicy | ⏳ **СРОЧНО** | - |
| ENV-007 | Раскомментировать проверку team_id в SharedEnvironmentVariablePolicy | ⏳ **СРОЧНО** | - |
| ENV-017 | Добавить regex валидацию для env key: `^[A-Za-z_][A-Za-z0-9_]*$` | ⏳ Pending | - |
| ENV-019 | Добавить blacklist системных переменных (PATH, LD_PRELOAD, etc.) | ⏳ Pending | - |

---

## Заметки аудитора

**Дата проверки:** 2026-01-30

Основная проблема - Policies отключены (возвращают `true`), что является общей проблемой проекта (см. authorization.md).

Рекомендуемый regex для валидации env key:
```php
if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
    throw new ValidationException('Invalid environment variable name');
}
```

Blacklist системных переменных:
```php
$systemVars = ['PATH', 'LD_PRELOAD', 'LD_LIBRARY_PATH', 'HOME', 'USER', 'SHELL'];
if (in_array(strtoupper($key), $systemVars)) {
    throw new ValidationException('Cannot override system environment variable');
}
```
