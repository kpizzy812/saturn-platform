# Backend Webhooks Security Audit

**Приоритет:** 🟡 High
**Статус:** [ ] Не начато

---

## Обзор

Проверка обработчиков webhooks от внешних сервисов.

### Ключевые файлы для проверки:

- `routes/webhooks.php`
- `app/Http/Controllers/Webhook/Github.php`
- `app/Http/Controllers/Webhook/Gitlab.php`
- `app/Http/Controllers/Webhook/Bitbucket.php`
- `app/Http/Controllers/Webhook/Gitea.php`
- `app/Http/Controllers/Webhook/Stripe.php`

---

## Гипотезы для проверки

### GitHub Webhooks

- [ ] **WH-001**: Проверить signature validation (X-Hub-Signature-256)
- [ ] **WH-002**: Проверить replay attack protection (timestamp/nonce)
- [ ] **WH-003**: Проверить payload size limits
- [ ] **WH-004**: Проверить event type validation
- [ ] **WH-005**: Проверить repository/branch matching
- [ ] **WH-006**: Проверить что secret хранится безопасно

### GitLab Webhooks

- [ ] **WH-007**: Проверить X-Gitlab-Token validation
- [ ] **WH-008**: Проверить payload structure validation
- [ ] **WH-009**: Проверить project/branch matching

### Bitbucket Webhooks

- [ ] **WH-010**: Проверить signature validation
- [ ] **WH-011**: Проверить payload validation

### Gitea Webhooks

- [ ] **WH-012**: Проверить signature validation
- [ ] **WH-013**: Проверить payload validation

### Stripe Webhooks

- [ ] **WH-014**: Проверить Stripe signature validation
- [ ] **WH-015**: Проверить event type whitelist
- [ ] **WH-016**: Проверить idempotency handling
- [ ] **WH-017**: Проверить что Stripe events fetched from API (не только webhook)

### General Webhook Security

- [ ] **WH-018**: Проверить rate limiting на webhook endpoints
- [ ] **WH-019**: Проверить timeout handling
- [ ] **WH-020**: Проверить async processing (queue jobs)
- [ ] **WH-021**: Проверить error handling - нет information disclosure
- [ ] **WH-022**: Проверить logging - нет sensitive data в logs

### Webhook Trigger Security (Outgoing)

- [ ] **WH-023**: Проверить team webhooks - URL validation
- [ ] **WH-024**: Проверить SSRF protection в webhook URLs
- [ ] **WH-025**: Проверить timeout на outgoing webhooks
- [ ] **WH-026**: Проверить retry logic

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
