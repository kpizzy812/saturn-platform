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

// Note: isReallyRequired checks real_value (resolved via accessor), not value directly.
// Without a DB resource, real_value is always null/empty, so we test what we can.
test('isReallyRequired returns true when required and real_value is empty', function () {
    $env = new EnvironmentVariable;
    $env->is_required = true;
    $env->value = 'some-value'; // real_value will be null without DB context
    expect($env->is_really_required)->toBeTrue();
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

// Fillable Security Tests
test('model uses fillable array for mass assignment protection', function () {
    $env = new EnvironmentVariable;

    expect($env->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new EnvironmentVariable)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes expected fields', function () {
    $fillable = (new EnvironmentVariable)->getFillable();

    expect($fillable)
        ->toContain('key')
        ->toContain('value')
        ->toContain('is_literal')
        ->toContain('is_multiline')
        ->toContain('is_preview')
        ->toContain('is_runtime')
        ->toContain('is_buildtime')
        ->toContain('is_shown_once')
        ->toContain('is_required')
        ->toContain('description')
        ->toContain('source_template')
        ->toContain('version')
        ->toContain('resourceable_type')
        ->toContain('resourceable_id');
});

// Casts Tests
test('value is cast to encrypted', function () {
    $casts = (new EnvironmentVariable)->getCasts();

    expect($casts['value'])->toBe('encrypted');
});

test('is_multiline is cast to boolean', function () {
    $casts = (new EnvironmentVariable)->getCasts();

    expect($casts['is_multiline'])->toBe('boolean');
});

test('is_preview is cast to boolean', function () {
    $casts = (new EnvironmentVariable)->getCasts();

    expect($casts['is_preview'])->toBe('boolean');
});

test('is_runtime is cast to boolean', function () {
    $casts = (new EnvironmentVariable)->getCasts();

    expect($casts['is_runtime'])->toBe('boolean');
});

test('is_buildtime is cast to boolean', function () {
    $casts = (new EnvironmentVariable)->getCasts();

    expect($casts['is_buildtime'])->toBe('boolean');
});

test('resourceable_id is cast to integer', function () {
    $casts = (new EnvironmentVariable)->getCasts();

    expect($casts['resourceable_id'])->toBe('integer');
});

// Relationship Tests
test('service relationship returns belongsTo', function () {
    $relation = (new EnvironmentVariable)->service();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('resourceable relationship returns morphTo', function () {
    $relation = (new EnvironmentVariable)->resourceable();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

// Appends Tests
test('appends includes expected computed attributes', function () {
    $env = new EnvironmentVariable;
    $appends = (new \ReflectionProperty($env, 'appends'))->getValue($env);

    expect($appends)
        ->toContain('real_value')
        ->toContain('is_shared')
        ->toContain('is_really_required')
        ->toContain('is_nixpacks')
        ->toContain('is_saturn');
});

// PROTECTED_KEYS Comprehensive Tests
test('PROTECTED_KEYS blocks DOCKER_HOST', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'DOCKER_HOST')->toThrow(\InvalidArgumentException::class, 'protected');
});

test('PROTECTED_KEYS blocks SSH_AUTH_SOCK', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'SSH_AUTH_SOCK')->toThrow(\InvalidArgumentException::class, 'protected');
});

test('PROTECTED_KEYS blocks LD_AUDIT', function () {
    $env = new EnvironmentVariable;
    expect(fn () => $env->key = 'LD_AUDIT')->toThrow(\InvalidArgumentException::class, 'protected');
});

test('key setter allows non-protected keys', function () {
    $env = new EnvironmentVariable;
    $env->key = 'DATABASE_URL';
    expect($env->key)->toBe('DATABASE_URL');
});

test('key setter allows NIXPACKS prefixed keys', function () {
    $env = new EnvironmentVariable;
    $env->key = 'NIXPACKS_NODE_VERSION';
    expect($env->key)->toBe('NIXPACKS_NODE_VERSION');
});

test('key setter allows SERVICE prefixed keys', function () {
    $env = new EnvironmentVariable;
    $env->key = 'SERVICE_FQDN_APP';
    expect($env->key)->toBe('SERVICE_FQDN_APP');
});

// Boolean Attribute Tests
test('is_literal attribute works', function () {
    $env = new EnvironmentVariable;
    $env->is_literal = true;

    expect($env->is_literal)->toBeTrue();
});

test('is_shown_once attribute works', function () {
    $env = new EnvironmentVariable;
    $env->is_shown_once = true;

    expect($env->is_shown_once)->toBeTrue();
});

test('description attribute works', function () {
    $env = new EnvironmentVariable;
    $env->description = 'API key for external service';

    expect($env->description)->toBe('API key for external service');
});

test('source_template attribute works', function () {
    $env = new EnvironmentVariable;
    $env->source_template = 'env_example';

    expect($env->source_template)->toBe('env_example');
});
