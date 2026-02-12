<?php

use App\Models\EnvironmentVariable;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\Service;
use App\Models\ServiceApplication;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $serviceApp = new ServiceApplication;
    expect($serviceApp->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ServiceApplication)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable does not include system-managed fields', function () {
    $fillable = (new ServiceApplication)->getFillable();

    expect($fillable)
        ->not->toContain('status')
        ->not->toContain('last_online_at');
});

test('fillable includes expected fields', function () {
    $fillable = (new ServiceApplication)->getFillable();

    expect($fillable)
        ->toContain('uuid')
        ->toContain('name')
        ->toContain('description')
        ->toContain('image')
        ->toContain('fqdn')
        ->toContain('service_id')
        ->toContain('is_log_drain_enabled')
        ->toContain('is_stripprefix_enabled')
        ->toContain('is_gzip_enabled');
});

// Relationship Tests
test('service relationship returns belongsTo', function () {
    $relation = (new ServiceApplication)->service();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Service::class);
});

test('persistentStorages relationship returns morphMany', function () {
    $relation = (new ServiceApplication)->persistentStorages();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(LocalPersistentVolume::class);
});

test('fileStorages relationship returns morphMany', function () {
    $relation = (new ServiceApplication)->fileStorages();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(LocalFileVolume::class);
});

test('environment_variables relationship returns morphMany', function () {
    $relation = (new ServiceApplication)->environment_variables();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(EnvironmentVariable::class);
});

// Trait Tests
test('uses HasFactory trait', function () {
    expect(class_uses_recursive(new ServiceApplication))->toContain(\Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

test('uses SoftDeletes trait', function () {
    expect(class_uses_recursive(new ServiceApplication))->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
});

// Method Tests
test('type returns service string', function () {
    $serviceApp = new ServiceApplication;
    expect($serviceApp->type())->toBe('service');
});

test('isLogDrainEnabled returns boolean', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->is_log_drain_enabled = true;
    expect($serviceApp->isLogDrainEnabled())->toBeTrue();

    $serviceApp->is_log_drain_enabled = false;
    expect($serviceApp->isLogDrainEnabled())->toBeFalse();
});

test('isStripprefixEnabled returns true by default', function () {
    $serviceApp = new ServiceApplication;
    expect($serviceApp->isStripprefixEnabled())->toBeTrue();
});

test('isGzipEnabled returns true by default', function () {
    $serviceApp = new ServiceApplication;
    expect($serviceApp->isGzipEnabled())->toBeTrue();
});

test('allFqdnsHavePort returns false when fqdn is null', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->fqdn = null;
    expect($serviceApp->allFqdnsHavePort())->toBeFalse();
});

test('allFqdnsHavePort returns false when fqdn is empty', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->fqdn = '';
    expect($serviceApp->allFqdnsHavePort())->toBeFalse();
});

test('extractPortFromUrl returns port number from URL', function () {
    expect(ServiceApplication::extractPortFromUrl('http://example.com:8080'))->toBe(8080);
    expect(ServiceApplication::extractPortFromUrl('https://example.com:443'))->toBe(443);
});

test('extractPortFromUrl returns null when no port specified', function () {
    expect(ServiceApplication::extractPortFromUrl('http://example.com'))->toBeNull();
    expect(ServiceApplication::extractPortFromUrl('https://example.com'))->toBeNull();
});

test('extractPortFromUrl handles URLs without scheme', function () {
    expect(ServiceApplication::extractPortFromUrl('example.com:3000'))->toBe(3000);
});

// Attribute Tests
test('name attribute works', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->name = 'web-service';

    expect($serviceApp->name)->toBe('web-service');
});

test('description attribute works', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->description = 'Web frontend service';

    expect($serviceApp->description)->toBe('Web frontend service');
});

test('image accessor uses sanitize_string on raw original', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->setRawAttributes(['image' => 'nginx:latest'], true);

    expect($serviceApp->image)->toBe('nginx:latest');
});

test('fqdn attribute works', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->fqdn = 'example.com';

    expect($serviceApp->fqdn)->toBe('example.com');
});

// Fqdns Accessor Tests
test('fqdns accessor returns empty array when fqdn is null', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->fqdn = null;

    expect($serviceApp->fqdns)->toBe([]);
});

test('fqdns accessor returns array of domains when fqdn contains comma-separated values', function () {
    $serviceApp = new ServiceApplication;
    $serviceApp->fqdn = 'example.com,app.example.com,api.example.com';

    expect($serviceApp->fqdns)->toBe(['example.com', 'app.example.com', 'api.example.com']);
});
