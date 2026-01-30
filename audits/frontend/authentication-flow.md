# Frontend Authentication Flow Audit

**Приоритет:** 🔴 Critical
**Статус:** [🔍] Проверено, найдены критические проблемы
**Дата аудита:** 2026-01-30

---

## Резюме уязвимостей

| Severity | Количество | Примеры |
|----------|-----------|---------|
| CRITICAL | 1 | Password reset token в URL |
| HIGH | 8 | Session timeout 7 дней, OAuth без state, Recovery codes plain text |
| MEDIUM | 5 | CSRF на logout, Timing attacks, PKCE |
| LOW | 2 | Token storage, QR sanitization |

---

## Гипотезы для проверки

### Login Flow

- [✅] **AUTHFLOW-001**: Проверить login form - password не в URL - OK
- [✅] **AUTHFLOW-002**: Проверить autocomplete attributes - OK
- [✅] **AUTHFLOW-003**: Проверить password masking - OK
- [⚠️] **AUTHFLOW-004**: Проверить login error messages - Возможен timing attack

### Token Handling

- [✅] **AUTHFLOW-005**: Проверить где хранится auth token - httpOnly cookies (OK)
- [✅] **AUTHFLOW-006**: Проверить что token не в localStorage - OK (Inertia)
- [✅] **AUTHFLOW-007**: Проверить token refresh mechanism - OK
- [🔴] **AUTHFLOW-008**: Проверить token expiration handling - **7 дней слишком долго!**

### Session Management

- [⚠️] **AUTHFLOW-009**: Проверить logout - CSRF не явный
- [🔴] **AUTHFLOW-010**: Проверить session timeout handling - **Нет обнаружения на фронте**
- [⚠️] **AUTHFLOW-011**: Проверить "remember me" - Недостаточная валидация

### Password Reset

- [✅] **AUTHFLOW-012**: Проверить forgot password flow - OK
- [🔴] **AUTHFLOW-013**: Проверить reset token в URL - **CRITICAL: В URL!**
- [✅] **AUTHFLOW-014**: Проверить password requirements UI - OK

### Two-Factor Authentication

- [✅] **AUTHFLOW-015**: Проверить 2FA challenge page - OK
- [🔴] **AUTHFLOW-016**: Проверить recovery code input - **Plain text display!**
- [🔴] **AUTHFLOW-017**: Проверить 2FA setup flow - **Skip allowed, Trust device без fingerprint**

### OAuth Flow

- [🔴] **AUTHFLOW-018**: Проверить OAuth callback handling - **Нет state validation!**
- [🔴] **AUTHFLOW-019**: Проверить state parameter usage - **Отсутствует**
- [⚠️] **AUTHFLOW-020**: Проверить OAuth error handling - Generic messages (OK)

### Onboarding

- [✅] **AUTHFLOW-021**: Проверить registration flow - OK
- [✅] **AUTHFLOW-022**: Проверить team invitation acceptance - OK
- [✅] **AUTHFLOW-023**: Проверить email verification flow - OK

### Route Protection

- [✅] **AUTHFLOW-024**: Проверить protected routes - OK
- [✅] **AUTHFLOW-025**: Проверить auth state management - OK
- [✅] **AUTHFLOW-026**: Проверить page refresh - OK

---

## Findings

### Критические (1)

#### [AUTH-CRITICAL-001] 🔴 Password Reset Token в URL

**Файл:** `resources/js/pages/Auth/ResetPassword.tsx` (строка 7-8)

**Проблема:**
```typescript
interface Props {
    email: string;
    token: string; // Передается из URL!
}
```

**Severity:** CRITICAL

**Риски:**
- Browser history
- Web server logs
- Referrer headers
- Browser history leakage

**Рекомендация:**
- Передавать токен только в POST body
- Сделать токены одноразовыми
- Ограничить lifetime до 1 часа
- Инвалидировать все сессии при reset

---

### Важные (8)

#### [AUTH-HIGH-001] 🟡 Session Lifetime 7 дней

