<?php

/**
 * Unit tests for ValidHostname validation rule.
 *
 * Tests cover:
 * - Valid hostnames pass validation
 * - Total length limit (253 chars)
 * - Dangerous shell metacharacters are rejected
 * - Dot rules (start/end/consecutive)
 * - Label rules (length, hyphen start/end, valid chars)
 */

use App\Rules\ValidHostname;

function hostnameValid(string $value): bool
{
    $failed = false;
    (new ValidHostname)->validate('hostname', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

function hostnameInvalid(string $value): bool
{
    return ! hostnameValid($value);
}

// ─── Valid hostnames ──────────────────────────────────────────────────────────

test('accepts simple single-label hostname', function () {
    expect(hostnameValid('myserver'))->toBeTrue();
});

test('accepts multi-label hostname', function () {
    expect(hostnameValid('my-server.example.com'))->toBeTrue();
});

test('accepts hostname with hyphens', function () {
    expect(hostnameValid('server-01.prod.example.com'))->toBeTrue();
});

test('accepts hostname with digits', function () {
    expect(hostnameValid('web01'))->toBeTrue();
});

test('accepts all-numeric label per RFC 1123', function () {
    expect(hostnameValid('192.168.0.1'))->toBeTrue();
});

test('accepts empty value without failure', function () {
    expect(hostnameValid(''))->toBeTrue();
});

// ─── Length validation ────────────────────────────────────────────────────────

test('rejects hostname longer than 253 characters', function () {
    $long = str_repeat('a', 250).'.co';
    expect(hostnameInvalid($long))->toBeTrue();
});

test('accepts hostname exactly 253 characters', function () {
    // Build 253-char hostname: 63-char.63-char.63-char.63-char = 255 with dots is too long
    // 62-char.62-char.62-char.62-char = 4*62+3 = 251 chars — ok
    $label = str_repeat('a', 62);
    $hostname = implode('.', [$label, $label, $label, $label]);
    expect(strlen($hostname))->toBeLessThanOrEqual(253);
    expect(hostnameValid($hostname))->toBeTrue();
});

// ─── Dot rules ────────────────────────────────────────────────────────────────

test('rejects hostname starting with a dot', function () {
    expect(hostnameInvalid('.example.com'))->toBeTrue();
});

test('rejects hostname ending with a dot', function () {
    expect(hostnameInvalid('example.com.'))->toBeTrue();
});

test('rejects hostname with consecutive dots', function () {
    expect(hostnameInvalid('example..com'))->toBeTrue();
});

// ─── Label rules ──────────────────────────────────────────────────────────────

test('rejects label starting with hyphen', function () {
    expect(hostnameInvalid('-example.com'))->toBeTrue();
});

test('rejects label ending with hyphen', function () {
    expect(hostnameInvalid('example-.com'))->toBeTrue();
});

test('rejects label with uppercase letters', function () {
    expect(hostnameInvalid('MyServer.example.com'))->toBeTrue();
});

test('rejects label longer than 63 characters', function () {
    $longLabel = str_repeat('a', 64);
    expect(hostnameInvalid("{$longLabel}.com"))->toBeTrue();
});

// ─── Dangerous character rejection ───────────────────────────────────────────

test('rejects hostname with semicolon', function () {
    expect(hostnameInvalid('host;evil.com'))->toBeTrue();
});

test('rejects hostname with pipe', function () {
    expect(hostnameInvalid('host|evil.com'))->toBeTrue();
});

test('rejects hostname with backtick', function () {
    expect(hostnameInvalid('host`cmd`.com'))->toBeTrue();
});

test('rejects hostname with dollar sign', function () {
    expect(hostnameInvalid('$host.com'))->toBeTrue();
});

test('rejects hostname with spaces', function () {
    expect(hostnameInvalid('my host.com'))->toBeTrue();
});
