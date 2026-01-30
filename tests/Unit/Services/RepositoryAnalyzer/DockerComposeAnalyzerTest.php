<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\DockerComposeAnalyzer;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/docker-compose-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->analyzer = new DockerComposeAnalyzer;
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec('rm -rf '.escapeshellarg($this->tempDir));
    }
});

test('detects postgresql from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  app:
    build: .
    ports:
      - "3000:3000"
  db:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: mydb
      POSTGRES_USER: user
      POSTGRES_PASSWORD: password
    ports:
      - "5432:5432"
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(2);
    expect($result['databases'])->toHaveCount(1);

    $dbService = collect($result['services'])->first(fn ($s) => $s->name === 'db');
    expect($dbService)->not->toBeNull();
    expect($dbService->isDatabase())->toBeTrue();
    expect($dbService->getDatabaseType())->toBe('postgresql');
    expect($dbService->getDefaultPort())->toBe(5432);

    expect($result['databases'][0]->type)->toBe('postgresql');
});

test('detects redis from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  cache:
    image: redis:7-alpine
    ports:
      - "6379:6379"
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(1);
    expect($result['services'][0]->isDatabase())->toBeTrue();
    expect($result['services'][0]->getDatabaseType())->toBe('redis');
});

test('detects mongodb from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  mongodb:
    image: mongo:6
    ports:
      - "27017:27017"
    environment:
      MONGO_INITDB_ROOT_USERNAME: root
      MONGO_INITDB_ROOT_PASSWORD: password
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(1);
    expect($result['services'][0]->isDatabase())->toBeTrue();
    expect($result['services'][0]->getDatabaseType())->toBe('mongodb');
    expect($result['services'][0]->getDefaultPort())->toBe(27017);
});

test('detects mysql from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(1);
    expect($result['services'][0]->isDatabase())->toBeTrue();
    expect($result['services'][0]->getDatabaseType())->toBe('mysql');
});

test('detects clickhouse from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  analytics:
    image: clickhouse/clickhouse-server:latest
    ports:
      - "8123:8123"
      - "9000:9000"
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(1);
    expect($result['services'][0]->isDatabase())->toBeTrue();
    expect($result['services'][0]->getDatabaseType())->toBe('clickhouse');
});

test('app service is not database', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  web:
    build: .
    ports:
      - "3000:3000"
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(1);
    expect($result['services'][0]->isDatabase())->toBeFalse();
    expect($result['services'][0]->getDatabaseType())->toBeNull();
});

test('returns empty arrays when no docker compose', function () {
    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toBeEmpty();
    expect($result['databases'])->toBeEmpty();
    expect($result['externalServices'])->toBeEmpty();
});

test('extracts ports from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8080:3000"
      - "443:443"
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(1);
    expect($result['services'][0]->ports)->toContain('8080:3000');
    expect($result['services'][0]->ports)->toContain('443:443');
});

test('handles docker compose with depends_on', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  app:
    build: .
    depends_on:
      - db
      - redis
  db:
    image: postgres:15
  redis:
    image: redis:7
YAML);

    $result = $this->analyzer->analyze($this->tempDir);

    expect($result['services'])->toHaveCount(3);

    $appService = collect($result['services'])->first(fn ($s) => $s->name === 'app');
    expect($appService->dependsOn)->toContain('db');
    expect($appService->dependsOn)->toContain('redis');
});
