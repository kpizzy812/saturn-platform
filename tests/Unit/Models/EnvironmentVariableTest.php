<?php

use App\Models\EnvironmentVariable;

// isNixpacks Attribute Tests
test('isNixpacks returns true for NIXPACKS_ prefixed key', function () {
    $env = new EnvironmentVariable;
    $env->key = 'NIXPACKS_NODE_VERSION';
    expect($env->is_nixpacks)->toBeTrue();
});

test('isNixpacks returns false for regular key', function () {
    $env = new EnvironmentVariable;
    $env->key = 'APP_NAME';
    expect($env->is_nixpacks)->toBeFalse();
});

test('isNixpacks returns false for key containing but not starting with NIXPACKS_', function () {
    $env = new EnvironmentVariable;
    $env->key = 'MY_NIXPACKS_VAR';
    expect($env->is_nixpacks)->toBeFalse();
});

// isSaturn Attribute Tests
test('isSaturn returns true for SERVICE_ prefixed key', function () {
    $env = new EnvironmentVariable;
    $env->key = 'SERVICE_FQDN_APP';
    expect($env->is_saturn)->toBeTrue();
});

test('isSaturn returns false for regular key', function () {
    $env = new EnvironmentVariable;
    $env->key = 'DATABASE_URL';
    expect($env->is_saturn)->toBeFalse();
});

// isShared Attribute Tests
test('isShared returns true for valid template syntax', function () {
    $env = new EnvironmentVariable;
    $env->value = '{{postgres.DATABASE_URL}}';
    expect($env->is_shared)->toBeTrue();
});

test('isShared returns true for service template', function () {
    $env = new EnvironmentVariable;
    $env->value = '{{service.API_KEY}}';
    expect($env->is_shared)->toBeTrue();
});

test('isShared returns false for regular value', function () {
    $env = new EnvironmentVariable;
    $env->value = 'my-database-url';
    expect($env->is_shared)->toBeFalse();
});

test('isShared returns false for partial template', function () {
    $env = new EnvironmentVariable;
    $env->value = '{{incomplete';
    expect($env->is_shared)->toBeFalse();
});

test('isShared returns false for empty value', function () {
    $env = new EnvironmentVariable;
    $env->value = '';
    expect($env->is_shared)->toBeFalse();
});

// isReallyRequired Attribute Tests
test('isReallyRequired returns true when required and value empty', function () {
    $env = new EnvironmentVariable;
    $env->is_required = true;
    $env->value = '';
    expect($env->is_really_required)->toBeTrue();
});

test('isReallyRequired returns false when required and value set', function () {
    $env = new EnvironmentVariable;
    $env->is_required = true;
    $env->value = 'some-value';
    expect($env->is_really_required)->toBeFalse();
});

test('isReallyRequired returns false when not required', function () {
    $env = new EnvironmentVariable;
    $env->is_required = false;
    $env->value = '';
    expect($env->is_really_required)->toBeFalse();
});

// key() Attribute Setter Tests
test('key setter trims whitespace', function () {
    $env = new EnvironmentVariable;
    $env->key = '  MY_VAR  ';
    expect($env->key)->toBe('MY_VAR');
});

test('key setter replaces spaces with underscores', function () {
    $env = new EnvironmentVariable;
    $env->key = 'MY VAR';
    expect($env->key)->toBe('MY_VAR');
});

test('key setter allows valid POSIX names', function () {
    $env = new EnvironmentVariable;
    $env->key = 'MY_VAR_123';
    expect($env->key)->toBe('MY_VAR_123');
});

test('key setter allows underscore prefix', function () {
    $env = new EnvironmentVariable;
    $env->key = '_PRIVATE_VAR';
    expect($env->key)->toBe('_PRIVATE_VAR');
});

test('key setter rejects invalid characters', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'MY-VAR')->toThrow(\InvalidArgumentException::class);
});

test('key setter rejects keys starting with digit', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = '123_VAR')->toThrow(\InvalidArgumentException::class);
});

test('key setter blocks PATH protected key', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'PATH')->toThrow(\InvalidArgumentException::class, 'protected');
});

test('key setter blocks LD_PRELOAD protected key', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'LD_PRELOAD')->toThrow(\InvalidArgumentException::class, 'protected');
});

test('key setter blocks HOME protected key', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'HOME')->toThrow(\InvalidArgumentException::class, 'protected');
});

test('key setter blocks protected keys case-insensitively', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'path')->toThrow(\InvalidArgumentException::class, 'protected');
});

// PROTECTED_KEYS constant
test('PROTECTED_KEYS contains expected security-critical keys', function () {
    expect(EnvironmentVariable::PROTECTED_KEYS)
        ->toContain('PATH')
        ->toContain('LD_PRELOAD')
        ->toContain('LD_LIBRARY_PATH')
        ->toContain('SHELL')
        ->toContain('HOME');
});

// updateIsShared Tests
test('updateIsShared sets is_shared to true for template value', function () {
    $env = new EnvironmentVariable;
    $env->value = '{{postgres.DB_URL}}';
    $env->is_shared = false;

    $reflection = new ReflectionMethod($env, 'updateIsShared');
    $reflection->invoke($env);

    expect($env->is_shared)->toBeTrue();
});

test('updateIsShared sets is_shared to false for regular value', function () {
    $env = new EnvironmentVariable;
    $env->value = 'regular-value';
    $env->is_shared = true;

    $reflection = new ReflectionMethod($env, 'updateIsShared');
    $reflection->invoke($env);

    expect($env->is_shared)->toBeFalse();
});
