# Backend File Uploads Security Audit

**Приоритет:** 🟡 High
**Статус:** [🔍] Проверено, найдены критические проблемы
**Дата аудита:** 2026-01-30

---

## Резюме уязвимостей

| Severity | Количество | Примеры |
|----------|-----------|---------|
| CRITICAL | 3 | Нет валидации MIME, Path traversal, Небезопасное расширение |
| HIGH | 4 | Нет авторизации, 256MB лимит, Shell injection, Path checks |
| MEDIUM | 3 | Integrity check, Temp files, Filename header |

---

## Гипотезы для проверки

### File Type Validation

- [🔴] **UPLOAD-001**: Проверить MIME type validation - **ОТСУТСТВУЕТ!**
- [🔴] **UPLOAD-002**: Проверить magic bytes validation - **ОТСУТСТВУЕТ!**
- [🔴] **UPLOAD-003**: Проверить whitelist allowed file types - **ОТСУТСТВУЕТ!**
- [🔴] **UPLOAD-004**: Проверить блокировку исполняемых файлов - **НЕТ БЛОКИРОВКИ!**

### File Size Limits

- [⚠️] **UPLOAD-005**: Проверить max file size configuration - 256MB (слишком много)
- [⚠️] **UPLOAD-006**: Проверить PHP upload limits vs app limits - OK но большие
- [✅] **UPLOAD-007**: Проверить chunked upload handling - OK (pion/laravel-chunk-upload)

### File Storage

- [✅] **UPLOAD-008**: Проверить storage path - outside webroot - OK
- [✅] **UPLOAD-009**: Проверить file permissions после upload - OK
- [🔴] **UPLOAD-010**: Проверить filename sanitization - **НЕТ САНИТИЗАЦИИ!**
- [🔴] **UPLOAD-011**: Проверить path traversal protection - **НЕДОСТАТОЧНО!**
- [✅] **UPLOAD-012**: Проверить unique filename generation - md5(time())

### File Processing

- [✅] **UPLOAD-013**: Проверить image processing - N/A
- [✅] **UPLOAD-014**: Проверить archive extraction - N/A
- [✅] **UPLOAD-015**: Проверить SSH key upload validation - validateSSHKey()
- [✅] **UPLOAD-016**: Проверить docker-compose upload validation - validateDockerCompose()

### File Access

- [🔴] **UPLOAD-017**: Проверить authorization при download - **НЕ ЯВНАЯ!**
- [✅] **UPLOAD-018**: Проверить signed URLs для file access - N/A
- [⚠️] **UPLOAD-019**: Проверить temporary file cleanup - Зависит от GC

### Specific Upload Scenarios

- [✅] **UPLOAD-020**: Docker Compose file upload - OK (YAML validation)
- [✅] **UPLOAD-021**: Private key upload - OK (format validation)
- [✅] **UPLOAD-022**: Environment file upload - OK
- [🔴] **UPLOAD-023**: Backup file upload - **Shell injection в restore!**

---

## Findings

### Критические (3)

#### [UPLOAD-CRITICAL-001] 🔴 Отсутствие валидации типа файла

**Файл:** `app/Http/Controllers/UploadController.php` (строки 14-38, 60-70)

**Проблема:**
```php
public function saveFile(UploadedFile $file, $resource)
{
    $mime = str_replace('/', '-', $file->getMimeType()); // Только замена!
    $filePath = "upload/{$resource->uuid}";
    $file->move($finalPath, 'restore');  // Без валидации!
}
```

**Severity:** CRITICAL

**Риск:** Загрузка PHP, Exe, Shell-скриптов

**Рекомендация:**
```php
'file' => 'required|file|mimes:sql,gz,tar,zip|max:256000'
```

---

#### [UPLOAD-CRITICAL-002] 🔴 Небезопасное расширение файла

**Файл:** `app/Http/Controllers/UploadController.php` (строки 72-80)

**Проблема:**
```php
protected function createFilename(UploadedFile $file)
{
    $extension = $file->getClientOriginalExtension(); // Может быть подделана!
    $filename .= '_'.md5(time()).'.'.$extension; // Расширение не проверено!
}
```

**Severity:** CRITICAL

**Рекомендация:**
```php
$allowedExtensions = ['sql', 'gz', 'tar', 'zip'];
$ext = strtolower($file->getClientOriginalExtension());
if (!in_array($ext, $allowedExtensions)) {
    throw new Exception('Invalid file extension');
}
```

---

#### [UPLOAD-CRITICAL-003] 🔴 Path Traversal при сохранении

