# Infrastructure WebSocket Security Audit

**Приоритет:** 🟡 High
**Статус:** [ ] Не начато

---

## Обзор

Проверка безопасности WebSocket сервера (Soketi).

### Ключевые файлы для проверки:

- `config/broadcasting.php`
- `config/websockets.php`
- `app/Events/*.php`
- `routes/channels.php`
- `app/Providers/BroadcastServiceProvider.php`

---

## Гипотезы для проверки

### Authentication

- [ ] **WS-001**: Проверить WebSocket authentication mechanism
- [ ] **WS-002**: Проверить Pusher key/secret configuration
- [ ] **WS-003**: Проверить token validation при connect

### Channel Authorization

- [ ] **WS-004**: Проверить private channel authorization
- [ ] **WS-005**: Проверить presence channel authorization
- [ ] **WS-006**: Проверить channel naming - нет injection
- [ ] **WS-007**: Проверить user presence data - нет sensitive info

### Event Security

- [ ] **WS-008**: Проверить broadcast data - нет sensitive info
- [ ] **WS-009**: Проверить event names - validated
- [ ] **WS-010**: Проверить payload size limits
- [ ] **WS-011**: Проверить event rate limiting

### Channel Authorization Rules

- [ ] **WS-012**: Проверить `App.Models.Server.*` channel
- [ ] **WS-013**: Проверить `App.Models.Application.*` channel
- [ ] **WS-014**: Проверить deployment status channels
- [ ] **WS-015**: Проверить team-based channels

### Connection Security

- [ ] **WS-016**: Проверить WSS (WebSocket over TLS)
- [ ] **WS-017**: Проверить connection limits per user
- [ ] **WS-018**: Проверить idle connection timeout
- [ ] **WS-019**: Проверить reconnection handling

### Soketi Configuration

- [ ] **WS-020**: Проверить Soketi app configuration
- [ ] **WS-021**: Проверить metrics endpoint protection
- [ ] **WS-022**: Проверить debug mode disabled
- [ ] **WS-023**: Проверить cluster configuration (если используется)

### Client-Side

- [ ] **WS-024**: Проверить Echo configuration на фронтенде
- [ ] **WS-025**: Проверить credential exposure в JS
- [ ] **WS-026**: Проверить error handling - нет info leak

### Message Validation

- [ ] **WS-027**: Проверить inbound message validation
- [ ] **WS-028**: Проверить client event permissions
- [ ] **WS-029**: Проверить broadcast payload sanitization

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
