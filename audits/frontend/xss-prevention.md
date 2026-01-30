# Frontend XSS Prevention Audit

**Приоритет:** 🔴 Critical
**Статус:** [x] Проверено

---

## Обзор

Проверка защиты от Cross-Site Scripting (XSS) во React frontend.

### Ключевые директории для проверки:

- `resources/js/pages/` (137+ страниц)
- `resources/js/components/`
- `resources/js/hooks/`

---

## Гипотезы для проверки

### React JSX Safety

- [x] **XSS-001**: Поиск использования `dangerouslySetInnerHTML` - 🔴 FOUND ISSUES
- [x] **XSS-002**: Проверить все места где используется `dangerouslySetInnerHTML` на sanitization - ⚠️ Not all sanitized
- [x] **XSS-003**: Проверить rendering user-generated content - ✅ OK (React escapes)

### URL Handling

- [x] **XSS-004**: Проверить href attributes - нет javascript: URLs - ✅ OK
- [x] **XSS-005**: Проверить window.location assignments - ✅ OK
- [x] **XSS-006**: Проверить redirect URLs validation - ✅ OK
- [x] **XSS-007**: Проверить src attributes для images/iframes - ✅ OK

### DOM Manipulation

- [x] **XSS-008**: Поиск использования innerHTML - ⚠️ Via dangerouslySetInnerHTML
- [x] **XSS-009**: Проверить document.write usage - ✅ Not found
- [x] **XSS-010**: Проверить eval() usage - ✅ Not found
- [x] **XSS-011**: Проверить Function() constructor usage - ✅ Not found

### Event Handlers

- [x] **XSS-012**: Проверить inline event handlers с user data - ✅ OK
- [x] **XSS-013**: Проверить onClick handlers - нет user-controlled strings - ✅ OK

### Third-Party Libraries

- [x] **XSS-014**: Проверить markdown rendering libraries (sanitization) - ✅ DOMPurify used
- [x] **XSS-015**: Проверить code highlighting libraries - ✅ OK
- [x] **XSS-016**: Проверить rich text editors (если используются) - N/A
- [x] **XSS-017**: Проверить chart libraries с user data - ✅ OK

### Specific Components

- [x] **XSS-018**: Terminal output display - escape sequences - ✅ xterm.js safely handles
- [x] **XSS-019**: Deployment logs display - ✅ React escapes text
- [x] **XSS-020**: Database query results display - ✅ OK (plain text)
- [x] **XSS-021**: Server metrics display - ✅ OK
- [x] **XSS-022**: Notification messages display - ✅ OK

### Template Injection

- [x] **XSS-023**: Проверить template strings с user data - ✅ OK
- [x] **XSS-024**: Проверить string interpolation в attributes - ✅ OK

### Storage XSS

- [x] **XSS-025**: Проверить localStorage/sessionStorage data usage - ✅ OK
- [x] **XSS-026**: Проверить cookie data rendering - ✅ OK

---

## Findings

### Критические

#### XSS-001-F: Unsanitized dangerouslySetInnerHTML in Admin Templates

**Файл:** `resources/js/pages/Admin/Templates/Index.tsx:406, 411`

**Проблема:**
```tsx
<a
    href={link.url}
    dangerouslySetInnerHTML={{ __html: link.label }}
/>
```

`link.label` is rendered directly without sanitization. If this label comes from API or user input, an attacker can inject malicious JavaScript.

**Attack Example:**
```javascript
link.label = '<img src=x onerror="alert(document.cookie)" />'
```

**Severity:** 🔴 Critical

**Fix:**
```tsx
// Option 1: Plain text (if HTML not needed)
<a href={link.url}>{link.label}</a>

// Option 2: Sanitize with DOMPurify
import DOMPurify from 'dompurify';
<a href={link.url} dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(link.label) }} />
```

#### XSS-002-F: Client-side SQL Escaping (SQL Injection Risk)

**Файлы:**
- `resources/js/components/features/FilterBuilder.tsx:428-438`
- `resources/js/components/features/TableDataViewer.tsx:103-108`

**Проблема:**
```tsx
function escapeSql(value: string): string {
    return value.replace(/'/g, "''");
}

// Usage:
return `${column} ILIKE '%${escapeSql(c.value)}%'`;
```

**Issues:**
1. Client-side escaping can be bypassed
2. Only escapes single quotes
3. Column names not validated
4. String concatenation instead of parameterized queries

**Note:** This is SQL injection, not XSS, but discovered during XSS audit.

**Severity:** 🟠 High

