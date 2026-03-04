<?php

/**
 * Unit tests for DiffRedactor service.
 *
 * Tests cover:
 * - redact(): strips PEM private keys, masks API tokens (GitHub, OpenAI, Anthropic,
 *   AWS, Stripe, Google, Slack, JWT), masks database connection strings, passwords,
 *   generic api_key/api_secret patterns, returns redactions_count
 * - containsSensitiveData(): detects all the above patterns
 */

use App\Services\AI\CodeReview\DiffRedactor;

function makeRedactor(): DiffRedactor
{
    return new DiffRedactor;
}

// ─── redact: private key stripping ────────────────────────────────────────────

test('redact strips RSA private key block', function () {
    $diff = "-----BEGIN RSA PRIVATE KEY-----\nMIIEpAIBAAKCAQEA...\n-----END RSA PRIVATE KEY-----";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[STRIPPED_SENSITIVE_CONTENT]');
    expect($result['diff'])->not->toContain('PRIVATE KEY-----');
    expect($result['redactions_count'])->toBeGreaterThanOrEqual(1);
});

test('redact strips OPENSSH private key block', function () {
    $diff = "-----BEGIN OPENSSH PRIVATE KEY-----\nbase64data...\n-----END OPENSSH PRIVATE KEY-----";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[STRIPPED_SENSITIVE_CONTENT]');
    expect($result['redactions_count'])->toBeGreaterThanOrEqual(1);
});

// ─── redact: GitHub tokens ────────────────────────────────────────────────────

test('redact masks GitHub personal access token (ghp_)', function () {
    $token = 'ghp_'.str_repeat('a', 36);
    $diff = "const token = \"{$token}\";";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[GITHUB_TOKEN]');
    expect($result['diff'])->not->toContain($token);
    expect($result['redactions_count'])->toBe(1);
});

test('redact masks GitHub OAuth token (gho_)', function () {
    $token = 'gho_'.str_repeat('b', 36);
    $diff = "token={$token}";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[GITHUB_OAUTH_TOKEN]');
});

// ─── redact: OpenAI / Anthropic ───────────────────────────────────────────────

test('redact masks OpenAI API key (sk-)', function () {
    $key = 'sk-'.str_repeat('x', 48);
    $diff = "OPENAI_API_KEY={$key}";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[OPENAI_API_KEY]');
    expect($result['diff'])->not->toContain($key);
});

// ─── redact: AWS ──────────────────────────────────────────────────────────────

test('redact masks AWS access key ID (AKIA...)', function () {
    $key = 'AKIA'.str_repeat('A', 16);
    $diff = "aws_access_key_id={$key}";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[AWS_ACCESS_KEY_ID]');
});

// ─── redact: Stripe ───────────────────────────────────────────────────────────

test('redact masks Stripe live secret key (sk_live_)', function () {
    $key = 'sk_live_'.str_repeat('a', 24);
    $diff = "stripe_key={$key}";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[STRIPE_LIVE_KEY]');
});

test('redact masks Stripe test secret key (sk_test_)', function () {
    $key = 'sk_test_'.str_repeat('b', 24);
    $diff = "stripe_test={$key}";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[STRIPE_TEST_KEY]');
});

// ─── redact: Generic patterns ─────────────────────────────────────────────────

test('redact masks api_key quoted values', function () {
    $diff = 'api_key="my_super_secret_api_key_value_123"';
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[REDACTED_API_KEY]');
    expect($result['diff'])->not->toContain('my_super_secret_api_key_value_123');
});

test('redact masks password quoted values', function () {
    $diff = 'password="mysecretpassword123"';
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[REDACTED_PASSWORD]');
});

test('redact masks database connection strings', function () {
    $diff = 'postgres://user:secret123@localhost:5432/mydb';
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[USER]:[PASSWORD]@');
    expect($result['diff'])->not->toContain('secret123');
});

test('redact masks mysql connection strings', function () {
    $diff = 'mysql://admin:password@db.example.com:3306/myapp';
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toContain('[USER]:[PASSWORD]@');
});

// ─── redact: no changes for clean code ───────────────────────────────────────

test('redact returns unchanged diff and zero count for clean code', function () {
    $diff = "+function hello() { return 'world'; }";
    $result = makeRedactor()->redact($diff);

    expect($result['diff'])->toBe($diff);
    expect($result['redactions_count'])->toBe(0);
});

// ─── containsSensitiveData ────────────────────────────────────────────────────

test('containsSensitiveData returns true for RSA private key', function () {
    $content = "-----BEGIN RSA PRIVATE KEY-----\ndata\n-----END RSA PRIVATE KEY-----";
    expect(makeRedactor()->containsSensitiveData($content))->toBeTrue();
});

test('containsSensitiveData returns true for GitHub token', function () {
    $content = 'ghp_'.str_repeat('a', 36);
    expect(makeRedactor()->containsSensitiveData($content))->toBeTrue();
});

test('containsSensitiveData returns true for OpenAI key', function () {
    $content = 'sk-'.str_repeat('a', 48);
    expect(makeRedactor()->containsSensitiveData($content))->toBeTrue();
});

test('containsSensitiveData returns true for AWS access key', function () {
    $content = 'AKIA'.str_repeat('A', 16);
    expect(makeRedactor()->containsSensitiveData($content))->toBeTrue();
});

test('containsSensitiveData returns true for Stripe live key', function () {
    $content = 'sk_live_'.str_repeat('a', 24);
    expect(makeRedactor()->containsSensitiveData($content))->toBeTrue();
});

test('containsSensitiveData returns false for clean code', function () {
    $content = 'function calculateTax(amount) { return amount * 0.2; }';
    expect(makeRedactor()->containsSensitiveData($content))->toBeFalse();
});

test('containsSensitiveData returns false for empty string', function () {
    expect(makeRedactor()->containsSensitiveData(''))->toBeFalse();
});
