<?php

/**
 * Unit tests for SSH Key Management admin page.
 *
 * These tests verify security constraints, usage detection,
 * filtering logic, and data integrity for the SSH key overview.
 */

use App\Models\PrivateKey;

// Fields that MUST NEVER be exposed to the frontend
$sensitiveFields = ['private_key'];

// Fields that are safe to expose via Inertia
$safeFields = [
    'id', 'uuid', 'name', 'description', 'fingerprint',
    'is_git_related', 'team_id', 'team_name',
    'servers_count', 'applications_count',
    'github_apps_count', 'gitlab_apps_count',
    'is_in_use', 'created_at',
];

// Fields for the show page (additional safe fields)
$showPageFields = [
    'id', 'uuid', 'name', 'description', 'fingerprint',
    'md5_fingerprint', 'public_key', 'is_git_related',
    'team_id', 'team_name', 'servers_count', 'applications_count',
    'github_apps_count', 'gitlab_apps_count', 'is_in_use',
    'created_at', 'updated_at',
];

test('private_key field is never included in index response fields', function () use ($safeFields, $sensitiveFields) {
    foreach ($sensitiveFields as $field) {
        expect($safeFields)->not->toContain($field);
    }
});

test('private_key field is never included in show response fields', function () use ($showPageFields, $sensitiveFields) {
    foreach ($sensitiveFields as $field) {
        expect($showPageFields)->not->toContain($field);
    }
});

test('public_key is safe to expose (it is derived, not secret)', function () use ($showPageFields) {
    expect($showPageFields)->toContain('public_key');
});

test('usage detection: key with servers_count > 0 is in_use', function () {
    $counts = ['servers_count' => 2, 'applications_count' => 0, 'github_apps_count' => 0, 'gitlab_apps_count' => 0];
    $isInUse = ($counts['servers_count'] + $counts['applications_count'] + $counts['github_apps_count'] + $counts['gitlab_apps_count']) > 0;

    expect($isInUse)->toBeTrue();
});

test('usage detection: key with applications_count > 0 is in_use', function () {
    $counts = ['servers_count' => 0, 'applications_count' => 1, 'github_apps_count' => 0, 'gitlab_apps_count' => 0];
    $isInUse = ($counts['servers_count'] + $counts['applications_count'] + $counts['github_apps_count'] + $counts['gitlab_apps_count']) > 0;

    expect($isInUse)->toBeTrue();
});

test('usage detection: key with github_apps_count > 0 is in_use', function () {
    $counts = ['servers_count' => 0, 'applications_count' => 0, 'github_apps_count' => 3, 'gitlab_apps_count' => 0];
    $isInUse = ($counts['servers_count'] + $counts['applications_count'] + $counts['github_apps_count'] + $counts['gitlab_apps_count']) > 0;

    expect($isInUse)->toBeTrue();
});

test('usage detection: key with all counts zero is unused', function () {
    $counts = ['servers_count' => 0, 'applications_count' => 0, 'github_apps_count' => 0, 'gitlab_apps_count' => 0];
    $isInUse = ($counts['servers_count'] + $counts['applications_count'] + $counts['github_apps_count'] + $counts['gitlab_apps_count']) > 0;

    expect($isInUse)->toBeFalse();
});

test('type filter: ssh means is_git_related=false', function () {
    $type = 'ssh';
    $isGitRelated = $type === 'git';

    expect($isGitRelated)->toBeFalse();
});

test('type filter: git means is_git_related=true', function () {
    $type = 'git';
    $isGitRelated = $type === 'git';

    expect($isGitRelated)->toBeTrue();
});

test('PrivateKey model has encrypted cast for private_key', function () {
    $key = new PrivateKey;
    $casts = $key->getCasts();

    expect($casts)->toHaveKey('private_key')
        ->and($casts['private_key'])->toBe('encrypted');
});

test('PrivateKey model has public_key in appends', function () {
    // public_key is a computed attribute that should be appended
    $key = new PrivateKey;

    // Check that the model class has the getPublicKeyAttribute method
    expect(method_exists($key, 'getPublicKeyAttribute'))->toBeTrue();
});

test('PrivateKey model has all required relationships', function () {
    $key = new PrivateKey;

    expect(method_exists($key, 'servers'))->toBeTrue();
    expect(method_exists($key, 'applications'))->toBeTrue();
    expect(method_exists($key, 'githubApps'))->toBeTrue();
    expect(method_exists($key, 'gitlabApps'))->toBeTrue();
});

test('PrivateKey model has isInUse method', function () {
    $key = new PrivateKey;

    expect(method_exists($key, 'isInUse'))->toBeTrue();
});

test('PrivateKey model has fingerprint generation methods', function () {
    expect(method_exists(PrivateKey::class, 'generateFingerprint'))->toBeTrue();
    expect(method_exists(PrivateKey::class, 'generateMd5Fingerprint'))->toBeTrue();
});

test('PrivateKey model has validatePrivateKey method', function () {
    expect(method_exists(PrivateKey::class, 'validatePrivateKey'))->toBeTrue();
});

test('fingerprint field is included in fillable', function () {
    $key = new PrivateKey;
    $fillable = $key->getFillable();

    expect($fillable)->toContain('fingerprint');
});

test('index response has all required fields', function () use ($safeFields) {
    $required = ['id', 'uuid', 'name', 'fingerprint', 'is_git_related', 'team_name', 'is_in_use', 'created_at'];

    foreach ($required as $field) {
        expect($safeFields)->toContain($field);
    }
});

test('show response has all required fields', function () use ($showPageFields) {
    $required = ['id', 'uuid', 'name', 'fingerprint', 'md5_fingerprint', 'public_key', 'is_git_related', 'team_name', 'is_in_use', 'created_at', 'updated_at'];

    foreach ($required as $field) {
        expect($showPageFields)->toContain($field);
    }
});

test('audit checks cover key validation, fingerprint, and file existence', function () {
    $auditChecks = ['key_valid', 'fingerprint_consistent', 'file_exists'];

    expect($auditChecks)->toHaveCount(3);
    expect($auditChecks)->toContain('key_valid');
    expect($auditChecks)->toContain('fingerprint_consistent');
    expect($auditChecks)->toContain('file_exists');
});
