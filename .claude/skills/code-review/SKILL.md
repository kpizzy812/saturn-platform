---
name: code-review
description: Use when reviewing code, PRs, or checking code quality in Saturn Platform
allowed-tools: Read, Grep, Glob, Bash
disable-model-invocation: true
---

# Saturn Code Review Checklist

Review $ARGUMENTS for the following criteria:

## Security (Critical)
- [ ] No hardcoded secrets, API keys, or passwords
- [ ] Input validation on all user inputs
- [ ] SQL injection prevention (use Eloquent, no raw queries with user input)
- [ ] XSS prevention (escape output, use Blade {{ }})
- [ ] CSRF protection on forms
- [ ] Authorization checks (Policies, Gates)
- [ ] No sensitive data in logs

## Laravel/PHP Quality
- [ ] Uses Action pattern for business logic (app/Actions/)
- [ ] Team-scoped queries with `ownedByCurrentTeamCached()`
- [ ] Proper use of Jobs for async operations
- [ ] Events for WebSocket updates
- [ ] No N+1 queries (use eager loading)
- [ ] Proper error handling with try/catch
- [ ] Type hints on methods

## React/TypeScript Quality
- [ ] Proper TypeScript types (no `any`)
- [ ] Components are reasonably sized
- [ ] Hooks follow rules (no conditional hooks)
- [ ] Proper error boundaries
- [ ] Accessibility (ARIA labels, semantic HTML)
- [ ] No console.log in production code

## Code Style
- [ ] Follows PSR-12 for PHP
- [ ] Meaningful variable/function names
- [ ] No dead/commented code
- [ ] Comments explain "why" not "what"
- [ ] DRY - no code duplication

## Testing
- [ ] Unit tests for business logic
- [ ] Feature tests for API endpoints
- [ ] Tests cover edge cases
- [ ] Mocks external services

## Performance
- [ ] No unnecessary database queries
- [ ] Proper caching where applicable
- [ ] No memory leaks in React
- [ ] Large lists are paginated

## Output Format

Provide review in this format:

### 🔴 Critical Issues (Must Fix)
- Issue description with file:line reference

### 🟡 Warnings (Should Fix)
- Issue description with file:line reference

### 🟢 Suggestions (Nice to Have)
- Improvement suggestion

### ✅ Good Practices Observed
- Positive observations

### Summary
Brief overall assessment and recommendation (approve/request changes)
