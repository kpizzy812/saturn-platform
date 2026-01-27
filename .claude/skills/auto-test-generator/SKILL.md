---
name: auto-test-generator
description: Automatically generates tests when writing new functionality in Actions, Services, Controllers, Jobs, or React components
allowed-tools: Read, Write, Edit, Bash, Grep, Glob
---

# Auto Test Generator for Saturn Platform

When you create or modify files in these directories, AUTOMATICALLY generate corresponding tests:

## Trigger Directories

| Source Directory | Test Directory | Test Type |
|-----------------|----------------|-----------|
| `app/Actions/` | `tests/Unit/Actions/` | Unit |
| `app/Services/` | `tests/Unit/Services/` | Unit |
| `app/Jobs/` | `tests/Unit/Jobs/` | Unit |
| `app/Http/Controllers/Api/` | `tests/Feature/Api/` | Feature |
| `resources/js/components/` | `resources/js/__tests__/` | Jest |
| `resources/js/hooks/` | `resources/js/__tests__/hooks/` | Jest |

## PHP Test Template (Pest)

```php
<?php

// For Unit tests - use Mockery, no database
beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

it('describes what the test verifies', function () {
    // Arrange
    $mock = Mockery::mock('App\Models\SomeModel');
    $mock->shouldReceive('method')->andReturn('value');

    // Act
    $result = someFunction($mock);

    // Assert
    expect($result)->toBe($expected);
});

// For Feature tests - can use factories (MUST run in Docker)
it('tests an API endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/endpoint');

    $response->assertStatus(200);
});
```

## TypeScript Test Template (Vitest)

```typescript
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ComponentName } from './ComponentName';

describe('ComponentName', () => {
    it('renders correctly', () => {
        render(<ComponentName />);
        expect(screen.getByText('expected text')).toBeInTheDocument();
    });

    it('handles user interaction', async () => {
        const mockFn = vi.fn();
        render(<ComponentName onClick={mockFn} />);

        await userEvent.click(screen.getByRole('button'));
        expect(mockFn).toHaveBeenCalled();
    });
});
```

## Auto-Generation Process

When creating new functionality:

1. **Analyze the source file** - understand public methods, dependencies, edge cases

2. **Determine test type:**
   - No database needed → Unit test (can run locally)
   - Database/HTTP required → Feature test (Docker only)
   - React component → Vitest test

3. **Generate test file** with:
   - Test for each public method
   - Edge cases (null, empty, invalid input)
   - Error scenarios
   - Happy path scenarios

4. **File naming:**
   - PHP: `{ClassName}Test.php`
   - TypeScript: `{ComponentName}.test.tsx`

5. **Run the test** to verify it passes:
   ```bash
   # Unit test
   ./vendor/bin/pest tests/Unit/Path/ToNewTest.php

   # Feature test (Docker required)
   docker exec saturn php artisan test --filter=NewTestName

   # Frontend test
   npm run test -- path/to/test.test.tsx
   ```

## Test Coverage Requirements

For each new class/function, ensure tests cover:

- [ ] Normal operation (happy path)
- [ ] Null/empty inputs
- [ ] Invalid inputs (throw exceptions?)
- [ ] Boundary conditions
- [ ] All public methods

## Example: Action Class

Source: `app/Actions/Server/RestartServer.php`
```php
class RestartServer
{
    public function handle(Server $server): bool
    {
        // ...
    }
}
```

Generated: `tests/Unit/Actions/Server/RestartServerTest.php`
```php
<?php

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

it('restarts server successfully', function () {
    $server = Mockery::mock('App\Models\Server');
    $server->shouldReceive('restart')->once()->andReturn(true);

    $action = new \App\Actions\Server\RestartServer();
    $result = $action->handle($server);

    expect($result)->toBeTrue();
});

it('returns false when server restart fails', function () {
    $server = Mockery::mock('App\Models\Server');
    $server->shouldReceive('restart')->once()->andReturn(false);

    $action = new \App\Actions\Server\RestartServer();
    $result = $action->handle($server);

    expect($result)->toBeFalse();
});

it('throws exception for invalid server state', function () {
    $server = Mockery::mock('App\Models\Server');
    $server->shouldReceive('restart')->once()
        ->andThrow(new \Exception('Server not reachable'));

    $action = new \App\Actions\Server\RestartServer();

    expect(fn() => $action->handle($server))
        ->toThrow(\Exception::class, 'Server not reachable');
});
```

## IMPORTANT

- ALWAYS run tests after generating to ensure they pass
- Unit tests: `./vendor/bin/pest tests/Unit/...`
- Feature tests: `docker exec saturn php artisan test --filter=...`
- If test fails, fix it before moving on
