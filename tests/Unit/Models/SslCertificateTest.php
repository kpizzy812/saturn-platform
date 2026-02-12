<?php

use App\Models\Server;
use App\Models\SslCertificate;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $cert = new SslCertificate;
    expect($cert->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new SslCertificate)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes expected fields', function () {
    $fillable = (new SslCertificate)->getFillable();

    expect($fillable)
        ->toContain('ssl_certificate')
        ->toContain('ssl_private_key')
        ->toContain('configuration_dir')
        ->toContain('mount_path')
        ->toContain('resource_type')
        ->toContain('resource_id')
        ->toContain('server_id')
        ->toContain('common_name')
        ->toContain('subject_alternative_names')
        ->toContain('valid_until')
        ->toContain('is_ca_certificate');
});

// Casts Tests
test('ssl_certificate is cast to encrypted', function () {
    $casts = (new SslCertificate)->getCasts();
    expect($casts['ssl_certificate'])->toBe('encrypted');
});

test('ssl_private_key is cast to encrypted', function () {
    $casts = (new SslCertificate)->getCasts();
    expect($casts['ssl_private_key'])->toBe('encrypted');
});

test('subject_alternative_names is cast to array', function () {
    $casts = (new SslCertificate)->getCasts();
    expect($casts['subject_alternative_names'])->toBe('array');
});

test('valid_until is cast to datetime', function () {
    $casts = (new SslCertificate)->getCasts();
    expect($casts['valid_until'])->toBe('datetime');
});

// Relationship Tests
test('application relationship returns morphTo', function () {
    $relation = (new SslCertificate)->application();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('service relationship returns morphTo', function () {
    $relation = (new SslCertificate)->service();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('database relationship returns morphTo', function () {
    $relation = (new SslCertificate)->database();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('server relationship returns belongsTo', function () {
    $relation = (new SslCertificate)->server();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Server::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new SslCertificate))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new SslCertificate))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $cert = new SslCertificate;
    $options = $cert->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Attribute Tests
test('common_name attribute works', function () {
    $cert = new SslCertificate;
    $cert->common_name = 'example.com';

    expect($cert->common_name)->toBe('example.com');
});

test('configuration_dir attribute works', function () {
    $cert = new SslCertificate;
    $cert->configuration_dir = '/etc/ssl/certs';

    expect($cert->configuration_dir)->toBe('/etc/ssl/certs');
});

test('mount_path attribute works', function () {
    $cert = new SslCertificate;
    $cert->mount_path = '/app/certs';

    expect($cert->mount_path)->toBe('/app/certs');
});
