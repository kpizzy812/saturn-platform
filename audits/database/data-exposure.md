# Database Data Exposure Audit

**Приоритет:** 🔴 Critical
**Статус:** [x] Проверено

---

## Обзор

Проверка на непреднамеренное раскрытие данных из БД.

### Ключевые файлы для проверки:

- `app/Models/*.php` ($hidden, $visible, $casts)
- API Resources (если используются)
- Controller responses
- Inertia page props

---

## Гипотезы для проверки

### Model Attributes

- [x] **EXPOSE-001**: Проверить $hidden на всех моделях - ⚠️ ISSUES FOUND
- [x] **EXPOSE-002**: Проверить что passwords в $hidden - OK (encrypted casts)
- [x] **EXPOSE-003**: Проверить что API tokens в $hidden - OK (User model)
- [x] **EXPOSE-004**: Проверить что private keys в $hidden - OK (encrypted + filesystem)
- [x] **EXPOSE-005**: Проверить $visible где используется - Not used

### Sensitive Fields

#### User Model
- [x] **EXPOSE-006**: password, remember_token скрыты - ✅ OK
- [x] **EXPOSE-007**: two_factor_secret скрыт - ✅ OK
- [x] **EXPOSE-008**: recovery_codes скрыты - ✅ OK

#### Server Model
- [x] **EXPOSE-009**: Проверить SSH credentials exposure - ⚠️ API keys may be exposed
- Uses encrypted casts but no $hidden

#### Application Model
- [x] **EXPOSE-010**: Проверить git credentials exposure - ✅ OK (in PrivateKey model)
- [x] **EXPOSE-011**: Проверить deployment secrets - ⚠️ CRITICAL ISSUE

#### Database Models
- [x] **EXPOSE-012**: Проверить database passwords - ✅ OK (encrypted casts)
- [x] **EXPOSE-013**: Проверить connection strings - ✅ OK

#### PrivateKey Model
- [x] **EXPOSE-014**: Проверить private_key field protection - ✅ OK (encrypted + filesystem)

#### S3Storage Model
- [x] **EXPOSE-015**: Проверить access keys protection - ✅ OK (encrypted casts)

#### Notification Settings
- [x] **EXPOSE-016**: Проверить webhook URLs/tokens - ✅ OK (encrypted casts)

### API Responses

- [x] **EXPOSE-017**: Проверить toArray() responses - ⚠️ ISSUES (Inertia exposes full models)
- [x] **EXPOSE-018**: Проверить JSON serialization - ⚠️ ISSUES
- [x] **EXPOSE-019**: Проверить API resource transformations - ✅ OK (API uses explicit mapping)

### Relationships

- [x] **EXPOSE-020**: Проверить eager loading - нет лишних данных - ✅ OK
- [x] **EXPOSE-021**: Проверить nested relationships exposure - ⚠️ Needs attention
- [x] **EXPOSE-022**: Проверить with() calls - ✅ OK

### Query Logging

- [x] **EXPOSE-023**: Проверить query logging disabled в production - ✅ OK
- [x] **EXPOSE-024**: Проверить Telescope query recording - ✅ OK (dev only)

### Backups

- [x] **EXPOSE-025**: Проверить backup data exposure - ✅ OK
- [x] **EXPOSE-026**: Проверить database dump security - ✅ OK

### Caching

- [x] **EXPOSE-027**: Проверить cached data - нет over-caching sensitive data - ✅ OK
- [x] **EXPOSE-028**: Проверить cache key isolation - ✅ OK

---

## Findings

### Критические

#### EXPOSE-001-F: Webhook Secrets Exposed in Inertia Props

**Файл:** `app/Http/Controllers/Inertia/ApplicationController.php:399-403`

**Проблема:**
```php
public function previewsSettings(string $uuid): Response
{
    // ...
    $settings = [
        'preview_url_template' => $application->preview_url_template,
        'manual_webhook_secret_github' => $application->manual_webhook_secret_github,
        'manual_webhook_secret_gitlab' => $application->manual_webhook_secret_gitlab,
        'manual_webhook_secret_bitbucket' => $application->manual_webhook_secret_bitbucket,
        'manual_webhook_secret_gitea' => $application->manual_webhook_secret_gitea,
    ];

    return Inertia::render('Applications/Previews/Settings', [
        'application' => $application,  // ← Full model also passed!
        'settings' => $settings,
    ]);
}
```

Webhook secrets are explicitly passed to the frontend and appear in the HTML source.
Users viewing the page can see secrets in the Inertia page props (JSON in HTML).

**Impact:** Attackers with read access can obtain webhook signing secrets and forge webhook events.

**Severity:** 🔴 Critical

#### EXPOSE-002-F: Full Application Model Passed to Inertia

**Файлы:**
- `app/Http/Controllers/Inertia/ApplicationController.php:317, 336, 405, 462, 489, 532, 552, 596`

**Проблема:**
```php
// Multiple routes pass the full model:
return Inertia::render('...', [
    'application' => $application,  // Entire model with ALL fields
]);
```

