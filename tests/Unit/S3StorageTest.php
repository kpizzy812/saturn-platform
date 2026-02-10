<?php

use App\Models\S3Storage;

test('S3Storage model has correct cast definitions', function () {
    $s3Storage = new S3Storage;
    $casts = $s3Storage->getCasts();

    expect($casts['is_usable'])->toBe('boolean');
    expect($casts['key'])->toBe('encrypted');
    expect($casts['secret'])->toBe('encrypted');
});

test('S3Storage isUsable method returns is_usable attribute value', function () {
    $s3Storage = new S3Storage;

    // Set the attribute directly to avoid encryption
    $s3Storage->setRawAttributes(['is_usable' => true]);
    expect($s3Storage->isUsable())->toBeTrue();

    $s3Storage->setRawAttributes(['is_usable' => false]);
    expect($s3Storage->isUsable())->toBeFalse();

    $s3Storage->setRawAttributes(['is_usable' => null]);
    expect($s3Storage->isUsable())->toBeNull();
});

test('S3Storage awsUrl method constructs correct URL format', function () {
    $s3Storage = new S3Storage;

    // Set attributes without triggering encryption
    $s3Storage->setRawAttributes([
        'endpoint' => 'https://s3.amazonaws.com',
        'bucket' => 'test-bucket',
    ]);

    expect($s3Storage->awsUrl())->toBe('https://s3.amazonaws.com/test-bucket');

    // Test with custom endpoint
    $s3Storage->setRawAttributes([
        'endpoint' => 'https://minio.example.com:9000',
        'bucket' => 'backups',
    ]);

    expect($s3Storage->awsUrl())->toBe('https://minio.example.com:9000/backups');
});

test('S3Storage model has correct fillable fields', function () {
    $s3Storage = new S3Storage;

    // The model should use explicit $fillable instead of $guarded = [] for security
    $fillable = $s3Storage->getFillable();
    expect($fillable)->toContain('uuid');
    expect($fillable)->toContain('name');
    expect($fillable)->toContain('key');
    expect($fillable)->toContain('secret');
    expect($fillable)->toContain('bucket');
    expect($fillable)->toContain('region');
    expect($fillable)->toContain('endpoint');
    // Security-sensitive fields should NOT be in fillable
    expect($fillable)->not->toContain('id');
    expect($fillable)->not->toContain('team_id');
    expect($fillable)->not->toContain('is_usable');
});
