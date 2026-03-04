<?php

/**
 * Unit tests for additional RepositoryAnalyzer DTOs:
 * AppDependency, DetectedDatabase, DockerComposeService, MonorepoInfo.
 *
 * Tests cover:
 * - AppDependency: hasDependencies()
 * - DetectedDatabase: default envVarName by type, withMergedConsumers() immutability
 * - DockerComposeService: isDatabase(), getDatabaseType(), getDefaultPort()
 * - MonorepoInfo: notMonorepo() factory
 */

use App\Services\RepositoryAnalyzer\DTOs\AppDependency;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\DTOs\DockerComposeService;
use App\Services\RepositoryAnalyzer\DTOs\MonorepoInfo;

// ─── AppDependency ────────────────────────────────────────────────────────────

test('AppDependency hasDependencies returns true when dependsOn is not empty', function () {
    $dep = new AppDependency('frontend', dependsOn: ['api', 'auth']);
    expect($dep->hasDependencies())->toBeTrue();
});

test('AppDependency hasDependencies returns false when dependsOn is empty', function () {
    $dep = new AppDependency('standalone-app');
    expect($dep->hasDependencies())->toBeFalse();
});

test('AppDependency deployOrder defaults to 0', function () {
    $dep = new AppDependency('my-app');
    expect($dep->deployOrder)->toBe(0);
});

test('AppDependency stores internalUrls correctly', function () {
    $dep = new AppDependency('frontend', internalUrls: ['API_URL' => 'api', 'AUTH_URL' => 'auth']);
    expect($dep->internalUrls)->toBe(['API_URL' => 'api', 'AUTH_URL' => 'auth']);
});

// ─── DetectedDatabase envVarName defaults ─────────────────────────────────────

test('DetectedDatabase defaults envVarName to DATABASE_URL for postgresql', function () {
    $db = new DetectedDatabase('postgresql', 'main-db');
    expect($db->envVarName)->toBe('DATABASE_URL');
});

test('DetectedDatabase defaults envVarName to DATABASE_URL for mysql', function () {
    $db = new DetectedDatabase('mysql', 'main-db');
    expect($db->envVarName)->toBe('DATABASE_URL');
});

test('DetectedDatabase defaults envVarName to DATABASE_URL for mariadb', function () {
    $db = new DetectedDatabase('mariadb', 'main-db');
    expect($db->envVarName)->toBe('DATABASE_URL');
});

test('DetectedDatabase defaults envVarName to MONGODB_URL for mongodb', function () {
    $db = new DetectedDatabase('mongodb', 'main-db');
    expect($db->envVarName)->toBe('MONGODB_URL');
});

test('DetectedDatabase defaults envVarName to REDIS_URL for redis', function () {
    $db = new DetectedDatabase('redis', 'cache');
    expect($db->envVarName)->toBe('REDIS_URL');
});

test('DetectedDatabase defaults envVarName to CLICKHOUSE_URL for clickhouse', function () {
    $db = new DetectedDatabase('clickhouse', 'analytics');
    expect($db->envVarName)->toBe('CLICKHOUSE_URL');
});

test('DetectedDatabase defaults envVarName to TYPE_URL for unknown type', function () {
    $db = new DetectedDatabase('cassandra', 'my-db');
    expect($db->envVarName)->toBe('CASSANDRA_URL');
});

test('DetectedDatabase uses explicit envVarName when provided', function () {
    $db = new DetectedDatabase('postgresql', 'main-db', 'POSTGRES_DATABASE_URL');
    expect($db->envVarName)->toBe('POSTGRES_DATABASE_URL');
});

// ─── DetectedDatabase withMergedConsumers ─────────────────────────────────────

test('DetectedDatabase withMergedConsumers creates new instance with merged consumers', function () {
    $original = new DetectedDatabase('redis', 'cache', consumers: ['api']);
    $merged = $original->withMergedConsumers(['worker', 'scheduler']);

    expect($merged->consumers)->toContain('api');
    expect($merged->consumers)->toContain('worker');
    expect($merged->consumers)->toContain('scheduler');
    expect($original->consumers)->toBe(['api']); // immutable
});

test('DetectedDatabase withMergedConsumers deduplicates consumers', function () {
    $original = new DetectedDatabase('redis', 'cache', consumers: ['api', 'worker']);
    $merged = $original->withMergedConsumers(['worker', 'new-service']);

    $unique = array_unique($merged->consumers);
    expect(count($merged->consumers))->toBe(count($unique));
    expect($merged->consumers)->toContain('new-service');
});

