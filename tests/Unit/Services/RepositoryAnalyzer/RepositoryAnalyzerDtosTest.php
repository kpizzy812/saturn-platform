<?php

/**
 * Unit tests for RepositoryAnalyzer DTO classes: DockerfileInfo, DetectedApp, CIConfig.
 *
 * Tests cover:
 * - DockerfileInfo: getPrimaryPort(), isMultiStage(), getNodeVersion(), getPythonVersion(), getGoVersion()
 * - DetectedApp: withName(), withPort(), withHealthCheck(), withApplicationMode(), isStatic(), hasBackend()
 * - CIConfig: hasAnyCommand() for all combinations
 */

use App\Services\RepositoryAnalyzer\DTOs\CIConfig;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\DTOs\DetectedHealthCheck;
use App\Services\RepositoryAnalyzer\DTOs\DockerfileInfo;

// ─── DockerfileInfo ───────────────────────────────────────────────────────────

test('DockerfileInfo getPrimaryPort returns first exposed port', function () {
    $info = new DockerfileInfo(exposedPorts: [3000, 8080]);
    expect($info->getPrimaryPort())->toBe(3000);
});

test('DockerfileInfo getPrimaryPort returns null when no ports', function () {
    $info = new DockerfileInfo;
    expect($info->getPrimaryPort())->toBeNull();
});

test('DockerfileInfo isMultiStage returns true when baseImage contains " AS "', function () {
    $info = new DockerfileInfo(baseImage: 'node:18-alpine AS builder');
    expect($info->isMultiStage())->toBeTrue();
});

test('DockerfileInfo isMultiStage returns false for single-stage image', function () {
    $info = new DockerfileInfo(baseImage: 'node:18-alpine');
    expect($info->isMultiStage())->toBeFalse();
});

test('DockerfileInfo isMultiStage returns false when baseImage is null', function () {
    $info = new DockerfileInfo;
    expect($info->isMultiStage())->toBeFalse();
});

test('DockerfileInfo getNodeVersion extracts version from node:18', function () {
    $info = new DockerfileInfo(baseImage: 'node:18');
    expect($info->getNodeVersion())->toBe('18');
});

test('DockerfileInfo getNodeVersion extracts version from node:18-alpine', function () {
    $info = new DockerfileInfo(baseImage: 'node:18-alpine');
    expect($info->getNodeVersion())->toBe('18');
});

test('DockerfileInfo getNodeVersion extracts major.minor from node:18.19', function () {
    $info = new DockerfileInfo(baseImage: 'node:18.19');
    expect($info->getNodeVersion())->toBe('18.19');
});

test('DockerfileInfo getNodeVersion returns null for non-node image', function () {
    $info = new DockerfileInfo(baseImage: 'python:3.11-slim');
    expect($info->getNodeVersion())->toBeNull();
});

test('DockerfileInfo getNodeVersion returns null when baseImage is null', function () {
    $info = new DockerfileInfo;
    expect($info->getNodeVersion())->toBeNull();
});

test('DockerfileInfo getPythonVersion extracts version from python:3.11', function () {
    $info = new DockerfileInfo(baseImage: 'python:3.11');
    expect($info->getPythonVersion())->toBe('3.11');
});

test('DockerfileInfo getPythonVersion extracts version from python:3.11-slim', function () {
    $info = new DockerfileInfo(baseImage: 'python:3.11-slim');
    expect($info->getPythonVersion())->toBe('3.11');
});

test('DockerfileInfo getPythonVersion returns null for non-python image', function () {
    $info = new DockerfileInfo(baseImage: 'node:18');
    expect($info->getPythonVersion())->toBeNull();
});

test('DockerfileInfo getGoVersion extracts version from golang:1.21', function () {
    $info = new DockerfileInfo(baseImage: 'golang:1.21');
    expect($info->getGoVersion())->toBe('1.21');
});

test('DockerfileInfo getGoVersion extracts version from golang:1.21-alpine', function () {
    $info = new DockerfileInfo(baseImage: 'golang:1.21-alpine');
    expect($info->getGoVersion())->toBe('1.21');
});

test('DockerfileInfo getGoVersion returns null for non-go image', function () {
    $info = new DockerfileInfo(baseImage: 'node:18');
    expect($info->getGoVersion())->toBeNull();
});

// ─── DetectedApp ──────────────────────────────────────────────────────────────

function makeDetectedApp(
    string $name = 'my-app',
    string $buildPack = 'nodejs',
    string $type = 'backend',
    string $applicationMode = 'web',
): DetectedApp {
    return new DetectedApp(
        name: $name,
        path: '.',
        framework: 'Express',
        buildPack: $buildPack,
        defaultPort: 3000,
        type: $type,
        applicationMode: $applicationMode,
    );
}

