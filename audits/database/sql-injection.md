# Database SQL Injection Audit

**Приоритет:** 🔴 Critical
**Статус:** [ ] Не начато

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

- [ ] **SQLI-001**: Поиск DB::raw() usage
- [ ] **SQLI-002**: Поиск DB::select() с raw SQL
- [ ] **SQLI-003**: Поиск whereRaw() usage
- [ ] **SQLI-004**: Поиск havingRaw() usage
- [ ] **SQLI-005**: Поиск orderByRaw() usage
- [ ] **SQLI-006**: Поиск selectRaw() usage
- [ ] **SQLI-007**: Проверить все raw queries на proper binding

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

> Записывать найденные критические проблемы здесь

### Важные

> Записывать найденные важные проблемы здесь

### Низкий приоритет

> Записывать проблемы низкого приоритета здесь

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| - | - | - | - |

---

## Заметки аудитора

> Дополнительные заметки при проверке
