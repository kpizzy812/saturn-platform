---
description: Run linters and code quality checks for Saturn Platform
---

# Run Saturn Linters

Arguments: $ARGUMENTS

## Linting Strategy

Run the following checks based on arguments:

### If `$ARGUMENTS` is empty - run all checks:

1. **PHP Formatting (Pint)**
   ```bash
   ./vendor/bin/pint --test
   ```
   If issues found, ask user if they want to auto-fix with `./vendor/bin/pint`

2. **PHP Static Analysis (PHPStan)**
   ```bash
   ./vendor/bin/phpstan analyse --memory-limit=512M
   ```

3. **TypeScript/JavaScript (ESLint + TypeCheck)**
   ```bash
   npm run lint 2>/dev/null || npx eslint resources/js --ext .ts,.tsx
   npm run typecheck 2>/dev/null || npx tsc --noEmit
   ```

### Specific checks:

- `$ARGUMENTS` = "php" → Run only PHP checks (Pint + PHPStan)
- `$ARGUMENTS` = "js" or "ts" → Run only JS/TS checks (ESLint + TypeCheck)
- `$ARGUMENTS` = "fix" → Auto-fix all fixable issues:
  ```bash
  ./vendor/bin/pint
  npx eslint resources/js --ext .ts,.tsx --fix
  ```

### Rector (Optional refactoring suggestions)
If `$ARGUMENTS` contains "rector":
```bash
./vendor/bin/rector process --dry-run
```

## Output Format

Summarize findings:
- ✅ Passed checks
- ❌ Failed checks with details
- 🔧 Auto-fixable issues

If there are failures, provide specific file:line references and suggestions.
