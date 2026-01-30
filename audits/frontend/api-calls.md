# Frontend API Calls Security Audit

**Приоритет:** 🟡 High
**Статус:** [🔍] Проверено, найдены проблемы
**Дата аудита:** 2026-01-30

---

## Резюме уязвимостей

| Severity | Количество | Примеры |
|----------|-----------|---------|
| CRITICAL | 0 | - |
| HIGH | 2 | Утечка ошибок, Чувствительные данные в localStorage |
| MEDIUM | 5 | Отсутствие CSRF, XSS, Отсутствие валидации, URL Injection |
| LOW | 3 | localStorage availability, timeouts |

---

## Гипотезы для проверки

### CSRF Protection

- [⚠️] **APICALL-001**: Проверить CSRF token handling - **Не везде добавляется**
- [✅] **APICALL-002**: Проверить Inertia.js CSRF integration - OK
- [⚠️] **APICALL-003**: Проверить CSRF для API calls (Sanctum) - Некоторые POST запросы без токена

### Request Security

- [✅] **APICALL-004**: Проверить что credentials включены правильно - `credentials: 'include'`
- [✅] **APICALL-005**: Проверить content-type headers - OK
- [🔴] **APICALL-006**: Проверить request timeout handling - **ОТСУТСТВУЕТ**

### Response Handling

- [🔴] **APICALL-007**: Проверить error response handling - **Утечка sensitive info**
- [⚠️] **APICALL-008**: Проверить JSON parsing safety - Нет проверки Content-Type
- [✅] **APICALL-009**: Проверить redirect handling - OK

### Data Exposure

- [🔴] **APICALL-010**: Проверить что API responses не кэшируются небезопасно - **SQL history в localStorage**
- [✅] **APICALL-011**: Проверить browser history - нет sensitive data в URLs
- [⚠️] **APICALL-012**: Проверить console.log - Некоторые ошибки логируются

### API Error Handling

- [✅] **APICALL-013**: Проверить 401/403 handling - redirect to login
- [✅] **APICALL-014**: Проверить network error handling - OK
- [⚠️] **APICALL-015**: Проверить rate limit error handling - Не обрабатывается

### Hooks Security

- [⚠️] **APICALL-016**: Проверить useApplications hook - Ошибки раскрываются
- [⚠️] **APICALL-017**: Проверить useServers hook - Ошибки раскрываются
- [⚠️] **APICALL-018**: Проверить useDeployments hook - Ошибки раскрываются
- [⚠️] **APICALL-019**: Проверить useDatabases hook - Ошибки раскрываются
- [⚠️] **APICALL-020**: Проверить useServices hook - Ошибки раскрываются

### File Upload Handling

- [✅] **APICALL-021**: Проверить FormData construction - OK
- [✅] **APICALL-022**: Проверить upload progress handling - OK
- [✅] **APICALL-023**: Проверить file validation на клиенте - OK

---

## Findings

### Критические (0)

Нет критических уязвимостей.

---

### Важные (2)

#### [API-HIGH-001] 🟡 Утечка чувствительных данных в сообщениях об ошибках

**Файлы:**
- `resources/js/hooks/useDeployments.ts` (линии 71, 100, 127, 190, 215)
- `resources/js/hooks/useServers.ts` (линии 112, 137, 202, 227, 249, 269, 336, 396)
- `resources/js/hooks/useDatabases.ts` (линии 116, 141, 206, 231, 253, 274, 295, 316, 380, 404, 425)
- `resources/js/hooks/useApplications.ts` (линии 58, 120, 147, 171, 194, 217)

**Проблема:**
```typescript
throw new Error(`Failed to fetch applications: ${response.statusText}`);
addToast('error', `Server returned ${response.status}`);
addToast('error', `Query failed: ${errorMessage}`);
```

**Severity:** HIGH

**Описание:** Ошибки отправляются напрямую из `response.json()` без фильтрации, что раскрывает:
- Детали backend архитектуры
- SQL ошибки с чувствительной информацией
- Пути до файлов
- Версии технологий

