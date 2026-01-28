# Frontend Sensitive Data Exposure Audit

**Приоритет:** 🔴 Critical
**Статус:** [ ] Не начато

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

- [ ] **SENS-001**: Проверить localStorage - нет tokens/passwords
- [ ] **SENS-002**: Проверить sessionStorage - нет sensitive data
- [ ] **SENS-003**: Проверить IndexedDB usage
- [ ] **SENS-004**: Проверить cookies - secure/httpOnly flags

### Console & Debugging

- [ ] **SENS-005**: Поиск console.log с sensitive data
- [ ] **SENS-006**: Проверить что debug mode отключен в production
- [ ] **SENS-007**: Проверить source maps в production

### Inertia.js Props

- [ ] **SENS-008**: Проверить shared props - нет secrets
- [ ] **SENS-009**: Проверить page props - нет лишних данных
- [ ] **SENS-010**: Проверить что passwords не передаются обратно

### Environment Variables Display

- [ ] **SENS-011**: Проверить masked display env variables
- [ ] **SENS-012**: Проверить copy to clipboard - warning
- [ ] **SENS-013**: Проверить export functionality

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
