# Backend Webhooks Security Audit

**Приоритет:** 🔴 Critical
**Статус:** [x] Проверено

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

- [x] **WH-001**: Проверить signature validation (X-Hub-Signature-256) - ⚠️ CRITICAL: Bypassed in dev mode
- [ ] **WH-002**: Проверить replay attack protection (timestamp/nonce) - НЕТ ЗАЩИТЫ
- [x] **WH-003**: Проверить payload size limits - ОК (Laravel default)
- [x] **WH-004**: Проверить event type validation - ОК (push/pull_request)
- [x] **WH-005**: Проверить repository/branch matching - ОК
- [x] **WH-006**: Проверить что secret хранится безопасно - ОК (encrypted in DB)

### GitLab Webhooks

- [x] **WH-007**: Проверить X-Gitlab-Token validation - ⚠️ Uses non-timing-safe comparison
- [x] **WH-008**: Проверить payload structure validation - ОК
- [x] **WH-009**: Проверить project/branch matching - ОК
- [x] **WH-010**: Проверить author_association для MR - ⚠️ MISSING (unlike GitHub)

### Bitbucket Webhooks

- [x] **WH-011**: Проверить signature validation - ⚠️ CRITICAL: Bypassed in dev mode
- [x] **WH-012**: Проверить payload validation - ОК
- [x] **WH-013**: Проверить author check для PR - ⚠️ MISSING

### Gitea Webhooks

- [x] **WH-014**: Проверить signature validation - ⚠️ CRITICAL: Bypassed in dev mode
- [x] **WH-015**: Проверить payload validation - ОК
- [x] **WH-016**: Проверить author check для PR - ⚠️ MISSING

### Stripe Webhooks

- [x] **WH-017**: Проверить Stripe signature validation - ОК (uses Stripe SDK)
- [x] **WH-018**: Проверить event type whitelist - Проверяется в StripeProcessJob
- [ ] **WH-019**: Проверить idempotency handling - НЕТ
- [x] **WH-020**: Проверить что error не раскрывает info - ⚠️ Exposes exception message

### General Webhook Security

- [ ] **WH-021**: Проверить rate limiting на webhook endpoints - НЕТ
- [x] **WH-022**: Проверить timeout handling - ОК (queue jobs)
- [x] **WH-023**: Проверить async processing - ОК (queue_application_deployment)
- [x] **WH-024**: Проверить error handling - ⚠️ handleError может раскрывать info
- [x] **WH-025**: Проверить logging - ОК

### Webhook Trigger Security (Outgoing)

- [ ] **WH-026**: Проверить team webhooks - URL validation - НЕ ПРОВЕРЕНО
- [ ] **WH-027**: Проверить SSRF protection в webhook URLs - НЕ ПРОВЕРЕНО
- [ ] **WH-028**: Проверить timeout на outgoing webhooks - НЕ ПРОВЕРЕНО
- [ ] **WH-029**: Проверить retry logic - НЕ ПРОВЕРЕНО

---

## Findings

### Критические

#### WH-001-F: Signature Verification Bypassed in Dev Mode

**Файлы:**
- `app/Http/Controllers/Webhook/Github.php:83`
- `app/Http/Controllers/Webhook/Github.php:275-278`
- `app/Http/Controllers/Webhook/Bitbucket.php:64`
- `app/Http/Controllers/Webhook/Gitea.php:71`

**Проблема:**
```php
if (! hash_equals($x_hub_signature_256, $hmac) && ! isDev()) {
    // signature validation skipped in dev mode
}
```

Signature verification is completely bypassed when `isDev()` returns true. This allows:
1. Any attacker who knows a dev/staging server to send forged webhook payloads
2. Trigger unauthorized deployments on dev/staging environments
3. Potential code execution if malicious repository is deployed

**Severity:** 🔴 Critical
**CVSS:** 9.1 (Network-based, no authentication required)

#### WH-002-F: GitLab Missing Author Association Check

**Файл:** `app/Http/Controllers/Webhook/Gitlab.php`

**Проблема:**
GitLab merge request handler does not check author association. Unlike GitHub which checks:
```php
$trustedAssociations = ['OWNER', 'MEMBER', 'COLLABORATOR', 'CONTRIBUTOR'];
if (! in_array($author_association, $trustedAssociations)) {
    // reject
}
```

GitLab allows ANY user to trigger preview deployments via MR.

**Severity:** 🔴 Critical
**Impact:** Unauthorized code execution via malicious MR

#### WH-003-F: Bitbucket/Gitea Missing Author Check

**Файлы:**
- `app/Http/Controllers/Webhook/Bitbucket.php:116`
- `app/Http/Controllers/Webhook/Gitea.php:140`

**Проблема:**
Same as GitLab - no author/contributor verification for PR deployments.

**Severity:** 🔴 Critical

### Важные

#### WH-004-F: GitLab Non-Timing-Safe Comparison

**Файл:** `app/Http/Controllers/Webhook/Gitlab.php:103`

**Проблема:**
```php
if ($webhook_secret !== $x_gitlab_token) {
```

Uses `!==` instead of `hash_equals()`, potentially vulnerable to timing attacks.

**Severity:** 🟠 High

#### WH-005-F: Stripe Error Message Exposure

**Файл:** `app/Http/Controllers/Webhook/Stripe.php:26`

**Проблема:**
```php
return response($e->getMessage(), 400);
```

Exception messages could contain sensitive information about the system.

**Severity:** 🟡 Medium

### Низкий приоритет

#### WH-006-F: No Rate Limiting on Webhooks

**Проблема:**
Webhook endpoints have no rate limiting, allowing potential DoS attacks.

**Severity:** 🟢 Low (webhooks typically have built-in protection from providers)

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| WH-001-F | Remove isDev() bypass in signature validation | 🔧 To Fix | - |
| WH-002-F | Add author check for GitLab MR | 🔧 To Fix | - |
| WH-003-F | Add author check for Bitbucket/Gitea PR | 🔧 To Fix | - |
| WH-004-F | Use hash_equals for GitLab token | 🔧 To Fix | - |
| WH-005-F | Sanitize Stripe error message | 🔧 To Fix | - |

---

## Заметки аудитора

### Проверено 2024-01-30

1. GitHub webhook signature validation is properly implemented with `hash_equals()` but bypassed in dev mode
2. GitHub has proper author_association check for PRs from public contributors
3. GitLab, Bitbucket, Gitea are missing author verification for PR/MR deployments
4. Stripe uses official SDK which handles signature verification correctly
5. All webhooks use async job processing (good)
6. Queue rate limiting exists via `queue_application_deployment` function

### Рекомендации

1. **NEVER** bypass security checks in dev mode - attackers target dev/staging
2. Add author verification to all git providers for PR deployments
3. Consider adding webhook replay protection (timestamp + nonce)
4. Add rate limiting middleware to webhook routes
