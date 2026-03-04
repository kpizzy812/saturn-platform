<?php

/**
 * Unit tests for ValidProxyConfigFilename validation rule.
 *
 * Tests cover:
 * - Valid filenames pass
 * - Reserved filenames are rejected
 * - Path traversal characters are rejected
 * - Hidden files (dot-prefixed) are rejected
 * - Invalid characters are rejected
 * - Length limit (255 chars)
 */

use App\Rules\ValidProxyConfigFilename;

function proxyFilenameValid(string $value): bool
{
    $failed = false;
    (new ValidProxyConfigFilename)->validate('filename', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

function proxyFilenameInvalid(string $value): bool
{
    return ! proxyFilenameValid($value);
}

// ─── Valid filenames ──────────────────────────────────────────────────────────

test('accepts simple yaml filename', function () {
    expect(proxyFilenameValid('myconfig.yaml'))->toBeTrue();
});

test('accepts filename with hyphens and underscores', function () {
    expect(proxyFilenameValid('my-custom_config.yml'))->toBeTrue();
});

test('accepts alphanumeric filename without extension', function () {
    expect(proxyFilenameValid('myconfig'))->toBeTrue();
});

test('accepts filename with numbers', function () {
    expect(proxyFilenameValid('config01.yaml'))->toBeTrue();
});

test('accepts empty value without failure', function () {
    expect(proxyFilenameValid(''))->toBeTrue();
});

// ─── Reserved filenames ───────────────────────────────────────────────────────

test('rejects saturn.yaml reserved filename', function () {
    expect(proxyFilenameInvalid('saturn.yaml'))->toBeTrue();
});

test('rejects saturn.yml reserved filename', function () {
    expect(proxyFilenameInvalid('saturn.yml'))->toBeTrue();
});

test('rejects Caddyfile reserved filename', function () {
    expect(proxyFilenameInvalid('Caddyfile'))->toBeTrue();
});

// ─── Path traversal prevention ────────────────────────────────────────────────

test('rejects filename with forward slash', function () {
    expect(proxyFilenameInvalid('path/to/config.yaml'))->toBeTrue();
});

test('rejects filename with backslash', function () {
    expect(proxyFilenameInvalid('path\\config.yaml'))->toBeTrue();
});

test('rejects relative path traversal', function () {
    expect(proxyFilenameInvalid('../etc/passwd'))->toBeTrue();
});

// ─── Hidden file prevention ───────────────────────────────────────────────────

test('rejects filename starting with dot', function () {
    expect(proxyFilenameInvalid('.hidden.yaml'))->toBeTrue();
});

test('rejects .htaccess', function () {
    expect(proxyFilenameInvalid('.htaccess'))->toBeTrue();
});

// ─── Invalid characters ───────────────────────────────────────────────────────

test('rejects filename with spaces', function () {
    expect(proxyFilenameInvalid('my config.yaml'))->toBeTrue();
});

test('rejects filename with at-sign', function () {
    expect(proxyFilenameInvalid('my@config.yaml'))->toBeTrue();
});

test('rejects filename with dollar sign', function () {
    expect(proxyFilenameInvalid('my$config.yaml'))->toBeTrue();
});

// ─── Length limit ─────────────────────────────────────────────────────────────

test('rejects filename longer than 255 characters', function () {
    $long = str_repeat('a', 256);
    expect(proxyFilenameInvalid($long))->toBeTrue();
});

test('accepts filename exactly 255 characters', function () {
    $filename = str_repeat('a', 255);
    expect(proxyFilenameValid($filename))->toBeTrue();
});
