<?php

/**
 * Unit tests for DockerImageFormat validation rule.
 *
 * Tests cover:
 * - Valid image formats (name only, name:tag, registry/image:tag, @sha256 digest)
 * - Invalid ":sha256:" colon format (should use "@sha256:")
 * - Invalid image name patterns
 */

use App\Rules\DockerImageFormat;

function dockerImageValid(string $value): bool
{
    $failed = false;
    (new DockerImageFormat)->validate('image', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

function dockerImageInvalid(string $value): bool
{
    return ! dockerImageValid($value);
}

// ─── Valid formats ────────────────────────────────────────────────────────────

test('accepts simple image name without tag', function () {
    expect(dockerImageValid('nginx'))->toBeTrue();
});

test('accepts image with latest tag', function () {
    expect(dockerImageValid('nginx:latest'))->toBeTrue();
});

test('accepts image with version tag', function () {
    expect(dockerImageValid('nginx:1.25.3'))->toBeTrue();
});

test('accepts image with alpine variant tag', function () {
    expect(dockerImageValid('node:20-alpine'))->toBeTrue();
});

test('accepts ghcr.io registry image with tag', function () {
    expect(dockerImageValid('ghcr.io/user/app:v1.2.3'))->toBeTrue();
});

test('accepts docker.io registry image', function () {
    expect(dockerImageValid('docker.io/library/nginx:latest'))->toBeTrue();
});

test('accepts registry with port and image tag', function () {
    expect(dockerImageValid('localhost:5000/app:latest'))->toBeTrue();
});

test('accepts image with sha256 digest via @', function () {
    $hash = str_repeat('a', 64);
    expect(dockerImageValid("nginx@sha256:{$hash}"))->toBeTrue();
});

test('accepts registry image with sha256 digest via @', function () {
    $hash = str_repeat('b', 64);
    expect(dockerImageValid("ghcr.io/user/app@sha256:{$hash}"))->toBeTrue();
});

test('accepts image with dot in name', function () {
    expect(dockerImageValid('my.app:latest'))->toBeTrue();
});

test('accepts image with underscore in name', function () {
    expect(dockerImageValid('my_app:latest'))->toBeTrue();
});

test('accepts multi-segment registry path', function () {
    expect(dockerImageValid('registry.example.com/org/team/app:prod'))->toBeTrue();
});

// ─── Invalid ":sha256:" format (should use "@sha256:") ───────────────────────

test('rejects image:sha256:hash format (colon instead of @)', function () {
    $hash = str_repeat('a', 64);
    expect(dockerImageInvalid("nginx:sha256:{$hash}"))->toBeTrue();
});

test('rejects image:sha256:hash with mixed case SHA', function () {
    $hash = str_repeat('A', 64);
    expect(dockerImageInvalid("nginx:SHA256:{$hash}"))->toBeTrue();
});

// ─── Invalid image names ──────────────────────────────────────────────────────

test('accepts image name with uppercase letters (case-insensitive regex)', function () {
    // The rule uses /i flag, so uppercase is allowed
    expect(dockerImageValid('Nginx:latest'))->toBeTrue();
});

test('rejects image name starting with hyphen', function () {
    expect(dockerImageInvalid('-nginx:latest'))->toBeTrue();
});

test('rejects tag with spaces', function () {
    expect(dockerImageInvalid('nginx:my tag'))->toBeTrue();
});

test('rejects image with shell special chars', function () {
    expect(dockerImageInvalid('nginx;evil:latest'))->toBeTrue();
});

test('rejects sha256 digest shorter than 64 hex chars', function () {
    expect(dockerImageInvalid('nginx@sha256:abc123'))->toBeTrue();
});

test('rejects sha256 digest longer than 64 hex chars', function () {
    $hash = str_repeat('a', 65);
    expect(dockerImageInvalid("nginx@sha256:{$hash}"))->toBeTrue();
});