**Файл:** `config/session.php` (строка 34)

```php
'lifetime' => env('SESSION_LIFETIME', 10080), // 7 дней!
```

**Severity:** HIGH

**Рекомендация:** Установить 120 минут (2 часа)

---

#### [AUTH-HIGH-002] 🟡 Отсутствует HttpOnly флаг явно

**Файл:** `config/session.php`

**Severity:** HIGH

**Рекомендация:**
```php
'http_only' => true,
'secure' => true,
'same_site' => 'Strict',
```

---

#### [AUTH-HIGH-003] 🟡 Trust Device без fingerprinting

**Файл:** `resources/js/pages/Auth/TwoFactor/Verify.tsx` (строка 163-166)

**Severity:** HIGH

**Рекомендация:** Device fingerprinting + IP hash + 30 day limit

---

#### [AUTH-HIGH-004] 🟡 Recovery Codes отображаются plain text

**Файл:** `resources/js/pages/Auth/TwoFactor/Setup.tsx` (строки 117-129)

**Severity:** HIGH

**Рекомендация:**
- Частичное отображение
- Auto-clear clipboard через 30 сек
- Обязательное скачивание файла

---

#### [AUTH-HIGH-005] 🟡 2FA может быть пропущена

**Файл:** `resources/js/pages/Auth/TwoFactor/Setup.tsx` (строки 241-248)

**Severity:** HIGH

**Рекомендация:** Обязать 2FA для admin пользователей

---

#### [AUTH-HIGH-006] 🟡 OAuth без state validation

**Файл:** `app/Http/Controllers/OauthController.php` (строки 18-42)

**Severity:** HIGH

**Рекомендация:**
```php
if (! request('state') || request('state') !== session('oauth.state')) {
    return redirect()->route('login')->withErrors(['Invalid OAuth state']);
}
```

---

#### [AUTH-HIGH-007] 🟡 Автоматическое создание пользователя через OAuth

**Файл:** `app/Http/Controllers/OauthController.php`

**Severity:** HIGH

**Рекомендация:** Проверять email verification от провайдера

---

#### [AUTH-HIGH-008] 🟡 Нет обнаружения session timeout на фронте

**Severity:** HIGH

**Рекомендация:**
```typescript
router.on('error', (error) => {
    if (error.response?.status === 401) {
        window.location.href = '/login';
    }
});
```

---

### Средний приоритет (5)

#### [AUTH-MEDIUM-001] ⚠️ CSRF на logout не явный

**Файл:** `resources/js/components/layout/Header.tsx` (строка 129)

---

#### [AUTH-MEDIUM-002] ⚠️ Timing attacks при login

**Файл:** `app/Providers/FortifyServiceProvider.php`

---

#### [AUTH-MEDIUM-003] ⚠️ PKCE отсутствует для OAuth

---

#### [AUTH-MEDIUM-004] ⚠️ Нет sliding window session

---

#### [AUTH-MEDIUM-005] ⚠️ CSRF из DOM без error handling

---

### Низкий приоритет (2)

#### [AUTH-LOW-001] API токены (профилактика)

---

#### [AUTH-LOW-002] QR код sanitization (уже реализовано)

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| AUTH-CRITICAL-001 | Reset token в URL | ⏳ Pending | - |
| AUTH-HIGH-001 | Session 7 дней | ⏳ Pending | - |
| AUTH-HIGH-002 | HttpOnly флаг | ⏳ Pending | - |
| AUTH-HIGH-006 | OAuth state | ⏳ Pending | - |

---

## Рекомендуемый план действий

### CRITICAL (немедленно):
1. Исправить password reset flow - POST body вместо URL
2. Установить session lifetime 120 минут
3. Добавить HttpOnly + Secure для cookies

### HIGH (неделя):
1. Добавить OAuth state validation
2. Обязать 2FA для admin
3. Добавить session timeout detection

### MEDIUM (месяц):
1. Реализовать PKCE
2. Добавить sliding window sessions
3. Улучшить CSRF handling
