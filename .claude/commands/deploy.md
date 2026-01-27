---
description: Commit and push changes to dev branch for deployment to VPS
---

# Deploy Saturn to Dev

Arguments: $ARGUMENTS (optional commit message)

## Pre-deployment Checklist

1. **Verify we're on dev branch:**
   ```bash
   git branch --show-current
   ```
   If not on `dev`, switch to it or warn user.

2. **Run linters:**
   ```bash
   ./vendor/bin/pint --test
   ./vendor/bin/phpstan analyse --memory-limit=512M
   ```
   Stop if critical errors found.

3. **Run tests:**
   ```bash
   docker exec saturn php artisan test
   ```
   Stop if tests fail.

4. **Check for uncommitted changes:**
   ```bash
   git status
   ```

## Deployment Steps

5. **Stage all changes:**
   ```bash
   git add -A
   ```

6. **Create commit:**
   - Use `$ARGUMENTS` as commit message if provided
   - Otherwise, generate descriptive message from changes
   ```bash
   git commit -m "<message>

   Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
   ```

7. **Push to dev:**
   ```bash
   git push origin dev
   ```

8. **Confirm deployment:**
   Report that code has been pushed to dev branch and will be deployed to VPS automatically.

## IMPORTANT
- Always run tests before deploying
- Never deploy to main/master directly
- If tests fail, fix issues first before deploying
- This command automates the deployment workflow as specified in CLAUDE.md