**Файл:** `app/Http/Controllers/UploadController.php` (строки 60-70)

**Проблема:**
```php
$filePath = "upload/{$resource->uuid}";  // Может содержать ../
$finalPath = storage_path('app/'.$filePath); // Без canonicalization
$file->move($finalPath, 'restore');
```

**Severity:** CRITICAL

**Рекомендация:**
```php
if (!uuid_is_valid($resource->uuid)) {
    throw new Exception('Invalid resource UUID');
}

$basePath = realpath(storage_path('app/upload'));
$finalPath = realpath($finalPath);

if (!$finalPath || strpos($finalPath, $basePath) !== 0) {
    throw new Exception('Invalid file path');
}
```

---

### Важные (4)

#### [UPLOAD-HIGH-001] 🟡 Отсутствие авторизации

**Файл:** `app/Http/Controllers/UploadController.php` (строки 14-25)

**Проблема:**
```php
public function upload(Request $request)
{
    $resource = getResourceByUuid(...);
    if (is_null($resource)) {
        return response()->json(['error' => '...'], 500);
    }
    // Нет явного $this->authorize()!
}
```

**Severity:** HIGH

**Рекомендация:** Добавить `$this->authorize('update', $resource);`

---

#### [UPLOAD-HIGH-002] 🟡 256MB лимит на загрузку

**Файлы:**
- `docker/production/etc/php/conf.d/zzz-custom-php.ini` (строки 8-9)
- `config/livewire.php` (строка 57)

```ini
upload_max_filesize = 256M
post_max_size = 256M
```

**Severity:** HIGH

**Риски:** DoS, исчерпание диска

---

#### [UPLOAD-HIGH-003] 🟡 Shell injection в restore команде

**Файл:** `app/Livewire/Project/Database/Import.php` (строки 109-139)

**Проблема:**
```php
return "{$this->mysqlRestoreCommand} < {$filePath}"; // Без escaping!
```

**Severity:** HIGH

**Рекомендация:**
```php
$escapedPath = escapeshellarg($filePath);
return "{$this->mysqlRestoreCommand} < {$escapedPath}";
```

---

#### [UPLOAD-HIGH-004] 🟡 Недостаточные path checks

**Файл:** `app/Livewire/Project/Database/Import.php` (строки 86-104)

**Проблема:**
```php
if (str_contains($path, '..')) { // Слишком простая проверка!
    return false;
}
```

**Severity:** HIGH

**Рекомендация:** Проверка на symlinks, encoding attacks

---

### Средний приоритет (3)

#### [UPLOAD-MEDIUM-001] ⚠️ Отсутствие проверки целостности

**Severity:** MEDIUM

---

#### [UPLOAD-MEDIUM-002] ⚠️ Временные файлы не удаляются

**Файл:** `config/livewire.php` (строки 54-67)

**Severity:** MEDIUM

---

#### [UPLOAD-MEDIUM-003] ⚠️ Filename injection в headers

**Файл:** `app/Http/Controllers/Api/TeamController.php` (строки 700-720)

**Severity:** MEDIUM

---

## Исправления

| ID | Описание | Статус | PR/Commit |
|----|----------|--------|-----------|
| UPLOAD-CRITICAL-001 | MIME validation | ⏳ Pending | - |
| UPLOAD-CRITICAL-002 | Extension validation | ⏳ Pending | - |
| UPLOAD-CRITICAL-003 | Path traversal | ⏳ Pending | - |
| UPLOAD-HIGH-001 | Authorization | ⏳ Pending | - |
| UPLOAD-HIGH-003 | Shell injection | ⏳ Pending | - |

---

## План действий

### Phase 1: CRITICAL (Неделя 1)
1. Добавить белый список MIME типов
2. Использовать `escapeshellarg()` во всех shell командах
3. Добавить явную авторизацию

### Phase 2: HIGH (Неделя 2)
1. Реализовать валидацию path traversal
2. Пересмотреть лимиты на загрузку
3. Добавить санитизацию filenames

### Phase 3: MEDIUM (Неделя 3)
1. Добавить проверку целостности
2. Автоудаление временных файлов
3. Content-Disposition санитизация

---

## Рекомендации по hardening

### PHP
```ini
disable_functions = "exec,system,passthru,shell_exec,proc_open"
open_basedir = /var/www/html:/tmp:/proc
display_errors = Off
```

### Nginx
```nginx
location ~* /storage/app/upload/ {
    location ~ \.php$ { return 403; }
}
```
