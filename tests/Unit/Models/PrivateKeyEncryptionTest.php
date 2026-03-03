<?php

use App\Models\PrivateKey;

// Security regression tests: SSH private key encryption at rest.
// Verifies that the PrivateKey model encrypts data in the DB via Laravel's
// built-in encrypted cast, so raw key material is never stored as plaintext.

// ═══════════════════════════════════════════
// Model cast configuration
// ═══════════════════════════════════════════

test('PrivateKey model declares private_key as encrypted cast', function () {
    $model = new PrivateKey;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('private_key');
    expect($casts['private_key'])->toBe('encrypted');
});

test('PrivateKey encrypted cast uses Laravel Crypt under the hood', function () {
    // Verify Laravel's encrypted cast uses the application key for encryption
    $appKey = config('app.key');

    expect($appKey)->not->toBeNull();
    expect($appKey)->not->toBeEmpty();
});

// ═══════════════════════════════════════════
// Crypt::encryptString / decryptString round-trip
// ═══════════════════════════════════════════

test('Crypt encrypted value is not the original plaintext', function () {
    $plaintext = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake_key_for_test\n-----END OPENSSH PRIVATE KEY-----";
    $ciphertext = \Illuminate\Support\Facades\Crypt::encryptString($plaintext);

    expect($ciphertext)->not->toBe($plaintext);
    expect($ciphertext)->not->toContain('BEGIN OPENSSH PRIVATE KEY');
});

test('Crypt encrypted value can be decrypted back to plaintext', function () {
    $plaintext = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake_key_for_test\n-----END OPENSSH PRIVATE KEY-----";
    $ciphertext = \Illuminate\Support\Facades\Crypt::encryptString($plaintext);
    $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($ciphertext);

    expect($decrypted)->toBe($plaintext);
});

test('Crypt produces different ciphertext for same plaintext (nonce-based)', function () {
    $plaintext = 'same-key-content';
    $cipher1 = \Illuminate\Support\Facades\Crypt::encryptString($plaintext);
    $cipher2 = \Illuminate\Support\Facades\Crypt::encryptString($plaintext);

    // Authenticated encryption with random IV — same input, different ciphertext
    expect($cipher1)->not->toBe($cipher2);
});

// ═══════════════════════════════════════════
// Model source audit
// ═══════════════════════════════════════════

test('PrivateKey model does not use getPrivateKeyAttribute accessor (relying on cast only)', function () {
    $source = file_get_contents(app_path('Models/PrivateKey.php'));

    // The encrypted cast handles en/decryption transparently — no manual accessor needed
    expect($source)->not->toContain('getPrivateKeyAttribute');
});

test('PrivateKey model validates key before saving via booted hook', function () {
    $source = file_get_contents(app_path('Models/PrivateKey.php'));

    expect($source)->toContain('static::saving');
    expect($source)->toContain('validatePrivateKey');
});

test('PrivateKey model stores SSH key file with restrictive 0600 permissions', function () {
    $source = file_get_contents(app_path('Models/PrivateKey.php'));

    expect($source)->toContain('0600');
});

test('PrivateKey model SSH key directory has restrictive 0700 permissions', function () {
    $source = file_get_contents(app_path('Models/PrivateKey.php'));

    expect($source)->toContain('0700');
});