// ─── DockerComposeService isDatabase ──────────────────────────────────────────

test('DockerComposeService isDatabase returns true for postgres image', function () {
    $svc = new DockerComposeService('db', 'postgres:15');
    expect($svc->isDatabase())->toBeTrue();
});

test('DockerComposeService isDatabase returns true for mysql image', function () {
    $svc = new DockerComposeService('db', 'mysql:8');
    expect($svc->isDatabase())->toBeTrue();
});

test('DockerComposeService isDatabase returns true for mariadb image', function () {
    $svc = new DockerComposeService('db', 'mariadb:10.11');
    expect($svc->isDatabase())->toBeTrue();
});

test('DockerComposeService isDatabase returns true for mongo image', function () {
    $svc = new DockerComposeService('db', 'mongo:6');
    expect($svc->isDatabase())->toBeTrue();
});

test('DockerComposeService isDatabase returns true for redis image', function () {
    $svc = new DockerComposeService('cache', 'redis:7-alpine');
    expect($svc->isDatabase())->toBeTrue();
});

test('DockerComposeService isDatabase returns true for clickhouse image', function () {
    $svc = new DockerComposeService('analytics', 'clickhouse/clickhouse-server:latest');
    expect($svc->isDatabase())->toBeTrue();
});

test('DockerComposeService isDatabase returns false for app image', function () {
    $svc = new DockerComposeService('app', 'node:18-alpine');
    expect($svc->isDatabase())->toBeFalse();
});

test('DockerComposeService isDatabase returns false for nginx image', function () {
    $svc = new DockerComposeService('proxy', 'nginx:latest');
    expect($svc->isDatabase())->toBeFalse();
});

// ─── DockerComposeService getDatabaseType ─────────────────────────────────────

test('DockerComposeService getDatabaseType returns postgresql for postgres image', function () {
    $svc = new DockerComposeService('db', 'postgres:15');
    expect($svc->getDatabaseType())->toBe('postgresql');
});

test('DockerComposeService getDatabaseType returns mysql for mariadb image', function () {
    $svc = new DockerComposeService('db', 'mariadb:10');
    expect($svc->getDatabaseType())->toBe('mysql');
});

test('DockerComposeService getDatabaseType returns mongodb for mongo image', function () {
    $svc = new DockerComposeService('db', 'mongo:6');
    expect($svc->getDatabaseType())->toBe('mongodb');
});

test('DockerComposeService getDatabaseType returns redis for redis image', function () {
    $svc = new DockerComposeService('cache', 'redis:7');
    expect($svc->getDatabaseType())->toBe('redis');
});

test('DockerComposeService getDatabaseType returns null for non-database image', function () {
    $svc = new DockerComposeService('app', 'node:18');
    expect($svc->getDatabaseType())->toBeNull();
});

// ─── DockerComposeService getDefaultPort ──────────────────────────────────────

test('DockerComposeService getDefaultPort parses host:container format', function () {
    $svc = new DockerComposeService('app', 'node:18', ports: ['3001:3000']);
    expect($svc->getDefaultPort())->toBe(3000); // container port
});

test('DockerComposeService getDefaultPort returns plain port number', function () {
    $svc = new DockerComposeService('app', 'node:18', ports: ['8080']);
    expect($svc->getDefaultPort())->toBe(8080);
});

test('DockerComposeService getDefaultPort returns null when no ports', function () {
    $svc = new DockerComposeService('app', 'node:18');
    expect($svc->getDefaultPort())->toBeNull();
});

// ─── MonorepoInfo ─────────────────────────────────────────────────────────────

test('MonorepoInfo::notMonorepo creates instance with isMonorepo=false', function () {
    $info = MonorepoInfo::notMonorepo();
    expect($info->isMonorepo)->toBeFalse();
    expect($info->type)->toBeNull();
    expect($info->workspacePaths)->toBe([]);
});

test('MonorepoInfo can be created as monorepo with type and paths', function () {
    $info = new MonorepoInfo(true, 'npm-workspaces', ['packages/api', 'packages/web']);
    expect($info->isMonorepo)->toBeTrue();
    expect($info->type)->toBe('npm-workspaces');
    expect($info->workspacePaths)->toHaveCount(2);
});
