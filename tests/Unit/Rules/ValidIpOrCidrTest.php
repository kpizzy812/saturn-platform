<?php

/**
 * Unit tests for ValidIpOrCidr validation rule.
 *
 * Tests cover:
 * - Valid IPs and CIDR pass
 * - Invalid IPs fail
 * - Special cases: empty, 0.0.0.0
 * - Comma-separated multiple entries
 */

use App\Rules\ValidIpOrCidr;

function ipOrCidrValid(string $value): bool
{
    $failed = false;
    (new ValidIpOrCidr)->validate('ip', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

function ipOrCidrInvalid(string $value): bool
{
    return ! ipOrCidrValid($value);
}

// ─── Valid values ─────────────────────────────────────────────────────────────

test('accepts empty value', function () {
    expect(ipOrCidrValid(''))->toBeTrue();
});

test('accepts 0.0.0.0 special case', function () {
    expect(ipOrCidrValid('0.0.0.0'))->toBeTrue();
});

test('accepts valid IPv4 address', function () {
    expect(ipOrCidrValid('192.168.1.1'))->toBeTrue();
});

test('accepts valid IPv4 CIDR notation', function () {
    expect(ipOrCidrValid('192.168.0.0/24'))->toBeTrue();
});

test('accepts /32 CIDR', function () {
    expect(ipOrCidrValid('10.0.0.1/32'))->toBeTrue();
});

test('accepts /0 CIDR', function () {
    expect(ipOrCidrValid('0.0.0.0/0'))->toBeTrue();
});

test('accepts comma-separated valid IPs', function () {
    expect(ipOrCidrValid('192.168.1.1, 10.0.0.1'))->toBeTrue();
});

test('accepts comma-separated IP and CIDR', function () {
    expect(ipOrCidrValid('192.168.1.1, 10.0.0.0/8'))->toBeTrue();
});

test('accepts IPv6 address', function () {
    expect(ipOrCidrValid('::1'))->toBeTrue();
});

// ─── Invalid values ───────────────────────────────────────────────────────────

test('rejects invalid IP address', function () {
    expect(ipOrCidrInvalid('999.999.999.999'))->toBeTrue();
});

test('rejects invalid CIDR mask above 32', function () {
    expect(ipOrCidrInvalid('192.168.1.0/33'))->toBeTrue();
});

test('rejects CIDR with invalid IP', function () {
    expect(ipOrCidrInvalid('300.0.0.0/24'))->toBeTrue();
});

test('rejects text string', function () {
    expect(ipOrCidrInvalid('not-an-ip'))->toBeTrue();
});

test('rejects comma-separated with one invalid entry', function () {
    expect(ipOrCidrInvalid('192.168.1.1, invalid-entry'))->toBeTrue();
});

test('rejects CIDR with multiple slashes', function () {
    expect(ipOrCidrInvalid('192.168.1.0/24/8'))->toBeTrue();
});
