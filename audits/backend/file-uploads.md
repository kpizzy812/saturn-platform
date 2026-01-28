# Backend File Uploads Security Audit

**Приоритет:** 🟡 High
**Статус:** [ ] Не начато

---

## Обзор

Проверка механизмов загрузки файлов.

### Ключевые файлы для проверки:

- `app/Http/Controllers/UploadController.php`
- `config/filesystems.php`
- Upload handling в других controllers

---

## Гипотезы для проверки

### File Type Validation

- [ ] **UPLOAD-001**: Проверить MIME type validation (не только extension)
- [ ] **UPLOAD-002**: Проверить magic bytes validation
- [ ] **UPLOAD-003**: Проверить whitelist allowed file types
- [ ] **UPLOAD-004**: Проверить блокировку исполняемых файлов

### File Size Limits

- [ ] **UPLOAD-005**: Проверить max file size configuration
- [ ] **UPLOAD-006**: Проверить PHP upload limits vs app limits
- [ ] **UPLOAD-007**: Проверить chunked upload handling

### File Storage

- [ ] **UPLOAD-008**: Проверить storage path - outside webroot
- [ ] **UPLOAD-009**: Проверить file permissions после upload
- [ ] **UPLOAD-010**: Проверить filename sanitization
- [ ] **UPLOAD-011**: Проверить path traversal protection (../)
- [ ] **UPLOAD-012**: Проверить unique filename generation

### File Processing

- [ ] **UPLOAD-013**: Проверить image processing - нет code execution
- [ ] **UPLOAD-014**: Проверить archive extraction - zip slip vulnerability
- [ ] **UPLOAD-015**: Проверить SSH key upload validation
- [ ] **UPLOAD-016**: Проверить docker-compose upload validation

### File Access

- [ ] **UPLOAD-017**: Проверить authorization при download
- [ ] **UPLOAD-018**: Проверить signed URLs для file access
- [ ] **UPLOAD-019**: Проверить temporary file cleanup

### Specific Upload Scenarios

- [ ] **UPLOAD-020**: Docker Compose file upload - YAML parsing safety
- [ ] **UPLOAD-021**: Private key upload - format validation
- [ ] **UPLOAD-022**: Environment file upload - parsing safety
- [ ] **UPLOAD-023**: Backup file upload для restore

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
