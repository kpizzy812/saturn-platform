# Infrastructure Secrets Management Audit

**Приоритет:** 🔴 Critical
**Статус:** [x] Проверено

---

## Обзор

Проверка управления секретами во всей инфраструктуре.

### Ключевые файлы для проверки:

- `.env.example`
- `config/*.php`
- `app/Models/` (credential storage)
- Deployment scripts

---

## Гипотезы для проверки

### Application Secrets

- [x] **SECRET-001**: Проверить APP_KEY rotation mechanism - ⚠️ No auto-rotation
- [x] **SECRET-002**: Проверить database credentials storage - ✅ OK (.env)
- [x] **SECRET-003**: Проверить Redis password configuration - ⚠️ Not encrypted in DB
- [x] **SECRET-004**: Проверить mail credentials storage - ✅ OK (encrypted)

### SSH Keys

- [x] **SECRET-005**: Проверить private key encryption at rest - ✅ EXCELLENT
- [x] **SECRET-006**: Проверить key generation security - ✅ OK (phpseclib3)
- [x] **SECRET-007**: Проверить temporary key file handling - ⚠️ Needs cleanup
- [x] **SECRET-008**: Проверить key passphrase support - ⚠️ Not implemented

### Database Credentials

- [x] **SECRET-009**: Проверить database password generation (entropy) - ✅ OK
- [x] **SECRET-010**: Проверить password storage encryption - ✅ EXCELLENT
- [x] **SECRET-011**: Проверить credential rotation support - ⚠️ Not implemented

### API Keys & Tokens

- [x] **SECRET-012**: Проверить API token hashing - ✅ OK (Sanctum)
- [x] **SECRET-013**: Проверить OAuth tokens encryption - ✅ OK
- [x] **SECRET-014**: Проверить external service credentials - 🔴 NOT ENCRYPTED

### S3/Storage Credentials

- [x] **SECRET-015**: Проверить S3 access keys storage - ✅ OK (encrypted)
- [x] **SECRET-016**: Проверить backup storage credentials - ✅ OK

### Webhook Secrets

- [x] **SECRET-017**: Проверить webhook signing secrets storage - ✅ OK (encrypted)
- [x] **SECRET-018**: Проверить secret generation randomness - ✅ OK

### Environment Variables

- [x] **SECRET-019**: Проверить .env file permissions - ✅ OK (600)
- [x] **SECRET-020**: Проверить .env не в git - ✅ OK (.gitignore)
- [x] **SECRET-021**: Проверить environment isolation (dev/prod) - ✅ OK

### Encryption

- [x] **SECRET-022**: Проверить Laravel encryption configuration - ✅ OK
- [x] **SECRET-023**: Проверить cipher algorithm (AES-256-CBC/GCM) - ⚠️ CBC, not GCM
- [x] **SECRET-024**: Проверить key derivation - ✅ OK

### Secret Exposure Prevention

- [x] **SECRET-025**: Проверить debug mode disabled в production - ✅ OK
- [x] **SECRET-026**: Проверить error pages - нет secret exposure - ✅ OK
- [x] **SECRET-027**: Проверить logs - secrets не логируются - 🔴 No masking
- [x] **SECRET-028**: Проверить stack traces filtering - ⚠️ Partial

### Backup Security

- [x] **SECRET-029**: Проверить backup encryption - 🔴 NOT IMPLEMENTED
- [x] **SECRET-030**: Проверить backup access control - ⚠️ Partial
- [x] **SECRET-031**: Проверить backup storage security - ✅ OK

---

## Findings

### Критические

#### SECRET-001-F: GitHub/GitLab App Secrets Not Encrypted

**Файлы:**
- `app/Models/GithubApp.php`
- `app/Models/GitlabApp.php`

**Проблема:**
```php
// GithubApp.php
protected $hidden = [
    'client_secret',
    'webhook_secret',
];
// Но нет encrypted cast! Хранятся в plaintext в БД
```

Secrets stored in plaintext in database. Only hidden from serialization, but visible to anyone with database access.

**Severity:** 🔴 Critical

**Fix:**
```php
protected $casts = [
    'client_secret' => 'encrypted',
    'webhook_secret' => 'encrypted',
];
```

#### SECRET-002-F: Log Secret Masking Missing

**Файл:** `config/logging.php`

**Проблема:**
No middleware or processor to mask passwords, tokens, API keys in logs. Sentry SQL bindings may expose sensitive data.

**Severity:** 🔴 Critical

**Recommendation:**
- Add custom log processor to mask patterns: `password=***`, `token=***`, `api_key=***`
- Review Sentry `send_default_pii` and SQL binding settings

#### SECRET-003-F: Backup Encryption Not Implemented

**Файл:** `app/Models/ScheduledDatabaseBackup.php`

**Проблема:**
Database backups uploaded to S3 without encryption. Anyone with S3 access can read full database contents.

**Severity:** 🔴 Critical

**Fix:**
- Encrypt backup files before S3 upload
- Use separate encryption key for backups
- Implement decryption for restore