**Рекомендация:**
```typescript
const userFriendlyError = getErrorMessage(response.status);
throw new Error(userFriendlyError);

if (process.env.NODE_ENV === 'development') {
    console.error('API Error:', data.error);
}
```

---

#### [API-HIGH-002] 🟡 Чувствительные данные в localStorage

**Файл:** `resources/js/pages/Databases/Query.tsx` (линии 59, 80)

**Проблема:**
```typescript
const stored = localStorage.getItem(`${HISTORY_KEY}_${database.uuid}`);
localStorage.setItem(`${HISTORY_KEY}_${database.uuid}`, JSON.stringify(updated));
```

**Severity:** HIGH

**Описание:** История SQL запросов хранится в localStorage:
- SQL запросы с WHERE условиями
- Значения данных из таблиц
- Чувствительные операции на БД

localStorage доступна для любого скрипта на странице, включая XSS.

**Рекомендация:** Использовать sessionStorage или не хранить SQL запросы.

---

### Средний приоритет (5)

#### [API-MEDIUM-001] ⚠️ CSRF токены не добавляются последовательно

**Файлы с отсутствующими CSRF токенами:**
- `resources/js/hooks/useDatabases.ts`
- `resources/js/hooks/useServices.ts`
- `resources/js/hooks/useProjects.ts`

**Severity:** MEDIUM

**Рекомендация:**
```typescript
function getCSRFToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

const response = await fetch(url, {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': getCSRFToken(),
    },
});
```

---

#### [API-MEDIUM-002] ⚠️ Отсутствие проверки Content-Type в API ответах

**Все fetch запросы**

**Проблема:**
```typescript
const data = await response.json(); // Предполагается JSON, но не проверяется
```

**Severity:** MEDIUM

**Рекомендация:**
```typescript
const contentType = response.headers.get('content-type');
if (!contentType?.includes('application/json')) {
    throw new Error('Invalid response type');
}
```

---

#### [API-MEDIUM-003] ⚠️ XSS через dangerouslySetInnerHTML

**Файлы:**
- `resources/js/pages/Admin/Templates/Index.tsx` (линии 407, 412)
- `resources/js/pages/Auth/TwoFactor/Setup.tsx` (линия 173)

**Severity:** MEDIUM

---

#### [API-MEDIUM-004] ⚠️ Отсутствие валидации URL параметров

**Файл:** `resources/js/hooks/useGitBranches.ts` (линия 75)

**Severity:** MEDIUM

---

#### [API-MEDIUM-005] ⚠️ WebSocket info leak

**Файлы:**
- `resources/js/hooks/useLogStream.ts`
- `resources/js/hooks/useRealtimeStatus.ts`

**Severity:** MEDIUM

---

### Низкий приоритет (3)

#### [API-LOW-001] localStorage без проверки доступности

**Файл:** `resources/js/hooks/useAutoScroll.ts` (линия 130, 146)

---

#### [API-LOW-002] Отсутствие timeout для fetch запросов

Все fetch запросы без timeout.

---

#### [API-LOW-003] Rate limit errors не обрабатываются

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| API-HIGH-001 | Утечка ошибок | ⏳ Pending | - |
| API-HIGH-002 | Данные в localStorage | ⏳ Pending | - |
| API-MEDIUM-001 | CSRF токены | ⏳ Pending | - |

---

## Рекомендуемый план действий

### IMMEDIATE (следующий sprint)
- Переделать обработку ошибок (HIGH)
- Убрать SQL запросы из localStorage (HIGH)
- Добавить CSRF токены везде (MEDIUM)

### SHORT TERM (2-3 недели)
- Добавить валидацию входных данных (MEDIUM)
- Улучшить XSS защиту (MEDIUM)
- Добавить Content-Type проверку (MEDIUM)

### MEDIUM TERM (месяц)
- Реализовать fetch timeout wrapper (LOW)
- Улучшить localStorage handling (LOW)
