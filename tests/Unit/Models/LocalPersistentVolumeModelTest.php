<?php

use App\Models\LocalPersistentVolume;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $volume = new LocalPersistentVolume;
    expect($volume->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new LocalPersistentVolume)->getFillable();
    expect($fillable)->not->toContain('id');
});

test('fillable includes expected fields', function () {
    $fillable = (new LocalPersistentVolume)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('mount_path')
        ->toContain('host_path')
        ->toContain('resource_id')
        ->toContain('resource_type');
});

// Relationship Type Tests
test('resource relationship returns morphTo', function () {
    $relation = (new LocalPersistentVolume)->resource();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('application relationship returns morphTo', function () {
    $relation = (new LocalPersistentVolume)->application();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('service relationship returns morphTo', function () {
    $relation = (new LocalPersistentVolume)->service();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('database relationship returns morphTo', function () {
    $relation = (new LocalPersistentVolume)->database();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

// Mount Path Accessor Tests
test('mountPath accessor prepends slash', function () {
    $volume = new LocalPersistentVolume;
    $volume->mount_path = 'var/lib/data';

    expect($volume->mount_path)->toBe('/var/lib/data');
});

test('mountPath accessor trims whitespace', function () {
    $volume = new LocalPersistentVolume;
    $volume->mount_path = '  /var/lib/data  ';

    expect($volume->mount_path)->toBe('/var/lib/data');
});

test('mountPath accessor keeps existing slash', function () {
    $volume = new LocalPersistentVolume;
    $volume->mount_path = '/var/lib/postgresql/data';

    expect($volume->mount_path)->toBe('/var/lib/postgresql/data');
});

// Host Path Accessor Tests
test('hostPath accessor prepends slash', function () {
    $volume = new LocalPersistentVolume;
    $volume->host_path = 'data/postgres';

    expect($volume->host_path)->toBe('/data/postgres');
});

test('hostPath accessor trims whitespace', function () {
    $volume = new LocalPersistentVolume;
    $volume->host_path = '  /data/postgres  ';

    expect($volume->host_path)->toBe('/data/postgres');
});

test('hostPath accessor returns null for null', function () {
    $volume = new LocalPersistentVolume;
    $volume->host_path = null;

    expect($volume->host_path)->toBeNull();
});

// isServiceResource Tests
test('isServiceResource returns true for ServiceApplication type', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\ServiceApplication';

    expect($volume->isServiceResource())->toBeTrue();
});

test('isServiceResource returns true for ServiceDatabase type', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\ServiceDatabase';

    expect($volume->isServiceResource())->toBeTrue();
});

test('isServiceResource returns false for Application type', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\Application';

    expect($volume->isServiceResource())->toBeFalse();
});

test('isServiceResource returns false for database types', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\StandalonePostgresql';

    expect($volume->isServiceResource())->toBeFalse();
});

// isDockerComposeResource Tests
test('isDockerComposeResource returns false for non-Application type', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\ServiceApplication';

    expect($volume->isDockerComposeResource())->toBeFalse();
});

test('isDockerComposeResource returns false when resource not loaded', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\Application';

    expect($volume->isDockerComposeResource())->toBeFalse();
});

// shouldBeReadOnlyInUI Tests
test('shouldBeReadOnlyInUI returns true for ServiceApplication', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\ServiceApplication';

    expect($volume->shouldBeReadOnlyInUI())->toBeTrue();
});

test('shouldBeReadOnlyInUI returns true for ServiceDatabase', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\ServiceDatabase';

    expect($volume->shouldBeReadOnlyInUI())->toBeTrue();
});

test('shouldBeReadOnlyInUI returns false for regular Application without resource loaded', function () {
    $volume = new LocalPersistentVolume;
    $volume->resource_type = 'App\Models\Application';

    // Without resource loaded, isDockerComposeResource returns false
    // and isReadOnlyVolume returns false (no resource)
    expect($volume->shouldBeReadOnlyInUI())->toBeFalse();
});

// isReadOnlyVolume Tests
test('isReadOnlyVolume returns false when resource is null', function () {
    $volume = new LocalPersistentVolume;

    expect($volume->isReadOnlyVolume())->toBeFalse();
});

// Name Attribute Tests
test('name attribute works', function () {
    $volume = new LocalPersistentVolume;
    $volume->name = 'postgres-data-abc123';

    expect($volume->name)->toBe('postgres-data-abc123');
});
