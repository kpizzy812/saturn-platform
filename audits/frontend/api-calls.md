# Frontend API Calls Security Audit

**Приоритет:** 🟡 High
**Статус:** [ ] Не начато

---

## Обзор

Проверка безопасности API вызовов из React frontend.

### Ключевые файлы для проверки:

- `resources/js/lib/api.ts`
- `resources/js/hooks/*.ts`
- `resources/js/pages/**/*.tsx`

---

## Гипотезы для проверки

### CSRF Protection

- [ ] **APICALL-001**: Проверить CSRF token handling
- [ ] **APICALL-002**: Проверить Inertia.js CSRF integration
- [ ] **APICALL-003**: Проверить CSRF для API calls (Sanctum)

### Request Security

- [ ] **APICALL-004**: Проверить что credentials включены правильно
- [ ] **APICALL-005**: Проверить content-type headers
- [ ] **APICALL-006**: Проверить request timeout handling

### Response Handling

- [ ] **APICALL-007**: Проверить error response handling - нет sensitive info exposure
- [ ] **APICALL-008**: Проверить JSON parsing safety
- [ ] **APICALL-009**: Проверить redirect handling

### Data Exposure

- [ ] **APICALL-010**: Проверить что API responses не кэшируются небезопасно
- [ ] **APICALL-011**: Проверить browser history - нет sensitive data в URLs
- [ ] **APICALL-012**: Проверить console.log - нет sensitive data logging

### API Error Handling

- [ ] **APICALL-013**: Проверить 401/403 handling - redirect to login
- [ ] **APICALL-014**: Проверить network error handling
- [ ] **APICALL-015**: Проверить rate limit error handling

### Hooks Security

- [ ] **APICALL-016**: Проверить useApplications hook
- [ ] **APICALL-017**: Проверить useServers hook
- [ ] **APICALL-018**: Проверить useDeployments hook
- [ ] **APICALL-019**: Проверить useDatabases hook
- [ ] **APICALL-020**: Проверить useServices hook

### File Upload Handling

- [ ] **APICALL-021**: Проверить FormData construction
- [ ] **APICALL-022**: Проверить upload progress handling
- [ ] **APICALL-023**: Проверить file validation на клиенте

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
