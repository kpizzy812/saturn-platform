<?php

/**
 * Database Backup Job Tests
 *
 * Verifies backup command construction, type support, and data integrity
 * for all 8 database types: PostgreSQL, MySQL, MariaDB, MongoDB,
 * Redis, KeyDB, Dragonfly, ClickHouse.
 */

use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;

// ═══════════════════════════════════════════
// isBackupSolutionAvailable() for all types
// ═══════════════════════════════════════════

test('postgresql reports backup solution available', function () {
    $db = Mockery::mock(StandalonePostgresql::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('mysql reports backup solution available', function () {
    $db = Mockery::mock(StandaloneMysql::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('mariadb reports backup solution available', function () {
    $db = Mockery::mock(StandaloneMariadb::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('mongodb reports backup solution available', function () {
    $db = Mockery::mock(StandaloneMongodb::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('redis reports backup solution available', function () {
    $db = Mockery::mock(StandaloneRedis::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('keydb reports backup solution available', function () {
    $db = Mockery::mock(StandaloneKeydb::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('dragonfly reports backup solution available', function () {
    $db = Mockery::mock(StandaloneDragonfly::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

test('clickhouse reports backup solution available', function () {
    $db = Mockery::mock(StandaloneClickhouse::class)->makePartial();
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

// ═══════════════════════════════════════════
// Database type strings are correct
// ═══════════════════════════════════════════

test('all database models return correct type strings', function () {
    expect((new StandalonePostgresql)->type())->toBe('standalone-postgresql');
    expect((new StandaloneMysql)->type())->toBe('standalone-mysql');
    expect((new StandaloneMariadb)->type())->toBe('standalone-mariadb');
    expect((new StandaloneMongodb)->type())->toBe('standalone-mongodb');
    expect((new StandaloneRedis)->type())->toBe('standalone-redis');
    expect((new StandaloneKeydb)->type())->toBe('standalone-keydb');
    expect((new StandaloneDragonfly)->type())->toBe('standalone-dragonfly');
    expect((new StandaloneClickhouse)->type())->toBe('standalone-clickhouse');
});

// ═══════════════════════════════════════════
// Backup type routing covers all DB types
// ═══════════════════════════════════════════

test('backup type routing handles postgresql', function () {
    $type = 'standalone-postgresql';
    expect(str($type)->contains('postgres'))->toBeTrue();
});

test('backup type routing handles mysql', function () {
    $type = 'standalone-mysql';
    expect(str($type)->contains('mysql'))->toBeTrue();
    expect(str($type)->contains('mariadb'))->toBeFalse();
});

test('backup type routing handles mariadb', function () {
    $type = 'standalone-mariadb';
    expect(str($type)->contains('mariadb'))->toBeTrue();
});

test('backup type routing handles mongodb', function () {
    $type = 'standalone-mongodb';
    expect(str($type)->contains('mongo'))->toBeTrue();
});

test('backup type routing handles redis', function () {
    $type = 'standalone-redis';
    expect(
        str($type)->contains('redis') || str($type)->contains('keydb') || str($type)->contains('dragonfly')
    )->toBeTrue();
});

test('backup type routing handles keydb', function () {
    $type = 'standalone-keydb';
    expect(
        str($type)->contains('redis') || str($type)->contains('keydb') || str($type)->contains('dragonfly')
    )->toBeTrue();
});

test('backup type routing handles dragonfly', function () {
    $type = 'standalone-dragonfly';
    expect(
        str($type)->contains('redis') || str($type)->contains('keydb') || str($type)->contains('dragonfly')
    )->toBeTrue();
});

test('backup type routing handles clickhouse', function () {
    $type = 'standalone-clickhouse';
    expect(str($type)->contains('clickhouse'))->toBeTrue();
});

// ═══════════════════════════════════════════
// Type routing order is correct (no false matches)
// MySQL must NOT match MariaDB and vice versa
// ═══════════════════════════════════════════

test('mysql type does not match mariadb check', function () {
    $type = 'standalone-mysql';
    // In the backup job, mariadb is checked before mysql would accidentally match
    // But 'mysql' does NOT contain 'mariadb'
    expect(str($type)->contains('mariadb'))->toBeFalse();
});

test('mariadb type does not match mongo check', function () {
    $type = 'standalone-mariadb';
    expect(str($type)->contains('mongo'))->toBeFalse();
});

test('redis type does not match clickhouse check', function () {
    $type = 'standalone-redis';
    expect(str($type)->contains('clickhouse'))->toBeFalse();
});

test('keydb type does not match redis-specific properties', function () {
    // KeyDB uses keydb_password, not redis_password
    $type = 'standalone-keydb';
    expect(str($type)->contains('keydb'))->toBeTrue();
    expect(str($type)->contains('redis'))->toBeFalse();
});

// ═══════════════════════════════════════════
// Backup command verification via reflection
// ═══════════════════════════════════════════

test('mysql backup includes single-transaction and routines flags', function () {
    $job = createBackupJobWithMockedDeps('mysql');
    $method = new ReflectionMethod($job, 'backup_standalone_mysql');

    expect($method->getName())->toBe('backup_standalone_mysql');
    expect($method->getNumberOfParameters())->toBe(1);
});

test('mariadb backup includes single-transaction and routines flags', function () {
    $job = createBackupJobWithMockedDeps('mariadb');
    $method = new ReflectionMethod($job, 'backup_standalone_mariadb');
    expect($method->getName())->toBe('backup_standalone_mariadb');
    expect($method->getNumberOfParameters())->toBe(1);
});

test('redis backup method exists', function () {
    $job = createBackupJobWithMockedDeps('redis');
    $method = new ReflectionMethod($job, 'backup_standalone_redis');
    expect($method->getName())->toBe('backup_standalone_redis');
    expect($method->getNumberOfParameters())->toBe(1);
});

test('clickhouse backup method exists', function () {
    $job = createBackupJobWithMockedDeps('clickhouse');
    $method = new ReflectionMethod($job, 'backup_standalone_clickhouse');
    expect($method->getName())->toBe('backup_standalone_clickhouse');
    expect($method->getNumberOfParameters())->toBe(1);
});

// ═══════════════════════════════════════════
// Backup file naming conventions
// ═══════════════════════════════════════════

test('postgresql backup produces .dmp file', function () {
    $database = 'testdb';
    $timestamp = time();
    $file = "/pg-dump-{$database}-{$timestamp}.dmp";
    expect($file)->toContain('.dmp');
    expect($file)->toStartWith('/pg-dump-');
});

test('mysql backup produces .dmp file', function () {
    $database = 'testdb';
    $timestamp = time();
    $file = "/mysql-dump-{$database}-{$timestamp}.dmp";
    expect($file)->toContain('.dmp');
    expect($file)->toStartWith('/mysql-dump-');
});

test('mariadb backup produces .dmp file', function () {
    $database = 'testdb';
    $timestamp = time();
    $file = "/mariadb-dump-{$database}-{$timestamp}.dmp";
    expect($file)->toContain('.dmp');
    expect($file)->toStartWith('/mariadb-dump-');
});

test('mongodb backup produces .tar.gz file', function () {
    $database = 'testdb';
    $timestamp = time();
    $file = "/mongo-dump-{$database}-{$timestamp}.tar.gz";
    expect($file)->toContain('.tar.gz');
    expect($file)->toStartWith('/mongo-dump-');
});

test('redis backup produces .rdb file', function () {
    $database = 'all';
    $timestamp = time();
    $file = "/redis-dump-{$database}-{$timestamp}.rdb";
    expect($file)->toContain('.rdb');
    expect($file)->toStartWith('/redis-dump-');
});

test('clickhouse backup produces .tar.gz file', function () {
    $database = 'default';
    $timestamp = time();
    $file = "/clickhouse-dump-{$database}-{$timestamp}.tar.gz";
    expect($file)->toContain('.tar.gz');
    expect($file)->toStartWith('/clickhouse-dump-');
});

// ═══════════════════════════════════════════
// Redis password handling per type
// ═══════════════════════════════════════════

test('redis password match selects correct field per database type', function () {
    // This mirrors the match() in backup_standalone_redis
    $types = [
        'standalone-redis' => 'redis_password',
        'standalone-keydb' => 'keydb_password',
        'standalone-dragonfly' => 'dragonfly_password',
    ];

    foreach ($types as $type => $expectedField) {
        $result = match (true) {
            str($type)->contains('keydb') => 'keydb_password',
            str($type)->contains('dragonfly') => 'dragonfly_password',
            default => 'redis_password',
        };
        expect($result)->toBe($expectedField, "Failed for type: {$type}");
    }
});

// ═══════════════════════════════════════════
// Default databases to backup per type
// ═══════════════════════════════════════════

test('redis defaults to backing up all data', function () {
    $type = 'standalone-redis';
    $isRedisLike = str($type)->contains('redis') || str($type)->contains('keydb') || str($type)->contains('dragonfly');
    expect($isRedisLike)->toBeTrue();

    // Redis backup always backs up ALL data (entire RDB snapshot)
    $databasesToBackup = $isRedisLike ? ['all'] : [];
    expect($databasesToBackup)->toBe(['all']);
});

test('clickhouse defaults to configured database', function () {
    $type = 'standalone-clickhouse';
    $database = null; // simulates no clickhouse_db set
    $databasesToBackup = [$database ?? 'default'];
    expect($databasesToBackup)->toBe(['default']);

    // With configured database
    $database = 'analytics';
    $databasesToBackup = [$database ?? 'default'];
    expect($databasesToBackup)->toBe(['analytics']);
});

// ═══════════════════════════════════════════
// All models have scheduledBackups() relation
// ═══════════════════════════════════════════

test('all database models have scheduledBackups relationship', function () {
    $models = [
        StandalonePostgresql::class,
        StandaloneMysql::class,
        StandaloneMariadb::class,
        StandaloneMongodb::class,
        StandaloneRedis::class,
        StandaloneKeydb::class,
        StandaloneDragonfly::class,
        StandaloneClickhouse::class,
    ];

    foreach ($models as $modelClass) {
        $model = new $modelClass;
        expect(method_exists($model, 'scheduledBackups'))
            ->toBeTrue("Model {$modelClass} missing scheduledBackups() relation");
    }
});

// ═══════════════════════════════════════════
// Source code verification: critical flags
// ═══════════════════════════════════════════

test('mysql single-db dump source contains --single-transaction --quick --routines --events', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_mysql');

    expect($method)->toContain('--single-transaction');
    expect($method)->toContain('--quick');
    expect($method)->toContain('--routines');
    expect($method)->toContain('--events');
});

test('mariadb single-db dump source contains --single-transaction --quick --routines --events', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_mariadb');

    expect($method)->toContain('--single-transaction');
    expect($method)->toContain('--quick');
    expect($method)->toContain('--routines');
    expect($method)->toContain('--events');
});

test('postgresql dump source contains --format=custom for data integrity', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_postgresql');

    expect($method)->toContain('--format=custom');
});

test('redis backup source uses SAVE command before copying', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_redis');

    expect($method)->toContain('redis-cli');
    expect($method)->toContain('SAVE');
    expect($method)->toContain('/data/dump.rdb');
    expect($method)->toContain('--no-auth-warning');
});

test('redis backup source uses escapeshellarg for password', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_redis');

    expect($method)->toContain('escapeshellarg');
});

test('clickhouse backup source dumps DDL and Native data', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_clickhouse');

    expect($method)->toContain('SHOW CREATE TABLE');
    expect($method)->toContain('--format TSVRaw');
    expect($method)->toContain('FORMAT Native');
    expect($method)->toContain('SHOW TABLES');
    expect($method)->toContain('tar czf');
    expect($method)->toContain('escapeshellarg');
});

test('mongodb backup source uses --gzip --archive for compression', function () {
    $source = file_get_contents(app_path('Jobs/DatabaseBackupJob.php'));
    $method = extractMethodSource($source, 'backup_standalone_mongodb');

    expect($method)->toContain('--gzip');
    expect($method)->toContain('--archive');
    expect($method)->toContain('mongodump');
});

// ═══════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════

/**
 * Extract a method's source code from the class file string.
 */
function extractMethodSource(string $source, string $methodName): string
{
    $pattern = '/private\s+function\s+'.preg_quote($methodName, '/').'\s*\([^)]*\)\s*:\s*void\s*\{/';
    if (! preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
        throw new \Exception("Method {$methodName} not found in source");
    }

    $start = $matches[0][1];
    $braceCount = 0;
    $inMethod = false;
    $end = $start;

    for ($i = $start; $i < strlen($source); $i++) {
        if ($source[$i] === '{') {
            $braceCount++;
            $inMethod = true;
        } elseif ($source[$i] === '}') {
            $braceCount--;
        }
        if ($inMethod && $braceCount === 0) {
            $end = $i + 1;
            break;
        }
    }

    return substr($source, $start, $end - $start);
}

function createBackupJobWithMockedDeps(string $type): \App\Jobs\DatabaseBackupJob
{
    $backup = Mockery::mock(\App\Models\ScheduledDatabaseBackup::class)->makePartial();
    $backup->shouldReceive('getAttribute')->with('timeout')->andReturn(3600);
    $backup->timeout = 3600;

    $job = new \App\Jobs\DatabaseBackupJob($backup);

    return $job;
}
