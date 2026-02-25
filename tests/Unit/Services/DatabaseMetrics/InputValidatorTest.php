<?php

use App\Services\DatabaseMetrics\InputValidator;

// ═══════════════════════════════════════════════════════════
// Column name validation
// ═══════════════════════════════════════════════════════════

test('isValidColumnName accepts safe column names', function () {
    $valid = ['name', 'email', 'created_at', '_id', 'UserName', 'col123', '_private', 'A'];

    foreach ($valid as $col) {
        expect(InputValidator::isValidColumnName($col))
            ->toBeTrue("Column '{$col}' should be valid");
    }
});

test('isValidColumnName rejects unsafe column names', function () {
    $invalid = [
        '123col',           // starts with number
        '-name',            // starts with hyphen
        'col;drop',         // semicolon (SQL injection)
        "col'name",         // single quote
        'col"name',         // double quote
        'col name',         // space
        'col--comment',     // SQL comment
        '',                 // empty
        'col.name',         // dot (only allowed in field names, not column names)
        'col$var',          // dollar sign
        "col\x00name",     // null byte
    ];

    foreach ($invalid as $col) {
        expect(InputValidator::isValidColumnName($col))
            ->toBeFalse("Column '{$col}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// Table name validation
// ═══════════════════════════════════════════════════════════

test('isValidTableName accepts safe table names', function () {
    $valid = ['users', 'public.users', 'my_table', '_internal', 'schema.table-name', 'CamelCase'];

    foreach ($valid as $name) {
        expect(InputValidator::isValidTableName($name))
            ->toBeTrue("Table name '{$name}' should be valid");
    }
});

test('isValidTableName rejects unsafe table names', function () {
    $invalid = [
        '1table',                  // starts with number
        '-table',                  // starts with hyphen
        '; DROP TABLE users',      // SQL injection
        "table'name",              // single quote
        'table"name',              // double quote
        'table name',              // space
        '',                        // empty
        str_repeat('a', 200),      // too long (>128 chars)
    ];

    foreach ($invalid as $name) {
        expect(InputValidator::isValidTableName($name))
            ->toBeFalse("Table name '{$name}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// Field name validation (MongoDB)
// ═══════════════════════════════════════════════════════════

test('isValidFieldName accepts safe field names', function () {
    $valid = ['_id', 'name', 'user.address', 'nested.deep.field', 'My_Field123'];

    foreach ($valid as $name) {
        expect(InputValidator::isValidFieldName($name))
            ->toBeTrue("Field name '{$name}' should be valid");
    }
});

test('isValidFieldName rejects unsafe field names', function () {
    $invalid = [
        '123field',                     // starts with number
        '$where',                       // MongoDB operator injection
        "field'name",                   // single quote
        'field;name',                   // semicolon
        'field name',                   // space
        "x}, {\$where: 'sleep(1000)'}", // NoSQL injection payload
        '',                             // empty
    ];

    foreach ($invalid as $name) {
        expect(InputValidator::isValidFieldName($name))
            ->toBeFalse("Field name '{$name}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// Extension name validation
// ═══════════════════════════════════════════════════════════

test('isValidExtensionName accepts safe extension names', function () {
    $valid = ['pg_trgm', 'uuid_ossp', 'postgis', 'hstore', 'plpgsql', 'PgTrgm'];

    foreach ($valid as $name) {
        expect(InputValidator::isValidExtensionName($name))
            ->toBeTrue("Extension name '{$name}' should be valid");
    }
});

test('isValidExtensionName rejects unsafe extension names', function () {
    $invalid = [
        '1ext',                    // starts with number
        'ext;drop',                // semicolon
        "ext'name",                // single quote
        'ext"name',                // double quote
        'ext name',                // space
        'ext--comment',            // SQL comment chars
        '',                        // empty
        'ext.name',                // dot
    ];

    foreach ($invalid as $name) {
        expect(InputValidator::isValidExtensionName($name))
            ->toBeFalse("Extension name '{$name}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// Username validation
// ═══════════════════════════════════════════════════════════

test('isValidUsername accepts safe usernames', function () {
    $valid = ['admin', 'db_user', '_internal', 'User123', 'a', 'test_user_1'];

    foreach ($valid as $name) {
        expect(InputValidator::isValidUsername($name))
            ->toBeTrue("Username '{$name}' should be valid");
    }
});

test('isValidUsername rejects unsafe usernames', function () {
    $invalid = ['123user', '-admin', 'user name', 'user;drop', "user'inject", 'user"test', ''];

    foreach ($invalid as $name) {
        expect(InputValidator::isValidUsername($name))
            ->toBeFalse("Username '{$name}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// ORDER BY direction validation
// ═══════════════════════════════════════════════════════════

test('validateOrderDirection accepts ASC and DESC', function () {
    expect(InputValidator::validateOrderDirection('asc'))->toBe('ASC');
    expect(InputValidator::validateOrderDirection('ASC'))->toBe('ASC');
    expect(InputValidator::validateOrderDirection('desc'))->toBe('DESC');
    expect(InputValidator::validateOrderDirection('DESC'))->toBe('DESC');
    expect(InputValidator::validateOrderDirection('Desc'))->toBe('DESC');
});

test('validateOrderDirection throws on invalid direction', function () {
    expect(fn () => InputValidator::validateOrderDirection('SIDEWAYS'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateOrderDirection('asc; DROP TABLE users'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateOrderDirection(''))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateOrderDirection("asc'--"))
        ->toThrow(\InvalidArgumentException::class);
});

test('safeOrderDirection returns ASC as fallback for invalid input', function () {
    expect(InputValidator::safeOrderDirection('asc'))->toBe('ASC');
    expect(InputValidator::safeOrderDirection('desc'))->toBe('DESC');
    expect(InputValidator::safeOrderDirection('INVALID'))->toBe('ASC');
    expect(InputValidator::safeOrderDirection(''))->toBe('ASC');
    expect(InputValidator::safeOrderDirection('asc; DROP TABLE'))->toBe('ASC');
});

// ═══════════════════════════════════════════════════════════
// Maintenance operation validation
// ═══════════════════════════════════════════════════════════

test('validateMaintenanceOperation accepts whitelisted operations', function () {
    expect(InputValidator::validateMaintenanceOperation('vacuum'))->toBe('VACUUM');
    expect(InputValidator::validateMaintenanceOperation('VACUUM'))->toBe('VACUUM');
    expect(InputValidator::validateMaintenanceOperation('analyze'))->toBe('ANALYZE');
    expect(InputValidator::validateMaintenanceOperation('ANALYZE'))->toBe('ANALYZE');
    expect(InputValidator::validateMaintenanceOperation('vacuum analyze'))->toBe('VACUUM ANALYZE');
    expect(InputValidator::validateMaintenanceOperation('reindex'))->toBe('REINDEX');
});

test('validateMaintenanceOperation rejects arbitrary SQL', function () {
    expect(fn () => InputValidator::validateMaintenanceOperation('DROP TABLE users'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateMaintenanceOperation('vacuum; DROP TABLE'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateMaintenanceOperation("vacuum\nDROP TABLE"))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateMaintenanceOperation('SELECT * FROM users'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => InputValidator::validateMaintenanceOperation(''))
        ->toThrow(\InvalidArgumentException::class);
});

// ═══════════════════════════════════════════════════════════
// Search sanitization
// ═══════════════════════════════════════════════════════════

test('sanitizeSearch removes SQL injection characters', function () {
    $search = "test'; DROP TABLE users; --";
    $result = InputValidator::sanitizeSearch($search);

    expect($result)->not->toContain("'");
    expect($result)->not->toContain(';');
    expect($result)->not->toContain('--');
    expect($result)->toContain('test');
});

test('sanitizeSearch removes dollar signs (PostgreSQL dollar quoting)', function () {
    $search = '$$malicious$$';
    $result = InputValidator::sanitizeSearch($search);

    expect($result)->not->toContain('$');
    expect($result)->toBe('malicious');
});

test('sanitizeSearch removes null bytes', function () {
    $search = "test\x00injection";
    $result = InputValidator::sanitizeSearch($search);

    expect($result)->not->toContain("\x00");
    expect($result)->toBe('testinjection');
});

test('sanitizeSearch preserves normal text', function () {
    $search = 'hello world 123';
    $result = InputValidator::sanitizeSearch($search);

    expect($result)->toBe('hello world 123');
});

test('sanitizeSearch handles empty string', function () {
    expect(InputValidator::sanitizeSearch(''))->toBe('');
});

// ═══════════════════════════════════════════════════════════
// MongoDB search sanitization
// ═══════════════════════════════════════════════════════════

test('sanitizeMongoSearch removes regex special characters', function () {
    $search = '/.*dangerous.*/';
    $result = InputValidator::sanitizeMongoSearch($search);

    expect($result)->not->toContain('/');
    expect($result)->not->toContain('*');
    expect($result)->not->toContain('.');
    expect($result)->toBe('dangerous');
});

test('sanitizeMongoSearch removes JS injection characters', function () {
    $search = "x'; process.exit(0); //";
    $result = InputValidator::sanitizeMongoSearch($search);

    expect($result)->not->toContain("'");
    expect($result)->not->toContain(';');
    expect($result)->not->toContain('/');
});

test('sanitizeMongoSearch preserves alphanumeric text', function () {
    $search = 'hello world 123';
    $result = InputValidator::sanitizeMongoSearch($search);

    expect($result)->toBe('hello world 123');
});

// ═══════════════════════════════════════════════════════════
// Redis pattern validation
// ═══════════════════════════════════════════════════════════

test('isValidRedisPattern accepts safe patterns', function () {
    $valid = ['*', 'user:*', 'cache:session:*', 'key[0-9]', 'key?', 'simple_key', 'key:123'];

    foreach ($valid as $pattern) {
        expect(InputValidator::isValidRedisPattern($pattern))
            ->toBeTrue("Pattern '{$pattern}' should be valid");
    }
});

test('isValidRedisPattern rejects unsafe patterns', function () {
    $invalid = [
        'key; rm -rf /',    // command injection
        "key'injection",    // single quote
        'key"test',         // double quote
        'key$(whoami)',     // command substitution
        'key`id`',          // backtick execution
        '',                 // empty
        'key name',         // space
    ];

    foreach ($invalid as $pattern) {
        expect(InputValidator::isValidRedisPattern($pattern))
            ->toBeFalse("Pattern '{$pattern}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// Collection name validation (MongoDB)
// ═══════════════════════════════════════════════════════════

test('isValidCollectionName accepts safe collection names', function () {
    $valid = ['users', 'my_collection', 'App.Models', 'data-store', '_internal'];

    foreach ($valid as $name) {
        expect(InputValidator::isValidCollectionName($name))
            ->toBeTrue("Collection name '{$name}' should be valid");
    }
});

test('isValidCollectionName rejects unsafe collection names', function () {
    $invalid = [
        '123collection',                // starts with number
        "users'); db.dropDatabase();//", // NoSQL injection
        '$cmd',                         // MongoDB system collection
        '',                             // empty
        'col name',                     // space
    ];

    foreach ($invalid as $name) {
        expect(InputValidator::isValidCollectionName($name))
            ->toBeFalse("Collection name '{$name}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// ObjectId validation (MongoDB)
// ═══════════════════════════════════════════════════════════

test('isValidObjectId accepts valid 24-char hex strings', function () {
    $valid = ['507f1f77bcf86cd799439011', '000000000000000000000000', 'abcdef0123456789abcdef01'];

    foreach ($valid as $id) {
        expect(InputValidator::isValidObjectId($id))
            ->toBeTrue("ObjectId '{$id}' should be valid");
    }
});

test('isValidObjectId rejects invalid strings', function () {
    $invalid = [
        '507f1f77bcf86cd79943901',     // too short (23 chars)
        '507f1f77bcf86cd7994390111',   // too long (25 chars)
        '507f1f77bcf86cd79943901g',    // invalid hex char 'g'
        '',                             // empty
        "507f1f77bcf86cd799439011'",   // SQL injection attempt
        '507f1f77bcf86cd79943901;',    // semicolon
    ];

    foreach ($invalid as $id) {
        expect(InputValidator::isValidObjectId($id))
            ->toBeFalse("ObjectId '{$id}' should be invalid");
    }
});

// ═══════════════════════════════════════════════════════════
// Integration: ORDER BY direction in SQL context
// ═══════════════════════════════════════════════════════════

test('order direction cannot inject SQL via safeOrderDirection', function () {
    $injectionPayloads = [
        'ASC; DROP TABLE users',
        'DESC; DELETE FROM sessions',
        'ASC UNION SELECT * FROM passwords',
        '1; --',
        "ASC\nDROP TABLE users",
    ];

    foreach ($injectionPayloads as $payload) {
        $safe = InputValidator::safeOrderDirection($payload);
        expect($safe)->toBe('ASC', "Injection '{$payload}' should fall back to ASC");
    }
});

test('maintenance operation cannot inject SQL', function () {
    $injectionPayloads = [
        'VACUUM; DROP TABLE users',
        "VACUUM\nDELETE FROM sessions",
        'VACUUM UNION SELECT * FROM passwords',
        "VACUUM' OR '1'='1",
    ];

    foreach ($injectionPayloads as $payload) {
        expect(fn () => InputValidator::validateMaintenanceOperation($payload))
            ->toThrow(\InvalidArgumentException::class, '', "Payload '{$payload}' should throw");
    }
});