test('DetectedApp withName creates new instance with updated name', function () {
    $original = makeDetectedApp('old-name');
    $updated = $original->withName('new-name');

    expect($updated->name)->toBe('new-name');
    expect($original->name)->toBe('old-name'); // immutable
    expect($updated->buildPack)->toBe($original->buildPack); // other props unchanged
});

test('DetectedApp withPort creates new instance with updated port', function () {
    $original = makeDetectedApp();
    $updated = $original->withPort(8080);

    expect($updated->defaultPort)->toBe(8080);
    expect($original->defaultPort)->toBe(3000);
});

test('DetectedApp withHealthCheck creates new instance with healthcheck', function () {
    $hc = new DetectedHealthCheck('/health', 'GET', 10, 5);
    $original = makeDetectedApp();
    $updated = $original->withHealthCheck($hc);

    expect($updated->healthCheck)->toBe($hc);
    expect($original->healthCheck)->toBeNull();
});

test('DetectedApp withApplicationMode creates new instance with updated mode', function () {
    $original = makeDetectedApp();
    $updated = $original->withApplicationMode('worker');

    expect($updated->applicationMode)->toBe('worker');
    expect($original->applicationMode)->toBe('web');
});

test('DetectedApp isStatic returns true for static buildpack', function () {
    $app = makeDetectedApp(buildPack: 'static');
    expect($app->isStatic())->toBeTrue();
});

test('DetectedApp isStatic returns false for nodejs buildpack', function () {
    $app = makeDetectedApp(buildPack: 'nodejs');
    expect($app->isStatic())->toBeFalse();
});

test('DetectedApp isStatic returns false for dockerfile buildpack', function () {
    $app = makeDetectedApp(buildPack: 'dockerfile');
    expect($app->isStatic())->toBeFalse();
});

test('DetectedApp hasBackend returns true for backend type', function () {
    $app = makeDetectedApp(type: 'backend');
    expect($app->hasBackend())->toBeTrue();
});

test('DetectedApp hasBackend returns true for fullstack type', function () {
    $app = makeDetectedApp(type: 'fullstack');
    expect($app->hasBackend())->toBeTrue();
});

test('DetectedApp hasBackend returns false for frontend type', function () {
    $app = makeDetectedApp(type: 'frontend');
    expect($app->hasBackend())->toBeFalse();
});

test('DetectedApp hasBackend returns false for unknown type', function () {
    $app = makeDetectedApp(type: 'unknown');
    expect($app->hasBackend())->toBeFalse();
});

test('DetectedApp toArray includes all expected keys', function () {
    $app = makeDetectedApp();
    $array = $app->toArray();

    expect($array)->toHaveKeys([
        'name', 'path', 'framework', 'build_pack', 'default_port',
        'build_command', 'install_command', 'start_command', 'type',
        'health_check', 'node_version', 'python_version', 'application_mode',
    ]);
    expect($array['name'])->toBe('my-app');
    expect($array['build_pack'])->toBe('nodejs');
    expect($array['health_check'])->toBeNull();
});

test('DetectedApp toArray includes health check details when present', function () {
    $hc = new DetectedHealthCheck('/health', 'GET', 15, 10);
    $app = makeDetectedApp()->withHealthCheck($hc);
    $array = $app->toArray();

    expect($array['health_check'])->toMatchArray([
        'path' => '/health',
        'method' => 'GET',
        'interval' => 15,
        'timeout' => 10,
    ]);
});

// ─── CIConfig ─────────────────────────────────────────────────────────────────

test('CIConfig hasAnyCommand returns true when installCommand is set', function () {
    $ci = new CIConfig(installCommand: 'npm install');
    expect($ci->hasAnyCommand())->toBeTrue();
});

test('CIConfig hasAnyCommand returns true when buildCommand is set', function () {
    $ci = new CIConfig(buildCommand: 'npm run build');
    expect($ci->hasAnyCommand())->toBeTrue();
});

test('CIConfig hasAnyCommand returns true when startCommand is set', function () {
    $ci = new CIConfig(startCommand: 'node server.js');
    expect($ci->hasAnyCommand())->toBeTrue();
});

test('CIConfig hasAnyCommand returns true when testCommand is set', function () {
    $ci = new CIConfig(testCommand: 'npm test');
    expect($ci->hasAnyCommand())->toBeTrue();
});

test('CIConfig hasAnyCommand returns false when all commands are null', function () {
    $ci = new CIConfig(nodeVersion: '18', detectedFrom: 'package.json');
    expect($ci->hasAnyCommand())->toBeFalse();
});

test('CIConfig stores node version correctly', function () {
    $ci = new CIConfig(nodeVersion: '20');
    expect($ci->nodeVersion)->toBe('20');
});
