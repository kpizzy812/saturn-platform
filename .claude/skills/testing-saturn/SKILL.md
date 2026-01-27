---
name: testing-saturn
description: Use when writing tests, running tests, or debugging test failures in Saturn Platform
allowed-tools: Read, Write, Edit, Bash, Grep, Glob
---

# Saturn Testing Guidelines

## CRITICAL RULES

### Feature Tests MUST Run in Docker
Feature tests require database access and MUST run inside the Docker container:

```bash
# Run all tests in Docker
docker exec saturn php artisan test

# Run specific test
docker exec saturn php artisan test --filter=SomeTestName

# Run Feature tests only
docker exec saturn php artisan test tests/Feature
```

### Unit Tests Can Run Locally
Unit tests don't need database and can run outside Docker:

```bash
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Unit/SomeTest.php
```

## Testing Patterns

### Unit Test Structure (No Database)
```php
use Mockery;

test('example unit test', function () {
    // Use Mockery for models
    $server = Mockery::mock('App\Models\Server');
    $server->shouldReceive('proxyType')->andReturn('traefik');

    // Test your logic
    expect($result)->toBe($expected);
});
```

### Feature Test Structure (Docker Required)
```php
use App\Models\Server;

test('example feature test', function () {
    // Can use factories
    $server = Server::factory()->create(['ip' => '1.2.3.4']);

    // Test with real database
    expect($server)->toBeInstanceOf(Server::class);
});
```

## File Naming
- `tests/Unit/*.php` - Unit tests (no DB)
- `tests/Feature/*.php` - Feature tests (require DB/Docker)

## Frontend Tests
```bash
npm run test          # Run once
npm run test:ui       # With UI
npm run test:watch    # Watch mode
```

## When Writing Tests

1. Determine if test needs database → Feature test (Docker) or Unit test (local)
2. Use Mockery for external dependencies in Unit tests
3. Use factories for database models in Feature tests
4. Follow AAA pattern: Arrange, Act, Assert
5. One assertion per test when possible

## Common Assertions
```php
expect($value)->toBe($expected);
expect($value)->toBeTrue();
expect($value)->toBeNull();
expect($array)->toHaveCount(3);
expect($object)->toBeInstanceOf(SomeClass::class);
```

## Running Tests After Changes
After writing tests, always verify they pass:
```bash
# For Unit tests
./vendor/bin/pest tests/Unit/YourNewTest.php

# For Feature tests
docker exec saturn php artisan test --filter=YourNewTest
```
