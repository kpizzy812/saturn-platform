<?php

/**
 * Unit tests for ApplicationTemplate model.
 *
 * Tests cover:
 * - categories() static method returns all expected keys and labels
 * - getApplicationConfig() merges template config over defaults
 * - getEnvironmentVariables() extracts env vars from config
 */

use App\Models\ApplicationTemplate;

// ─── categories() static method ───────────────────────────────────────────────

test('categories returns an array', function () {
    expect(ApplicationTemplate::categories())->toBeArray();
});

test('categories includes nodejs entry', function () {
    $cats = ApplicationTemplate::categories();
    expect($cats)->toHaveKey('nodejs');
    expect($cats['nodejs'])->toBe('Node.js');
});

test('categories includes php entry', function () {
    $cats = ApplicationTemplate::categories();
    expect($cats)->toHaveKey('php');
    expect($cats['php'])->toBe('PHP');
});

test('categories includes docker entry', function () {
    $cats = ApplicationTemplate::categories();
    expect($cats)->toHaveKey('docker');
    expect($cats['docker'])->toBe('Docker');
});

test('categories includes all expected keys', function () {
    $cats = ApplicationTemplate::categories();
    expect($cats)->toHaveKeys([
        'nodejs', 'php', 'python', 'ruby', 'go', 'rust',
        'java', 'dotnet', 'static', 'docker', 'general',
    ]);
});

test('categories has 11 entries', function () {
    expect(count(ApplicationTemplate::categories()))->toBe(11);
});

// ─── getApplicationConfig() ───────────────────────────────────────────────────

test('getApplicationConfig returns defaults when config is null', function () {
    $template = new ApplicationTemplate;
    $template->setRawAttributes(['config' => null]);

    $config = $template->getApplicationConfig();

    expect($config['build_pack'])->toBe('nixpacks');
    expect($config['ports_exposes'])->toBe('3000');
    expect($config['base_directory'])->toBe('/');
    expect($config['health_check_path'])->toBe('/');
    expect($config['health_check_enabled'])->toBeTrue();
});

test('getApplicationConfig overrides defaults with template values', function () {
    $template = new ApplicationTemplate;
    $template->setRawAttributes([
        'config' => json_encode([
            'build_pack' => 'dockerfile',
            'ports_exposes' => '8080',
        ]),
    ]);

    $config = $template->getApplicationConfig();

    expect($config['build_pack'])->toBe('dockerfile');
    expect($config['ports_exposes'])->toBe('8080');
    // Defaults still applied for unset keys
    expect($config['base_directory'])->toBe('/');
    expect($config['health_check_enabled'])->toBeTrue();
});

test('getApplicationConfig preserves custom config keys alongside defaults', function () {
    $template = new ApplicationTemplate;
    $template->setRawAttributes([
        'config' => json_encode([
            'install_command' => 'npm ci',
            'build_command' => 'npm run build',
        ]),
    ]);

    $config = $template->getApplicationConfig();

    expect($config)->toHaveKey('install_command');
    expect($config['install_command'])->toBe('npm ci');
    expect($config['build_pack'])->toBe('nixpacks'); // default preserved
});

// ─── getEnvironmentVariables() ────────────────────────────────────────────────

test('getEnvironmentVariables returns empty array when config has no env vars', function () {
    $template = new ApplicationTemplate;
    $template->setRawAttributes(['config' => json_encode(['build_pack' => 'nixpacks'])]);

    expect($template->getEnvironmentVariables())->toBe([]);
});

test('getEnvironmentVariables returns empty array when config is null', function () {
    $template = new ApplicationTemplate;
    $template->setRawAttributes(['config' => null]);

    expect($template->getEnvironmentVariables())->toBe([]);
});

test('getEnvironmentVariables returns env vars from config', function () {
    $envVars = [
        ['key' => 'NODE_ENV', 'value' => 'production'],
        ['key' => 'PORT', 'value' => '3000'],
    ];

    $template = new ApplicationTemplate;
    $template->setRawAttributes([
        'config' => json_encode([
            'environment_variables' => $envVars,
        ]),
    ]);

    expect($template->getEnvironmentVariables())->toBe($envVars);
});

// ─── Fillable contract ────────────────────────────────────────────────────────

test('fillable includes name', function () {
    $template = new ApplicationTemplate;
    expect(in_array('name', $template->getFillable()))->toBeTrue();
});

test('fillable includes category', function () {
    $template = new ApplicationTemplate;
    expect(in_array('category', $template->getFillable()))->toBeTrue();
});

test('fillable does not include usage_count (system-managed)', function () {
    $template = new ApplicationTemplate;
    expect(in_array('usage_count', $template->getFillable()))->toBeFalse();
});

test('fillable does not include slug (auto-generated)', function () {
    $template = new ApplicationTemplate;
    expect(in_array('slug', $template->getFillable()))->toBeFalse();
});
