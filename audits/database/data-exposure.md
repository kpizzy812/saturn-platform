# Database Data Exposure Audit

**Приоритет:** 🔴 Critical
**Статус:** [ ] Не начато

---

## Обзор

Проверка на непреднамеренное раскрытие данных из БД.

### Ключевые файлы для проверки:

- `app/Models/*.php` ($hidden, $visible, $casts)
- API Resources (если используются)
- Controller responses

---

## Гипотезы для проверки

### Model Attributes

- [ ] **EXPOSE-001**: Проверить $hidden на всех моделях
- [ ] **EXPOSE-002**: Проверить что passwords в $hidden
- [ ] **EXPOSE-003**: Проверить что API tokens в $hidden
- [ ] **EXPOSE-004**: Проверить что private keys в $hidden
- [ ] **EXPOSE-005**: Проверить $visible где используется

### Sensitive Fields

#### User Model
- [ ] **EXPOSE-006**: password, remember_token скрыты
- [ ] **EXPOSE-007**: two_factor_secret скрыт
- [ ] **EXPOSE-008**: recovery_codes скрыты

#### Server Model
- [ ] **EXPOSE-009**: Проверить SSH credentials exposure

#### Application Model
- [ ] **EXPOSE-010**: Проверить git credentials exposure
- [ ] **EXPOSE-011**: Проверить deployment secrets

#### Database Models
- [ ] **EXPOSE-012**: Проверить database passwords
- [ ] **EXPOSE-013**: Проверить connection strings

#### PrivateKey Model
- [ ] **EXPOSE-014**: Проверить private_key field protection

#### S3Storage Model
- [ ] **EXPOSE-015**: Проверить access keys protection

#### Notification Settings
- [ ] **EXPOSE-016**: Проверить webhook URLs/tokens

### API Responses

- [ ] **EXPOSE-017**: Проверить toArray() responses
- [ ] **EXPOSE-018**: Проверить JSON serialization
- [ ] **EXPOSE-019**: Проверить API resource transformations

### Relationships

- [ ] **EXPOSE-020**: Проверить eager loading - нет лишних данных
- [ ] **EXPOSE-021**: Проверить nested relationships exposure
- [ ] **EXPOSE-022**: Проверить with() calls

### Query Logging

- [ ] **EXPOSE-023**: Проверить query logging disabled в production
- [ ] **EXPOSE-024**: Проверить Telescope query recording

### Backups

- [ ] **EXPOSE-025**: Проверить backup data exposure
- [ ] **EXPOSE-026**: Проверить database dump security

### Caching

- [ ] **EXPOSE-027**: Проверить cached data - нет over-caching sensitive data
- [ ] **EXPOSE-028**: Проверить cache key isolation

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
