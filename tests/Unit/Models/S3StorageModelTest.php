<?php

use App\Models\S3Storage;
use App\Models\Team;

// type() Tests
test('type returns s3-storage string from class name', function () {
    $s3 = new S3Storage;
    expect($s3)->toBeInstanceOf(S3Storage::class);
});

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $s3 = new S3Storage;
    expect($s3->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or team_id', function () {
    $fillable = (new S3Storage)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('team_id');
});

test('fillable does not include system-managed fields', function () {
    $fillable = (new S3Storage)->getFillable();

    expect($fillable)
        ->not->toContain('is_usable')
        ->not->toContain('unusable_email_sent');
});

test('fillable includes expected fields', function () {
    $fillable = (new S3Storage)->getFillable();

    expect($fillable)
        ->toContain('uuid')
        ->toContain('name')
        ->toContain('description')
        ->toContain('key')
        ->toContain('secret')
        ->toContain('bucket')
        ->toContain('region')
        ->toContain('endpoint')
        ->toContain('path');
});

// Casts Tests
test('is_usable is cast to boolean', function () {
    $casts = (new S3Storage)->getCasts();
    expect($casts['is_usable'])->toBe('boolean');
});

test('key is cast to encrypted', function () {
    $casts = (new S3Storage)->getCasts();
    expect($casts['key'])->toBe('encrypted');
});

test('secret is cast to encrypted', function () {
    $casts = (new S3Storage)->getCasts();
    expect($casts['secret'])->toBe('encrypted');
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new S3Storage)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// isUsable Tests
test('isUsable returns is_usable value when true', function () {
    $s3 = new S3Storage;
    $s3->is_usable = true;
    expect($s3->isUsable())->toBeTrue();
});

test('isUsable returns is_usable value when false', function () {
    $s3 = new S3Storage;
    $s3->is_usable = false;
    expect($s3->isUsable())->toBeFalse();
});

// awsUrl Tests
test('awsUrl concatenates endpoint and bucket', function () {
    $s3 = new S3Storage;
    $s3->endpoint = 'https://s3.amazonaws.com';
    $s3->bucket = 'my-bucket';

    expect($s3->awsUrl())->toBe('https://s3.amazonaws.com/my-bucket');
});

test('awsUrl works with custom endpoint', function () {
    $s3 = new S3Storage;
    $s3->endpoint = 'https://minio.example.com';
    $s3->bucket = 'backups';

    expect($s3->awsUrl())->toBe('https://minio.example.com/backups');
});

// Path Accessor Tests
test('path accessor prepends slash', function () {
    $s3 = new S3Storage;
    $s3->path = 'backups/daily';

    expect($s3->path)->toBe('/backups/daily');
});

test('path accessor trims whitespace', function () {
    $s3 = new S3Storage;
    $s3->path = '  /backups  ';

    expect($s3->path)->toBe('/backups');
});

test('path accessor returns null for empty string', function () {
    $s3 = new S3Storage;
    $s3->path = '';

    expect($s3->path)->toBeNull();
});

test('path accessor returns null for null', function () {
    $s3 = new S3Storage;
    $s3->path = null;

    expect($s3->path)->toBeNull();
});

// Endpoint Accessor Tests
test('endpoint accessor trims whitespace', function () {
    $s3 = new S3Storage;
    $s3->endpoint = '  https://s3.amazonaws.com  ';

    expect($s3->endpoint)->toBe('https://s3.amazonaws.com');
});

test('endpoint accessor returns null for null', function () {
    $s3 = new S3Storage;
    $s3->endpoint = null;

    expect($s3->endpoint)->toBeNull();
});

// Bucket Accessor Tests
test('bucket accessor trims whitespace', function () {
    $s3 = new S3Storage;
    $s3->bucket = '  my-bucket  ';

    expect($s3->bucket)->toBe('my-bucket');
});

// Region Accessor Tests
test('region accessor trims whitespace', function () {
    $s3 = new S3Storage;
    $s3->region = '  us-east-1  ';

    expect($s3->region)->toBe('us-east-1');
});

test('region accessor returns null for null', function () {
    $s3 = new S3Storage;
    $s3->region = null;

    expect($s3->region)->toBeNull();
});

// Attribute Tests
test('name attribute works', function () {
    $s3 = new S3Storage;
    $s3->name = 'Production Backups';

    expect($s3->name)->toBe('Production Backups');
});

test('description attribute works', function () {
    $s3 = new S3Storage;
    $s3->description = 'S3 storage for daily backups';

    expect($s3->description)->toBe('S3 storage for daily backups');
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new S3Storage))->toContain(\App\Traits\Auditable::class);
});

test('uses HasSafeStringAttribute trait', function () {
    expect(class_uses_recursive(new S3Storage))->toContain(\App\Traits\HasSafeStringAttribute::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new S3Storage))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $s3 = new S3Storage;
    $options = $s3->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});