### Важные

#### SECRET-004-F: No API Token Expiration Policy

**Файл:** `config/sanctum.php:49`

```php
'expiration' => null,
```

Tokens never expire by default. Stolen token works forever.

**Severity:** 🟠 High

**Fix:** Set reasonable expiration (e.g., 30 days):
```php
'expiration' => 60 * 24 * 30, // 30 days in minutes
```

#### SECRET-005-F: No Credential Rotation Mechanism

**Проблема:**
No automated mechanism for rotating:
- Database passwords
- API tokens
- Service credentials

**Severity:** 🟠 High

**Recommendation:**
- Implement rotation API endpoints
- Add scheduled rotation reminders
- Document manual rotation procedures

#### SECRET-006-F: SSH Key Passphrase Support Missing

**Файл:** `app/Models/PrivateKey.php`

Keys stored without passphrase protection. Once decrypted, key is accessible.

**Severity:** 🟡 Medium

#### SECRET-007-F: AES-256-CBC Instead of GCM

**Файл:** `config/app.php`

```php
'cipher' => 'AES-256-CBC',
```

CBC provides confidentiality but not authentication. GCM provides both.

**Severity:** 🟡 Medium

### Низкий приоритет

#### SECRET-008-F: Sentry Bindings Logging

Sentry configuration may log SQL bindings which could contain sensitive data.

**Severity:** 🟡 Low (internal service)

---

## Положительные находки

| Area | Implementation | Status |
|------|----------------|--------|
| SSH Private Keys | Encrypted + filesystem (0700) | ✅ Excellent |
| Database Passwords | All encrypted casts | ✅ Excellent |
| Mail Credentials | All encrypted | ✅ OK |
| S3 Credentials | Encrypted casts | ✅ OK |
| TeamWebhook Secrets | Encrypted casts | ✅ OK |
| OAuth Secrets | Custom encryption | ✅ OK |
| .env Security | Gitignored + 600 permissions | ✅ OK |
| Password Generation | Str::password() with high entropy | ✅ OK |
| API Token Hashing | Sanctum 64-char hash | ✅ OK |
| Debug Mode | Disabled in production | ✅ OK |

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| SECRET-001-F | Encrypt GitHub/GitLab app secrets | ✅ FIXED | c8ad4e1 |
| SECRET-002-F | Add log secret masking | 🔧 To Fix | - |
| SECRET-003-F | Implement backup encryption | 🔧 To Fix | - |
| SECRET-004-F | Add API token expiration | 🔧 To Fix | - |
| SECRET-005-F | Implement credential rotation | 🔧 To Fix | - |
| SECRET-006-F | Add SSH key passphrase support | ⏳ Low Priority | - |
| SECRET-007-F | Consider AES-256-GCM | ⏳ Low Priority | - |

---

## Рекомендации

### Немедленные действия (Week 1)

1. **Encrypt GitHub/GitLab secrets**
   ```php
   // Migration
   Schema::table('github_apps', function (Blueprint $table) {
       // Add temp columns, encrypt, rename
   });

   // Model
   protected $casts = [
       'client_secret' => 'encrypted',
       'webhook_secret' => 'encrypted',
   ];
   ```

2. **Add log masking middleware**
   ```php
   // Custom log processor
   class SecretMaskingProcessor {
       public function __invoke(array $record): array {
           $record['message'] = preg_replace(
               '/(password|token|secret|key)=[^\s&]+/i',
               '$1=***MASKED***',
               $record['message']
           );
           return $record;
       }
   }
   ```

3. **Add API token expiration**
   ```php
   // config/sanctum.php
   'expiration' => 60 * 24 * 30, // 30 days
   ```

### Краткосрочные действия (Month 1)

4. **Implement backup encryption**
   - Encrypt with separate key before S3 upload
   - Store encryption key securely
   - Implement decryption for restore

5. **Document rotation procedures**
   - APP_KEY rotation
   - Database credential rotation
   - API token refresh

### Долгосрочные улучшения

6. **Credential rotation automation**
7. **SSH key passphrase support**
8. **AES-256-GCM migration** (requires Laravel upgrade evaluation)

---

## Заметки аудитора

### Проверено 2024-01-30

**Overall Assessment:** Good foundational security with critical gaps in GitHub/GitLab credentials and backup encryption.

**Security Score:** B-

| Category | Score |
|----------|-------|
| SSH Key Protection | A+ |
| Database Credentials | A |
| Mail Credentials | A |
| S3 Credentials | A |
| Webhook Secrets | A |
| GitHub/GitLab Secrets | F |
| Backup Encryption | F |
| Log Masking | D |
| Token Expiration | C |

### Приоритет исправлений

1. **Critical:** Encrypt GitHub/GitLab secrets (easy fix, high impact)
2. **Critical:** Add log masking (moderate effort)
3. **Critical:** Implement backup encryption (high effort)
4. **High:** Set token expiration (easy fix)
5. **Medium:** Document rotation procedures
