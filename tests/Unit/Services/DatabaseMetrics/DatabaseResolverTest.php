<?php

/**
 * Unit tests for DatabaseResolver service.
 *
 * Tests cover:
 * - TYPE_* constants have correct values
 * - isSqlCapable(): true for postgresql, mysql, mariadb, clickhouse; false otherwise
 * - isRedisLike(): true for redis, keydb, dragonfly; false otherwise
 * - isMysqlLike(): true for mysql, mariadb; false otherwise
 */

use App\Services\DatabaseMetrics\DatabaseResolver;

// ─── Constants ────────────────────────────────────────────────────────────────

test('TYPE_POSTGRESQL constant equals postgresql', function () {
    expect(DatabaseResolver::TYPE_POSTGRESQL)->toBe('postgresql');
});

test('TYPE_MYSQL constant equals mysql', function () {
    expect(DatabaseResolver::TYPE_MYSQL)->toBe('mysql');
});

test('TYPE_MARIADB constant equals mariadb', function () {
    expect(DatabaseResolver::TYPE_MARIADB)->toBe('mariadb');
});

test('TYPE_MONGODB constant equals mongodb', function () {
    expect(DatabaseResolver::TYPE_MONGODB)->toBe('mongodb');
});

test('TYPE_REDIS constant equals redis', function () {
    expect(DatabaseResolver::TYPE_REDIS)->toBe('redis');
});

test('TYPE_KEYDB constant equals keydb', function () {
    expect(DatabaseResolver::TYPE_KEYDB)->toBe('keydb');
});

test('TYPE_DRAGONFLY constant equals dragonfly', function () {
    expect(DatabaseResolver::TYPE_DRAGONFLY)->toBe('dragonfly');
});

test('TYPE_CLICKHOUSE constant equals clickhouse', function () {
    expect(DatabaseResolver::TYPE_CLICKHOUSE)->toBe('clickhouse');
});

// ─── isSqlCapable ─────────────────────────────────────────────────────────────

test('isSqlCapable returns true for postgresql', function () {
    expect((new DatabaseResolver)->isSqlCapable('postgresql'))->toBeTrue();
});

test('isSqlCapable returns true for mysql', function () {
    expect((new DatabaseResolver)->isSqlCapable('mysql'))->toBeTrue();
});

test('isSqlCapable returns true for mariadb', function () {
    expect((new DatabaseResolver)->isSqlCapable('mariadb'))->toBeTrue();
});

test('isSqlCapable returns true for clickhouse', function () {
    expect((new DatabaseResolver)->isSqlCapable('clickhouse'))->toBeTrue();
});

test('isSqlCapable returns false for redis', function () {
    expect((new DatabaseResolver)->isSqlCapable('redis'))->toBeFalse();
});

test('isSqlCapable returns false for mongodb', function () {
    expect((new DatabaseResolver)->isSqlCapable('mongodb'))->toBeFalse();
});

test('isSqlCapable returns false for keydb', function () {
    expect((new DatabaseResolver)->isSqlCapable('keydb'))->toBeFalse();
});

test('isSqlCapable returns false for dragonfly', function () {
    expect((new DatabaseResolver)->isSqlCapable('dragonfly'))->toBeFalse();
});

// ─── isRedisLike ──────────────────────────────────────────────────────────────

test('isRedisLike returns true for redis', function () {
    expect((new DatabaseResolver)->isRedisLike('redis'))->toBeTrue();
});

test('isRedisLike returns true for keydb', function () {
    expect((new DatabaseResolver)->isRedisLike('keydb'))->toBeTrue();
});

test('isRedisLike returns true for dragonfly', function () {
    expect((new DatabaseResolver)->isRedisLike('dragonfly'))->toBeTrue();
});

test('isRedisLike returns false for postgresql', function () {
    expect((new DatabaseResolver)->isRedisLike('postgresql'))->toBeFalse();
});

test('isRedisLike returns false for mysql', function () {
    expect((new DatabaseResolver)->isRedisLike('mysql'))->toBeFalse();
});

test('isRedisLike returns false for mongodb', function () {
    expect((new DatabaseResolver)->isRedisLike('mongodb'))->toBeFalse();
});

// ─── isMysqlLike ──────────────────────────────────────────────────────────────

test('isMysqlLike returns true for mysql', function () {
    expect((new DatabaseResolver)->isMysqlLike('mysql'))->toBeTrue();
});

test('isMysqlLike returns true for mariadb', function () {
    expect((new DatabaseResolver)->isMysqlLike('mariadb'))->toBeTrue();
});

test('isMysqlLike returns false for postgresql', function () {
    expect((new DatabaseResolver)->isMysqlLike('postgresql'))->toBeFalse();
});

test('isMysqlLike returns false for clickhouse', function () {
    expect((new DatabaseResolver)->isMysqlLike('clickhouse'))->toBeFalse();
});

test('isMysqlLike returns false for redis', function () {
    expect((new DatabaseResolver)->isMysqlLike('redis'))->toBeFalse();
});
