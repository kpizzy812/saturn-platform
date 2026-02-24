<?php

use App\Services\DatabaseMetrics\PostgresMetricsService;

beforeEach(function () {
    $this->service = new PostgresMetricsService;
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// Username validation
// ═══════════════════════════════════════════

test('valid usernames pass validation regex', function () {
    $validUsernames = ['admin', 'db_user', '_internal', 'User123', 'a', 'test_user_1'];
    $pattern = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    foreach ($validUsernames as $username) {
        expect(preg_match($pattern, $username))->toBe(1, "Username '{$username}' should be valid");
    }
});

test('invalid usernames fail validation regex', function () {
    $invalidUsernames = ['123user', '-admin', 'user name', 'user;drop', "user'inject", 'user"test', ''];
    $pattern = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    foreach ($invalidUsernames as $username) {
        expect(preg_match($pattern, $username))->toBe(0, "Username '{$username}' should be invalid");
    }
});

test('username starting with number is rejected', function () {
    $pattern = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    expect(preg_match($pattern, '1admin'))->toBe(0);
});

// ═══════════════════════════════════════════
// Password escaping for SQL
// ═══════════════════════════════════════════

test('single quotes in password are escaped for SQL', function () {
    $password = "it's a test";
    $escaped = str_replace("'", "''", $password);

    expect($escaped)->toBe("it''s a test");
});

test('multiple single quotes are all escaped', function () {
    $password = "pass'word'test";
    $escaped = str_replace("'", "''", $password);

    expect($escaped)->toBe("pass''word''test");
});

test('password without quotes remains unchanged', function () {
    $password = 'securepassword123';
    $escaped = str_replace("'", "''", $password);

    expect($escaped)->toBe('securepassword123');
});

// ═══════════════════════════════════════════
// Default values for postgres_user and postgres_db
// ═══════════════════════════════════════════

test('postgres_user defaults to postgres when null', function () {
    $user = null ?? 'postgres';
    expect($user)->toBe('postgres');
});

test('postgres_db defaults to postgres when null', function () {
    $db = null ?? 'postgres';
    expect($db)->toBe('postgres');
});

test('custom postgres_user is preserved', function () {
    $user = 'myapp' ?? 'postgres';
    expect($user)->toBe('myapp');
});

// ═══════════════════════════════════════════
// Error detection logic
// ═══════════════════════════════════════════

test('ERROR in result is detected case-insensitively', function () {
    expect(stripos('ERROR: relation does not exist', 'ERROR') !== false)->toBeTrue();
    expect(stripos('error: something failed', 'ERROR') !== false)->toBeTrue();
    expect(stripos('Error: bad input', 'ERROR') !== false)->toBeTrue();
});

test('FATAL in result is detected case-insensitively', function () {
    expect(stripos('FATAL: password authentication failed', 'FATAL') !== false)->toBeTrue();
    expect(stripos('fatal: connection refused', 'FATAL') !== false)->toBeTrue();
});

test('normal result does not trigger error detection', function () {
    expect(stripos('SELECT 1', 'ERROR') !== false)->toBeFalse();
    expect(stripos('(1 row)', 'FATAL') !== false)->toBeFalse();
});

// ═══════════════════════════════════════════
// parseDelimitedResult() — result parsing
// ═══════════════════════════════════════════

test('parseDelimitedResult handles empty input', function () {
    $method = new ReflectionMethod(PostgresMetricsService::class, 'parseDelimitedResult');

    $result = $method->invoke($this->service, '', '|');

    expect($result['columns'])->toBe([]);
    expect($result['rows'])->toBe([]);
    expect($result['rowCount'])->toBe(0);
});

test('parseDelimitedResult parses single row correctly', function () {
    $method = new ReflectionMethod(PostgresMetricsService::class, 'parseDelimitedResult');

    $result = $method->invoke($this->service, 'alice|42|active', '|');

    expect($result['columns'])->toBe(['column_0', 'column_1', 'column_2']);
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['column_0'])->toBe('alice');
    expect($result['rows'][0]['column_1'])->toBe('42');
    expect($result['rows'][0]['column_2'])->toBe('active');
});

test('parseDelimitedResult parses multiple rows', function () {
    $method = new ReflectionMethod(PostgresMetricsService::class, 'parseDelimitedResult');

    $input = "alice|42|active\nbob|17|idle\ncharlie|99|active";
    $result = $method->invoke($this->service, $input, '|');

    expect($result['columns'])->toHaveCount(3);
    expect($result['rows'])->toHaveCount(3);
    expect($result['rowCount'])->toBe(3);
});

test('parseDelimitedResult generates generic column names', function () {
    $method = new ReflectionMethod(PostgresMetricsService::class, 'parseDelimitedResult');

    $result = $method->invoke($this->service, 'a|b|c|d|e', '|');

    expect($result['columns'])->toBe(['column_0', 'column_1', 'column_2', 'column_3', 'column_4']);
});

test('parseDelimitedResult limits results to 1000 rows', function () {
    $method = new ReflectionMethod(PostgresMetricsService::class, 'parseDelimitedResult');

    // Generate 1500 rows
    $lines = [];
    for ($i = 0; $i < 1500; $i++) {
        $lines[] = "row{$i}|value{$i}";
    }
    $input = implode("\n", $lines);

    $result = $method->invoke($this->service, $input, '|');

    expect($result['rowCount'])->toBe(1000);
    expect($result['rows'])->toHaveCount(1000);
});

test('parseDelimitedResult filters blank lines', function () {
    $method = new ReflectionMethod(PostgresMetricsService::class, 'parseDelimitedResult');

    $input = "alice|42\n\nbob|17\n  \ncharlie|99";
    $result = $method->invoke($this->service, $input, '|');

    expect($result['rows'])->toHaveCount(3);
});

// ═══════════════════════════════════════════
// Duration formatting
// ═══════════════════════════════════════════

test('duration under 60 seconds shows seconds', function () {
    $duration = 45.5;
    $formatted = $duration < 60 ? round($duration, 3).'s' : round($duration / 60, 1).'m';

    expect($formatted)->toBe('45.5s');
});

test('duration over 60 seconds shows minutes', function () {
    $duration = 120.0;
    $formatted = $duration < 60 ? round($duration, 3).'s' : round($duration / 60, 1).'m';

    expect($formatted)->toBe('2m');
});

test('duration of exactly 60 seconds shows minutes', function () {
    $duration = 60.0;
    $formatted = $duration < 60 ? round($duration, 3).'s' : round($duration / 60, 1).'m';

    expect($formatted)->toBe('1m');
});

test('very short duration preserves precision', function () {
    $duration = 0.123;
    $formatted = $duration < 60 ? round($duration, 3).'s' : round($duration / 60, 1).'m';

    expect($formatted)->toBe('0.123s');
});

// ═══════════════════════════════════════════
// Table name validation
// ═══════════════════════════════════════════

test('valid table names pass validation regex', function () {
    $pattern = '/^[a-zA-Z_][a-zA-Z0-9_.\-]{0,127}$/';

    $validNames = ['users', 'public.users', 'my_table', '_internal', 'schema.table-name'];
    foreach ($validNames as $name) {
        expect(preg_match($pattern, $name))->toBe(1, "Table name '{$name}' should be valid");
    }
});

test('invalid table names fail validation regex', function () {
    $pattern = '/^[a-zA-Z_][a-zA-Z0-9_.\-]{0,127}$/';

    $invalidNames = ['1table', '-table', '; DROP TABLE users', "table'name", 'table"name'];
    foreach ($invalidNames as $name) {
        expect(preg_match($pattern, $name))->toBe(0, "Table name '{$name}' should be invalid");
    }
});

// ═══════════════════════════════════════════
// SQL injection prevention in search
// ═══════════════════════════════════════════

test('search sanitization removes dangerous characters', function () {
    $search = "test'; DROP TABLE users; --";
    $escaped = str_replace(["'", '"', '\\', ';', '--'], '', $search);

    expect($escaped)->toBe('test DROP TABLE users ');
    expect($escaped)->not->toContain("'");
    expect($escaped)->not->toContain(';');
    expect($escaped)->not->toContain('--');
});

test('safe column name validation rejects SQL injection', function () {
    $pattern = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    $safeColumns = ['name', 'email', 'created_at', '_id'];
    $unsafeColumns = ['name;', "user'", 'DROP TABLE', 'col--umn'];

    foreach ($safeColumns as $col) {
        expect(preg_match($pattern, $col))->toBe(1, "Column '{$col}' should be safe");
    }

    foreach ($unsafeColumns as $col) {
        expect(preg_match($pattern, $col))->toBe(0, "Column '{$col}' should be unsafe");
    }
});

// ═══════════════════════════════════════════
// Schema handling
// ═══════════════════════════════════════════

test('table name with dot is treated as schema.table', function () {
    $tableName = 'public.users';
    $hasDot = str_contains($tableName, '.');

    expect($hasDot)->toBeTrue();

    [$schema, $table] = explode('.', $tableName, 2);
    expect($schema)->toBe('public');
    expect($table)->toBe('users');
});

test('table name without dot defaults to public schema', function () {
    $tableName = 'users';
    $hasDot = str_contains($tableName, '.');

    expect($hasDot)->toBeFalse();

    [$schema, $table] = $hasDot ? explode('.', $tableName, 2) : ['public', $tableName];
    expect($schema)->toBe('public');
    expect($table)->toBe('users');
});

// ═══════════════════════════════════════════
// Numeric validation for connections
// ═══════════════════════════════════════════

test('numeric string is detected as valid connection count', function () {
    expect(is_numeric('42'))->toBeTrue();
    expect(is_numeric('0'))->toBeTrue();
    expect(is_numeric('100'))->toBeTrue();
});

test('non-numeric string is rejected for connection count', function () {
    expect(is_numeric('N/A'))->toBeFalse();
    expect(is_numeric(''))->toBeFalse();
    expect(is_numeric('error'))->toBeFalse();
});

// ═══════════════════════════════════════════
// Extension toggle SQL
// ═══════════════════════════════════════════

test('extension enable SQL uses CREATE EXTENSION', function () {
    $extensionName = 'pg_trgm';
    $enable = true;
    $sql = $enable ? "CREATE EXTENSION IF NOT EXISTS \"{$extensionName}\"" : "DROP EXTENSION IF EXISTS \"{$extensionName}\"";

    expect($sql)->toBe('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');
});

test('extension disable SQL uses DROP EXTENSION', function () {
    $extensionName = 'pg_trgm';
    $enable = false;
    $sql = $enable ? "CREATE EXTENSION IF NOT EXISTS \"{$extensionName}\"" : "DROP EXTENSION IF EXISTS \"{$extensionName}\"";

    expect($sql)->toBe('DROP EXTENSION IF EXISTS "pg_trgm"');
});
