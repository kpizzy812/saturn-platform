# Frontend Input Validation Audit

**Приоритет:** 🟡 High
**Статус:** [🔍] Проверено, найдены проблемы
**Дата аудита:** 2026-01-30

---

## Резюме уязвимостей

| Severity | Количество | Примеры |
|----------|-----------|---------|
| CRITICAL | 2 | Proxy config без санитизации, DOMPurify не везде |
| HIGH | 4 | Env vars, DB username, Webhook URL, CSP |
| MEDIUM | 6 | Git URL, Docker image, File extension, SSL size |
| LOW | 2 | maxLength, DNS примеры |

---

## Гипотезы для проверки

### General Input Validation

- [✅] **INPUT-001**: Проверить validation.ts - паттерны валидации - Хорошо реализовано
- [⚠️] **INPUT-002**: Проверить что client-side validation дублирует server-side - Частично
- [🔴] **INPUT-003**: Проверить max length restrictions на inputs - **ОТСУТСТВУЕТ**
- [✅] **INPUT-004**: Проверить number range validation - OK (validatePort)

### Form Submission

- [✅] **INPUT-005**: Проверить form submission - нет double-submit
- [✅] **INPUT-006**: Проверить disabled state во время submit
- [✅] **INPUT-007**: Проверить error display - clear previous errors

### Specific Input Types

#### Server Creation
- [✅] **INPUT-008**: IP address validation - validateIPAddress()
- [✅] **INPUT-009**: Port number validation - validatePort()
- [✅] **INPUT-010**: SSH key format validation - validateSSHKey()

#### Application Creation
- [🔴] **INPUT-011**: Git URL validation - **НЕТ ВАЛИДАЦИИ**
- [✅] **INPUT-012**: Branch name validation - OK
- [✅] **INPUT-013**: Domain name validation - Regex валидация
- [✅] **INPUT-014**: Port mapping validation - OK

#### Database Creation
- [✅] **INPUT-015**: Database name validation - OK
- [🔴] **INPUT-016**: Username validation - **НЕТ САНИТИЗАЦИИ**
- [✅] **INPUT-017**: Password strength validation - validatePassword()

#### Environment Variables
- [🔴] **INPUT-018**: Variable name validation - **НЕТ САНИТИЗАЦИИ НА ФРОНТЕ**
- [✅] **INPUT-019**: Value input - multiline handling - OK

#### Team/User Management
- [✅] **INPUT-020**: Email validation - OK
- [✅] **INPUT-021**: Team name validation - OK
- [✅] **INPUT-022**: Username validation - OK

### Dangerous Input Patterns

- [✅] **INPUT-023**: Проверить что нельзя ввести скрипты в text fields - React автоматически экранирует
- [✅] **INPUT-024**: Проверить path traversal patterns в file inputs - OK
- [🔴] **INPUT-025**: Проверить command injection patterns - **Proxy config не валидируется**

### Rich Inputs

- [✅] **INPUT-026**: Docker Compose editor - validateDockerCompose()
- [⚠️] **INPUT-027**: SQL editor - injection patterns warning - Нет предупреждения
- [✅] **INPUT-028**: Code editors - safe rendering - OK

---

## Findings

### Критические (2)

#### [INPUT-CRITICAL-001] 🔴 Proxy configuration без санитизации

**Файл:** `resources/js/pages/Servers/Proxy/Configuration.tsx` (строка 39)

**Проблема:**
```typescript
router.post(`/servers/${server.uuid}/proxy/configuration`, {
    configuration: config,  // Может содержать malicious content
```

**Severity:** CRITICAL

**Рекомендация:**
```typescript
function validateProxyConfig(config: string): ValidationResult {
    const dangerousPatterns = [
        /exec\s*\(/,
        /import\s+\(/,
        /eval\s*\(/,
        /<script/i
    ];

    for (const pattern of dangerousPatterns) {
        if (pattern.test(config)) {
            return { valid: false, error: 'Configuration contains dangerous patterns' };
        }
    }
    return { valid: true };
}
```

---

#### [INPUT-CRITICAL-002] 🔴 Отсутствие DOMPurify для user-generated контента

**Severity:** CRITICAL

**Описание:** Если результаты SQL запросов или другой user-generated контент отображается без санитизации.

**Рекомендация:**
```typescript
import DOMPurify from 'dompurify';
const sanitized = DOMPurify.sanitize(content, { ALLOWED_TAGS: [] });
```

---

### Важные (4)

#### [INPUT-HIGH-001] 🟡 Отсутствие санитизации environment переменных

**Файл:** `resources/js/pages/Environments/Variables.tsx` (строка 102)

**Проблема:**
```typescript
const newVariable: EnvironmentVariable = {
    key: newKey.trim(),
    value: newValue.trim(),  // Только trim(), нет санитизации
};
```

**Severity:** HIGH

**Рекомендация:**
```typescript
function validateEnvVarKey(key: string): ValidationResult {
    const envKeyRegex = /^[A-Z_][A-Z0-9_]*$/i;
    if (!envKeyRegex.test(key)) {
        return { valid: false, error: 'Invalid variable name format' };
    }
    return { valid: true };
}
```