**Fix:** Move SQL building to backend with parameterized queries.

### Важные

#### XSS-003-F: QR Code SVG - Properly Sanitized

**Файл:** `resources/js/pages/Auth/TwoFactor/Setup.tsx:40-47`

**Код:**
```tsx
const sanitizedQrCode = useMemo(() => {
    return DOMPurify.sanitize(qrCode, {
        USE_PROFILES: { svg: true, svgFilters: true },
        ADD_TAGS: ['svg', 'path', 'rect', 'g', 'defs', 'clipPath', 'use'],
        ADD_ATTR: ['viewBox', 'fill', 'd', 'transform', 'clip-path', 'xmlns'],
    });
}, [qrCode]);
```

**Status:** ✅ PROPERLY MITIGATED

Uses DOMPurify with restricted whitelist - good practice.

### Средний приоритет

#### XSS-004-F: Exception Message Display

**Файл:** `resources/views/errors/500.blade.php:10`

**Код:**
```blade
{!! Purify::clean($exception->getMessage()) !!}
```

**Status:** ✅ MITIGATED with HTMLPurifier

### Низкий приоритет

#### XSS-005-F: Log/Terminal Output - Safe

**Файлы:**
- `resources/js/pages/Deployments/BuildLogs.tsx:499-502`
- `resources/js/components/features/Terminal.tsx:77-95`

**Status:** ✅ SAFE

- Logs rendered as React text content (auto-escaped)
- Terminal uses xterm.js which safely handles escape sequences
- Not using dangerouslySetInnerHTML

#### XSS-006-F: Database Cell Data - Safe

**Файл:** `resources/js/components/features/TableDataViewer.tsx:969-973`

**Код:**
```tsx
<span className="break-all">
    {String(cellValue).length > 100
        ? String(cellValue).substring(0, 100) + '...'
        : String(cellValue)}
</span>
```

**Status:** ✅ SAFE - React text content, properly escaped

---

## Положительные находки

| Component | Practice | Status |
|-----------|----------|--------|
| TwoFactor Setup | DOMPurify for SVG | ✅ Excellent |
| BuildLogs | React text escaping | ✅ OK |
| Terminal | xterm.js safe handling | ✅ OK |
| TableDataViewer cells | Plain text rendering | ✅ OK |
| Error pages | HTMLPurifier | ✅ OK |

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| XSS-001-F | Sanitize link.label in Admin Templates | ✅ FIXED | c8ad4e1 |
| XSS-002-F | Move SQL building to backend | 🔧 To Fix | - |
| XSS-003-F | QR Code sanitization | ✅ Already OK | - |
| XSS-004-F | Exception message display | ✅ Already OK | - |

---

## Рекомендации

### Немедленные действия

1. **Fix Admin Templates XSS**
   ```tsx
   // resources/js/pages/Admin/Templates/Index.tsx
   import DOMPurify from 'dompurify';

   // Replace:
   dangerouslySetInnerHTML={{ __html: link.label }}

   // With:
   dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(link.label) }}
   ```

2. **Add Content Security Policy (CSP) headers**
   ```php
   // In middleware or config
   Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'
   ```

3. **Move FilterBuilder SQL logic to backend**
   - Send filter objects to API
   - Backend validates column names against whitelist
   - Backend uses parameterized queries

### Долгосрочные улучшения

1. **ESLint security plugin**
   ```bash
   npm install --save-dev eslint-plugin-security
   ```

2. **Security-focused tests**
   - Add XSS payload tests for all input fields
   - Test for SQL injection patterns

3. **Security headers**
   - X-Content-Type-Options: nosniff
   - X-Frame-Options: SAMEORIGIN
   - X-XSS-Protection: 1; mode=block

---

## Заметки аудитора

### Проверено 2024-01-30

**Overall Assessment:** Saturn frontend has generally good XSS protection due to React's default escaping. Main issue is one instance of unsanitized dangerouslySetInnerHTML in Admin Templates.

**Security Score:** B

| Category | Score |
|----------|-------|
| React JSX Safety | A- |
| Third-Party Libraries | A |
| Terminal/Logs Display | A |
| User Data Rendering | B+ |
| SQL Query Building | C (moved from XSS to SQL injection) |

### Key Findings Summary

1. **1 Critical XSS** - dangerouslySetInnerHTML without sanitization
2. **1 High SQL Injection** - Client-side SQL escaping (not strictly XSS)
3. **Most components safe** - React default escaping works well
4. **Good practices** - DOMPurify used for SVG, xterm.js for terminal