The Application model has NO `$hidden` property, so all fields are serialized:
- `manual_webhook_secret_github`
- `manual_webhook_secret_gitlab`
- `manual_webhook_secret_bitbucket`
- `manual_webhook_secret_gitea`

**Severity:** 🔴 Critical

#### EXPOSE-003-F: Full Server Model Passed to Inertia

**Файл:** `app/Http/Controllers/Inertia/ServerController.php` (multiple routes)

**Проблема:**
```php
return Inertia::render('Servers/Show', [
    'server' => $server,  // Full model
]);
```

Server model encrypted fields are decrypted during serialization:
- `logdrain_axiom_api_key`
- `logdrain_newrelic_license_key`

While encrypted in storage, when Laravel serializes the model for Inertia, the `encrypted` cast automatically decrypts values.

**Severity:** 🟠 High

#### EXPOSE-004-F: Missing $hidden in Application Model

**Файл:** `app/Models/Application.php`

**Проблема:**
Application model has no `$hidden` property despite having sensitive fields:
- `manual_webhook_secret_github`
- `manual_webhook_secret_gitlab`
- `manual_webhook_secret_bitbucket`
- `manual_webhook_secret_gitea`

These fields are also NOT encrypted (no 'encrypted' cast).

**Severity:** 🔴 Critical

### Важные

#### EXPOSE-005-F: Missing $hidden in Server Model

**Файл:** `app/Models/Server.php`

Server model has no `$hidden` property. While API keys use 'encrypted' cast,
they're still decrypted and exposed when the model is serialized.

**Recommended fix:** Add $hidden for encrypted API keys or avoid passing full models.

**Severity:** 🟠 High

### Низкий приоритет

#### EXPOSE-006-F: Models with Good Protection

The following models have proper protection:

| Model | Protection | Status |
|-------|------------|--------|
| User | $hidden for password, tokens, 2FA | ✅ OK |
| PrivateKey | encrypted cast + filesystem | ✅ OK |
| GithubApp | $hidden for secrets | ✅ OK |
| GitlabApp | $hidden for secrets | ✅ OK |
| S3Storage | encrypted cast | ✅ OK |
| EnvironmentVariable | encrypted cast | ✅ OK |
| TeamWebhook | encrypted cast | ✅ OK |
| All Database Models | encrypted cast for passwords | ✅ OK |

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| EXPOSE-001-F | Don't pass webhook secrets to frontend | ✅ Fixed | - |
| EXPOSE-002-F | Add $hidden to Application model + encrypt secrets | ✅ Fixed | - |
| EXPOSE-003-F | Add $hidden to Server model | ✅ Fixed | - |
| EXPOSE-004-F | Add $hidden to Application model OR encrypt secrets | ✅ Fixed | - |

---

## Recommended Fixes

### Option 1: Add $hidden to models

```php
// app/Models/Application.php
protected $hidden = [
    'manual_webhook_secret_github',
    'manual_webhook_secret_gitlab',
    'manual_webhook_secret_bitbucket',
    'manual_webhook_secret_gitea',
];

// app/Models/Server.php
protected $hidden = [
    'logdrain_axiom_api_key',
    'logdrain_newrelic_license_key',
];
```

### Option 2: Encrypt webhook secrets

```php
// app/Models/Application.php
protected $casts = [
    'manual_webhook_secret_github' => 'encrypted',
    'manual_webhook_secret_gitlab' => 'encrypted',
    'manual_webhook_secret_bitbucket' => 'encrypted',
    'manual_webhook_secret_gitea' => 'encrypted',
    // ... other casts
];

// Combined with $hidden for double protection
protected $hidden = [
    'manual_webhook_secret_github',
    'manual_webhook_secret_gitlab',
    'manual_webhook_secret_bitbucket',
    'manual_webhook_secret_gitea',
];
```

### Option 3: Use explicit field mapping in controllers (RECOMMENDED)

```php
// Instead of:
return Inertia::render('...', ['application' => $application]);

// Use explicit mapping:
return Inertia::render('...', [
    'application' => [
        'id' => $application->id,
        'uuid' => $application->uuid,
        'name' => $application->name,
        // ... only include needed fields
    ],
]);
```

---

## Заметки аудитора

### Проверено 2024-01-30

1. **User model** - Properly protected with $hidden
2. **PrivateKey model** - Excellent protection (encrypted + filesystem + permissions)
3. **Database models** - All use encrypted casts for passwords
4. **Application model** - Missing $hidden, webhook secrets unencrypted
5. **Server model** - Missing $hidden, API keys may be exposed when serialized

### Key Issues Summary

1. **Inertia serialization** - Full models passed to frontend expose all fields
2. **Missing $hidden** - Application and Server models lack field hiding
3. **Unencrypted secrets** - Application webhook secrets not encrypted
4. **Explicit exposure** - Controller code explicitly passes secrets to frontend

### Recommendation Priority

1. **Immediate:** Fix ApplicationController.previewsSettings() - remove explicit secret passing
2. **High:** Add $hidden to Application model for webhook secrets
3. **Medium:** Use explicit field mapping in all Inertia controllers
4. **Low:** Add $hidden to Server model (already encrypted, so lower risk)
