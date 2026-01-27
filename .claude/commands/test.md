---
description: Run tests for Saturn Platform
---

# Run Saturn Tests

Arguments: $ARGUMENTS

## Test Execution Strategy

1. **Determine test type from arguments:**
   - If `$ARGUMENTS` contains "unit" → Run unit tests locally
   - If `$ARGUMENTS` contains "feature" → Run feature tests in Docker
   - If `$ARGUMENTS` contains specific file path → Detect type from path
   - If `$ARGUMENTS` is empty → Run all tests in Docker

2. **Run appropriate command:**

   For Unit tests (no database needed):
   ```bash
   ./vendor/bin/pest tests/Unit $ARGUMENTS
   ```

   For Feature tests (MUST use Docker):
   ```bash
   docker exec saturn php artisan test tests/Feature $ARGUMENTS
   ```

   For all tests:
   ```bash
   docker exec saturn php artisan test
   ```

   For specific test file:
   ```bash
   # If path contains tests/Unit/
   ./vendor/bin/pest <path>

   # If path contains tests/Feature/
   docker exec saturn php artisan test --filter=<TestName>
   ```

3. **Frontend tests** (if `$ARGUMENTS` contains "frontend" or "js" or "react"):
   ```bash
   npm run test
   ```

4. **Report results** with summary of passed/failed tests.

## IMPORTANT
- NEVER run Feature tests outside Docker - they will fail with database connection errors
- Unit tests can run locally with `./vendor/bin/pest`