---

#### [INPUT-HIGH-002] 🟡 Отсутствие санитизации database username

**Файл:** `resources/js/pages/Databases/Users.tsx` (строка 75-80)

**Проблема:**
```typescript
const confirmCreate = () => {
    if (newUsername.trim() && newPassword.trim()) {
        router.post(`/databases/${database.uuid}/users`, {
            username: newUsername,  // Не санитизировано
```

**Severity:** HIGH

**Рекомендация:**
```typescript
function validateDatabaseUsername(username: string): ValidationResult {
    const validUsernameRegex = /^[a-zA-Z0-9_-]{3,32}$/;
    if (!validUsernameRegex.test(username)) {
        return { valid: false, error: 'Username must be 3-32 alphanumeric characters' };
    }
    return { valid: true };
}
```

---

#### [INPUT-HIGH-003] 🟡 Webhook URL без валидации

**Файл:** `resources/js/pages/Settings/Notifications/Webhook.tsx`

**Severity:** HIGH

**Рекомендация:**
```typescript
function validateWebhookURL(url: string): ValidationResult {
    try {
        const parsed = new URL(url);
        if (!['http', 'https'].includes(parsed.protocol.replace(':', ''))) {
            return { valid: false, error: 'Only HTTP/HTTPS URLs allowed' };
        }
        return { valid: true };
    } catch {
        return { valid: false, error: 'Invalid URL format' };
    }
}
```

---

#### [INPUT-HIGH-004] 🟡 Отсутствие Content Security Policy

**Severity:** HIGH

**Рекомендация:**
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'
```

---

### Средний приоритет (6)

#### [INPUT-MEDIUM-001] ⚠️ Git repository URL без валидации

**Файл:** `resources/js/pages/Applications/Create.tsx` (строка 354)

---

#### [INPUT-MEDIUM-002] ⚠️ Docker image без валидации

**Файл:** `resources/js/pages/Applications/Create.tsx` (строка 399)

---

#### [INPUT-MEDIUM-003] ⚠️ Отсутствие валидации расширения файла

**Файл:** `resources/js/pages/Settings/Account.tsx`

---

#### [INPUT-MEDIUM-004] ⚠️ SSL сертификаты без ограничения размера

**Файл:** `resources/js/pages/SSL/Upload.tsx`

---

#### [INPUT-MEDIUM-005] ⚠️ Числовые поля без min/max

**Файл:** `resources/js/pages/Settings/Notifications/Email.tsx`

---

#### [INPUT-MEDIUM-006] ⚠️ Filter values в FilterBuilder

**Файл:** `resources/js/components/features/FilterBuilder.tsx` (строки 106-150)

---

### Низкий приоритет (2)

#### [INPUT-LOW-001] Отсутствие maxLength в Input компонентах

**Файл:** `resources/js/components/ui/Input.tsx`

---

#### [INPUT-LOW-002] DNS пример значения

**Файл:** `resources/js/pages/Domains/Add.tsx`

---

## Положительные находки

### ✅ Хорошо реализовано

1. **validation.ts** - Комплексные функции валидации:
   - `validateIPAddress()` - IPv4/IPv6/hostname
   - `validateCIDR()` - CIDR notation
   - `validateSSHKey()` - SSH ключи
   - `validatePassword()` - Сила пароля
   - `validatePort()` - Порты (1-65535)
   - `validateDockerCompose()` - YAML структура

2. **DOMPurify для QR кодов** - `Auth/TwoFactor/Setup.tsx`

3. **File upload validation** - `Settings/Account.tsx`:
   - Размер файла (2MB max)
   - MIME types whitelist

4. **Domain validation** - Regex-based в `Domains/Add.tsx`

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| INPUT-CRITICAL-001 | Proxy config санитизация | ⏳ Pending | - |
| INPUT-CRITICAL-002 | DOMPurify везде | ⏳ Pending | - |
| INPUT-HIGH-001 | Env vars санитизация | ⏳ Pending | - |
| INPUT-HIGH-002 | DB username валидация | ⏳ Pending | - |
| INPUT-HIGH-003 | Webhook URL валидация | ⏳ Pending | - |

---

## Рекомендуемый план действий

### Немедленно (Critical):
- Добавить санитизацию proxy configuration
- Внедрить DOMPurify для всего user-generated контента

### Высокий приоритет (High):
- Добавить валидацию для environment переменных
- Валидировать database username
- Валидировать webhook URL
- Настроить CSP headers

### Средний приоритет (Medium):
- Добавить git URL валидацию
- Валидировать Docker image формат
- Ограничить размеры файлов
- Добавить min/max для числовых полей

### Рекомендация: Использовать Zod

```typescript
import { z } from 'zod';

const ApplicationCreateSchema = z.object({
    name: z.string().min(1, 'Required').max(255, 'Too long'),
    git_repository: z.string().url('Invalid URL'),
    git_branch: z.string().min(1, 'Required'),
    docker_image: z.string().regex(/^[a-z0-9-._/:]+$/, 'Invalid format').optional(),
});
```
