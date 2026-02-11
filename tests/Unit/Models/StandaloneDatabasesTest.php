<?php

use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;

// type() Tests for all database models
test('postgresql type returns standalone-postgresql', function () {
    expect((new StandalonePostgresql)->type())->toBe('standalone-postgresql');
});

test('mysql type returns standalone-mysql', function () {
    expect((new StandaloneMysql)->type())->toBe('standalone-mysql');
});

test('mongodb type returns standalone-mongodb', function () {
    expect((new StandaloneMongodb)->type())->toBe('standalone-mongodb');
});

test('redis type returns standalone-redis', function () {
    expect((new StandaloneRedis)->type())->toBe('standalone-redis');
});

test('mariadb type returns standalone-mariadb', function () {
    expect((new StandaloneMariadb)->type())->toBe('standalone-mariadb');
});

test('keydb type returns standalone-keydb', function () {
    expect((new StandaloneKeydb)->type())->toBe('standalone-keydb');
});

test('dragonfly type returns standalone-dragonfly', function () {
    expect((new StandaloneDragonfly)->type())->toBe('standalone-dragonfly');
});

test('clickhouse type returns standalone-clickhouse', function () {
    expect((new StandaloneClickhouse)->type())->toBe('standalone-clickhouse');
});

// isRunning() Tests
test('database isRunning returns true when status contains running', function () {
    $db = new StandalonePostgresql;
    $db->status = 'running';
    expect($db->isRunning())->toBeTrue();
});

test('database isRunning returns true when status is running:healthy', function () {
    $db = new StandaloneMysql;
    $db->status = 'running:healthy';
    expect($db->isRunning())->toBeTrue();
});

test('database isRunning returns false when status is exited', function () {
    $db = new StandaloneRedis;
    $db->status = 'exited';
    expect($db->isRunning())->toBeFalse();
});

test('database isRunning returns false when status is stopped', function () {
    $db = new StandaloneMariadb;
    $db->status = 'stopped';
    expect($db->isRunning())->toBeFalse();
});

// isExited() Tests
test('database isExited returns true when status starts with exited', function () {
    $db = new StandalonePostgresql;
    $db->status = 'exited';
    expect($db->isExited())->toBeTrue();
});

test('database isExited returns true for exited with code', function () {
    $db = new StandaloneMongodb;
    $db->status = 'exited:0';
    expect($db->isExited())->toBeTrue();
});

test('database isExited returns false when running', function () {
    $db = new StandaloneClickhouse;
    $db->status = 'running:healthy';
    expect($db->isExited())->toBeFalse();
});

// isBackupSolutionAvailable() Tests
test('postgresql supports backups', function () {
    expect((new StandalonePostgresql)->isBackupSolutionAvailable())->toBeTrue();
});

test('mysql supports backups', function () {
    expect((new StandaloneMysql)->isBackupSolutionAvailable())->toBeTrue();
});

test('mariadb supports backups', function () {
    expect((new StandaloneMariadb)->isBackupSolutionAvailable())->toBeTrue();
});

test('mongodb supports backups', function () {
    expect((new StandaloneMongodb)->isBackupSolutionAvailable())->toBeTrue();
});

test('redis does not support backups', function () {
    expect((new StandaloneRedis)->isBackupSolutionAvailable())->toBeFalse();
});

test('keydb does not support backups', function () {
    expect((new StandaloneKeydb)->isBackupSolutionAvailable())->toBeFalse();
});

test('dragonfly does not support backups', function () {
    expect((new StandaloneDragonfly)->isBackupSolutionAvailable())->toBeFalse();
});

test('clickhouse does not support backups', function () {
    expect((new StandaloneClickhouse)->isBackupSolutionAvailable())->toBeFalse();
});

// isLogDrainEnabled() Tests
test('isLogDrainEnabled returns true when enabled', function () {
    $db = new StandalonePostgresql;
    $db->is_log_drain_enabled = true;
    expect($db->isLogDrainEnabled())->toBeTrue();
});

test('isLogDrainEnabled returns false when disabled', function () {
    $db = new StandalonePostgresql;
    $db->is_log_drain_enabled = false;
    expect($db->isLogDrainEnabled())->toBeFalse();
});

test('isLogDrainEnabled returns false by default', function () {
    $db = new StandaloneMysql;
    expect($db->isLogDrainEnabled())->toBeFalse();
});

// project() and team() Tests
test('project returns environment project', function () {
    $db = new StandaloneRedis;
    $project = (object) ['id' => 1, 'name' => 'Test'];
    $db->environment = (object) ['project' => $project];

    expect($db->project())->toBe($project);
});

test('project returns null when no environment', function () {
    $db = new StandalonePostgresql;
    $db->environment = null;

    expect($db->project())->toBeNull();
});

test('team returns environment project team', function () {
    $db = new StandaloneMysql;
    $team = (object) ['id' => 1, 'name' => 'Team'];
    $db->environment = (object) ['project' => (object) ['team' => $team]];

    expect($db->team())->toBe($team);
});

// portsMappingsArray Tests
test('portsMappingsArray returns empty array when no mappings', function () {
    $db = new StandalonePostgresql;
    $db->ports_mappings = null;
    expect($db->ports_mappings_array)->toBe([]);
});

test('portsMappingsArray splits comma-separated ports', function () {
    $db = new StandalonePostgresql;
    $db->ports_mappings = '5432:5432,5433:5433';
    expect($db->ports_mappings_array)->toBe(['5432:5432', '5433:5433']);
});

test('portsMappingsArray returns single port mapping', function () {
    $db = new StandaloneMysql;
    $db->ports_mappings = '3306:3306';
    expect($db->ports_mappings_array)->toBe(['3306:3306']);
});

// Redis-specific: getRedisVersion Tests
test('getRedisVersion extracts version from image tag', function () {
    $redis = new StandaloneRedis;
    $redis->image = 'redis:7.0';
    expect($redis->getRedisVersion())->toBe('7.0');
});

test('getRedisVersion returns 0.0 when no tag', function () {
    $redis = new StandaloneRedis;
    $redis->image = 'redis';
    expect($redis->getRedisVersion())->toBe('0.0');
});

test('getRedisVersion extracts version from complex image', function () {
    $redis = new StandaloneRedis;
    $redis->image = 'bitnami/redis:7.2.4';
    expect($redis->getRedisVersion())->toBe('7.2.4');
});

// workdir() Tests
test('workdir returns correct path for postgresql', function () {
    $db = new StandalonePostgresql;
    $db->uuid = 'abc-123';
    expect($db->workdir())->toContain('abc-123');
});

test('workdir returns correct path for mysql', function () {
    $db = new StandaloneMysql;
    $db->uuid = 'def-456';
    expect($db->workdir())->toContain('def-456');
});
