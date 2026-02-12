<?php

use App\Models\PrivateKey;

// Use Ed25519 keys for testing — RSA triggers PHP 8.5 float-string warnings in phpseclib
beforeAll(function () {
    // Generate a single Ed25519 key to reuse across tests (fast, no PHP warnings)
    $ec = \phpseclib3\Crypt\EC::createKey('Ed25519');
    $GLOBALS['testPrivateKey'] = $ec->toString('OpenSSH');
});

function testKey(): string
{
    return $GLOBALS['testPrivateKey'];
}

// validatePrivateKey Tests
test('validatePrivateKey returns true for valid Ed25519 key', function () {
    expect(PrivateKey::validatePrivateKey(testKey()))->toBeTrue();
});

test('validatePrivateKey returns false for invalid key', function () {
    expect(PrivateKey::validatePrivateKey('not-a-real-key'))->toBeFalse();
});

test('validatePrivateKey returns false for empty string', function () {
    expect(PrivateKey::validatePrivateKey(''))->toBeFalse();
});

test('validatePrivateKey returns false for random text', function () {
    expect(PrivateKey::validatePrivateKey('-----BEGIN RSA PRIVATE KEY-----\ngarbage\n-----END RSA PRIVATE KEY-----'))->toBeFalse();
});

// extractPublicKeyFromPrivate Tests
test('extractPublicKeyFromPrivate returns public key for valid private key', function () {
    $publicKey = PrivateKey::extractPublicKeyFromPrivate(testKey());

    expect($publicKey)
        ->not->toBeNull()
        ->toStartWith('ssh-ed25519 ');
});

test('extractPublicKeyFromPrivate returns null for invalid key', function () {
    expect(PrivateKey::extractPublicKeyFromPrivate('invalid-key'))->toBeNull();
});

test('extractPublicKeyFromPrivate returns null for empty string', function () {
    expect(PrivateKey::extractPublicKeyFromPrivate(''))->toBeNull();
});

// generateFingerprint Tests
test('generateFingerprint returns fingerprint for valid key', function () {
    $fingerprint = PrivateKey::generateFingerprint(testKey());

    expect($fingerprint)
        ->not->toBeNull()
        ->toBeString();
});

test('generateFingerprint returns null for invalid key', function () {
    expect(PrivateKey::generateFingerprint('not-a-key'))->toBeNull();
});

test('generateFingerprint is deterministic for same key', function () {
    $fp1 = PrivateKey::generateFingerprint(testKey());
    $fp2 = PrivateKey::generateFingerprint(testKey());

    expect($fp1)->toBe($fp2);
});

// generateMd5Fingerprint Tests
test('generateMd5Fingerprint returns fingerprint for valid key', function () {
    $fingerprint = PrivateKey::generateMd5Fingerprint(testKey());

    expect($fingerprint)
        ->not->toBeNull()
        ->toBeString();
});

test('generateMd5Fingerprint returns null for invalid key', function () {
    expect(PrivateKey::generateMd5Fingerprint('not-a-key'))->toBeNull();
});

test('generateMd5Fingerprint differs from sha256 fingerprint', function () {
    $sha256 = PrivateKey::generateFingerprint(testKey());
    $md5 = PrivateKey::generateMd5Fingerprint(testKey());

    expect($sha256)->not->toBe($md5);
});

// validateAndExtractPublicKey Tests
test('validateAndExtractPublicKey returns valid result for valid key', function () {
    $result = PrivateKey::validateAndExtractPublicKey(testKey());

    expect($result)
        ->toBeArray()
        ->toHaveKey('isValid')
        ->toHaveKey('publicKey');
    expect($result['isValid'])->toBeTrue();
    expect($result['publicKey'])->toStartWith('ssh-ed25519 ');
});

test('validateAndExtractPublicKey returns invalid for bad key', function () {
    $result = PrivateKey::validateAndExtractPublicKey('bad-key');

    expect($result['isValid'])->toBeFalse();
    expect($result['publicKey'])->toBe('');
});

// getKeyLocation Tests
test('getKeyLocation returns path with uuid', function () {
    $key = new PrivateKey;
    $key->uuid = 'abc-123-def';

    expect($key->getKeyLocation())
        ->toBe('/var/www/html/storage/app/ssh/keys/ssh_key@abc-123-def');
});

test('getKeyLocation includes correct prefix and uuid', function () {
    $key = new PrivateKey;
    $key->uuid = 'test-uuid';

    expect($key->getKeyLocation())
        ->toStartWith('/var/www/html/storage/app/ssh/keys/ssh_key@')
        ->toContain('test-uuid');
});

// getPublicKey Tests (via static method — instance needs encrypted cast)
test('getPublicKey extracts public key from valid private key', function () {
    $publicKey = PrivateKey::extractPublicKeyFromPrivate(testKey());

    expect($publicKey)
        ->toBeString()
        ->toStartWith('ssh-ed25519 ');
});

// formatPrivateKey helper Tests
test('formatPrivateKey trims whitespace', function () {
    $formatted = formatPrivateKey("  some-key-content  \n");

    expect($formatted)->toBe("some-key-content\n");
});

test('formatPrivateKey adds trailing newline if missing', function () {
    $formatted = formatPrivateKey('some-key-content');

    expect($formatted)->toEndWith("\n");
});

test('formatPrivateKey preserves trailing newline', function () {
    $formatted = formatPrivateKey("some-key-content\n");

    expect($formatted)->toBe("some-key-content\n");
});

// $fillable security Tests
test('fillable does not include sensitive fields', function () {
    $fillable = (new PrivateKey)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('uuid')
        ->not->toContain('public_key');
});

test('fillable includes expected fields', function () {
    $fillable = (new PrivateKey)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('description')
        ->toContain('private_key')
        ->toContain('is_git_related')
        ->toContain('team_id')
        ->toContain('fingerprint');
});

// Casts Tests
test('private_key is cast to encrypted', function () {
    $casts = (new PrivateKey)->getCasts();

    expect($casts['private_key'])->toBe('encrypted');
});

// Appends Tests
test('public_key is appended to model', function () {
    $key = new PrivateKey;
    $appends = (new \ReflectionProperty($key, 'appends'))->getValue($key);

    expect($appends)->toContain('public_key');
});
